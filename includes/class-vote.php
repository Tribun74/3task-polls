<?php
/**
 * TPoll Vote Handler
 *
 * @package TPoll
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPoll_Vote {

    /**
     * Cast a vote
     *
     * @param int       $poll_id    Poll ID.
     * @param int|int[] $answer_ids Answer ID(s).
     * @param int       $value      Value (stars, thumbs, slider).
     * @param int       $context    Post ID when the same poll is counted per post (feedback), else 0.
     * @param string    $ballot     ID shared by several polls sent together (questionnaire), empty for a new one.
     */
    public static function cast($poll_id, $answer_ids, $value = 1, $context = 0, $ballot = '') {
        global $wpdb;

        $context = absint($context);

        $poll = TPoll_Poll::get($poll_id);

        if (!$poll) {
            return new WP_Error('invalid_poll', __('Poll not found.', '3task-polls'));
        }

        // Check if poll is active
        if (!$poll->is_active()) {
            return new WP_Error('poll_closed', __('This poll is not active.', '3task-polls'));
        }

        $restriction = $poll->get_setting('vote_restriction', 'ip');

        if ($restriction === 'user' && !is_user_logged_in()) {
            return new WP_Error('login_required', __('Please log in to vote.', '3task-polls'));
        }

        /**
         * Lets add-ons reject a vote before anything is stored, for example when a
         * required extra field is missing. Return a WP_Error to stop the vote.
         *
         * @param null|WP_Error $check      Null to continue.
         * @param TPoll_Poll    $poll       Poll.
         * @param array         $answer_ids Submitted answer IDs.
         * @param int           $value      Submitted value.
         */
        $check = apply_filters('tpoll_pre_vote', null, $poll, $answer_ids, $value);
        if (is_wp_error($check)) {
            return $check;
        }

        $ip = self::get_ip();
        $ip_hash = self::hash_ip($ip, $poll_id);
        $user_agent_hash = self::hash_user_agent($poll_id);
        $user_id = get_current_user_id();

        if (self::is_rate_limited(self::hash_ip($ip))) {
            return new WP_Error('rate_limited', __('Too many votes from your network. Please try again later.', '3task-polls'));
        }

        $already_voted = self::has_voted($poll_id, $poll, $context);
        $allow_revote = (bool) $poll->get_setting('allow_revote', false);

        // Check if already voted
        if ($already_voted && !$allow_revote) {
            return new WP_Error('already_voted', __('You have already voted.', '3task-polls'));
        }

        // Ensure answer_ids is array
        if (!is_array($answer_ids)) {
            $answer_ids = array($answer_ids);
        }

        $answer_ids = array_values(array_unique(array_filter(array_map('absint', $answer_ids))));

        if (empty($answer_ids)) {
            return new WP_Error('invalid_answer', __('Please select a valid answer.', '3task-polls'));
        }

        // Rating: 1-5, Voting: -1 or 1, choice polls: always 1
        $raw_value = intval($value);
        if ($poll->type === 'rating') {
            $value = max(1, min(5, $raw_value));
        } elseif ($poll->type === 'voting') {
            $value = ($raw_value >= 0) ? 1 : -1;
        } else {
            $value = 1;
        }

        /**
         * Filters the stored vote value, for types with their own scale.
         * Return a WP_Error to reject the value.
         *
         * @param int        $value     Value after the built-in rules.
         * @param TPoll_Poll $poll      Poll.
         * @param int        $raw_value Value as submitted.
         */
        $value = apply_filters('tpoll_vote_value', $value, $poll, $raw_value);
        if (is_wp_error($value)) {
            return $value;
        }
        $value = (int) $value;

        if (in_array($poll->type, TPoll_Poll::multi_answer_types(), true)) {
            $max_choices = (int) $poll->get_setting('max_choices', 0);
            if ($max_choices > 0 && count($answer_ids) > $max_choices) {
                return new WP_Error(
                    'too_many_choices',
                    sprintf(
                        /* translators: %d: maximum number of selections allowed */
                        __('You can select a maximum of %d answers.', '3task-polls'),
                        $max_choices
                    )
                );
            }
        } elseif (count($answer_ids) > 1) {
            $answer_ids = array($answer_ids[0]);
        }

        $votes_table = $wpdb->prefix . 'tpoll_votes';
        $answers_table = $wpdb->prefix . 'tpoll_answers';

        // Validate answer ownership before writing.
        foreach ($answer_ids as $answer_id) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, table name is safe.
            $answer_exists = (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $answers_table WHERE id = %d AND poll_id = %d",
                $answer_id,
                $poll_id
            ));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if (!$answer_exists) {
                return new WP_Error('invalid_answer', __('One or more selected answers are invalid.', '3task-polls'));
            }
        }

        $use_transaction = false;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control.
        $transaction_started = $wpdb->query('START TRANSACTION');
        if ($transaction_started !== false) {
            $use_transaction = true;
        }

        // Reaching this point with an existing vote means changing the vote is allowed.
        if ($already_voted) {
            $remove_result = self::remove_existing_vote($poll_id, $restriction, $user_id, $context);

            if (is_wp_error($remove_result) || $remove_result === false) {
                if ($use_transaction) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control.
                    $wpdb->query('ROLLBACK');
                }
                return new WP_Error('vote_error', __('Error updating your existing vote.', '3task-polls'));
            }
        }

        // One ballot per submission: counts voters correctly for multiple choice.
        $ballot = preg_match('/^[A-Za-z0-9-]{1,32}$/', (string) $ballot) ? (string) $ballot : wp_generate_password(32, false, false);

        foreach ($answer_ids as $answer_id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table.
            $inserted = $wpdb->insert(
                $votes_table,
                array(
                    'poll_id' => $poll_id,
                    'answer_id' => $answer_id,
                    'user_id' => $user_id ?: null,
                    'ip_hash' => $ip_hash,
                    'user_agent_hash' => $user_agent_hash,
                    'ballot' => $ballot,
                    'context_id' => $context,
                    'vote_value' => $value,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s')
            );

            if ($inserted === false) {
                if ($use_transaction) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control.
                    $wpdb->query('ROLLBACK');
                }
                return new WP_Error('vote_error', __('Error recording vote.', '3task-polls'));
            }
        }

        if ($use_transaction) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control.
            $committed = $wpdb->query('COMMIT');
            if ($committed === false) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control.
                $wpdb->query('ROLLBACK');
                return new WP_Error('vote_error', __('Error recording vote.', '3task-polls'));
            }
        }

        // Two parallel requests can both pass has_voted(). The older ballot wins, the newer one is removed.
        if (!$allow_revote && self::lost_race($poll_id, $restriction, $user_id, $ballot, $context)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
            $wpdb->delete($votes_table, array('ballot' => $ballot, 'poll_id' => $poll_id), array('%s', '%d'));
            return new WP_Error('already_voted', __('You have already voted.', '3task-polls'));
        }

        // The cookie is only part of the "cookie" restriction mode.
        if ($restriction === 'cookie') {
            self::set_voted_cookie($poll_id, $context);
        }

        /**
         * Fires after a vote was stored.
         *
         * @param int    $poll_id    Poll ID.
         * @param int[]  $answer_ids Answer IDs.
         * @param int    $value      Stored value.
         * @param string $ballot     ID of this vote submission.
         * @param int    $context    Post ID for per-post polls, else 0.
         */
        do_action('tpoll_vote_cast', $poll_id, $answer_ids, $value, $ballot, $context);

        return true;
    }

    /**
     * SQL condition that matches the current visitor in a poll (and context).
     * IP hashes are checked in the per-poll form and in the form used up to 1.0.5,
     * so a vote from before the update is still recognised.
     *
     * @param int    $poll_id     Poll ID.
     * @param string $restriction ip, cookie or user.
     * @param int    $user_id     Current user.
     * @param int    $context     Context (post ID or 0).
     * @param bool   $with_agent  Also match the browser hash (cookie mode).
     * @return string|null Prepared condition or null when there is nothing to match.
     */
    private static function voter_condition($poll_id, $restriction, $user_id, $context, $with_agent = false) {
        global $wpdb;

        if ($restriction === 'user') {
            if (!$user_id) {
                return null;
            }
            return $wpdb->prepare('poll_id = %d AND context_id = %d AND user_id = %d', $poll_id, $context, $user_id);
        }

        $ip = self::get_ip();
        $where = $wpdb->prepare(
            'poll_id = %d AND context_id = %d AND ip_hash IN (%s, %s)',
            $poll_id,
            $context,
            self::hash_ip($ip, $poll_id),
            self::hash_ip($ip)
        );

        if ($with_agent) {
            $where .= $wpdb->prepare(
                ' AND user_agent_hash IN (%s, %s)',
                self::hash_user_agent($poll_id),
                self::hash_user_agent()
            );
        }

        return $where;
    }

    /**
     * Check whether an older ballot of the same voter exists.
     */
    private static function lost_race($poll_id, $restriction, $user_id, $ballot, $context) {
        global $wpdb;

        if (!in_array($restriction, array('ip', 'user'), true)) {
            return false;
        }

        $where = self::voter_condition($poll_id, $restriction, $user_id, $context);
        if (null === $where) {
            return false;
        }

        $votes_table = $wpdb->prefix . 'tpoll_votes';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, $where is prepared in voter_condition().
        $own_min = (int) $wpdb->get_var($wpdb->prepare("SELECT MIN(id) FROM $votes_table WHERE ballot = %s AND poll_id = %d", $ballot, $poll_id));
        $older = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $votes_table WHERE $where AND ballot <> %s AND id < %d", $ballot, $own_min));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        return $older > 0;
    }

    /**
     * Remove previous vote(s) for a voter.
     *
     * @return bool|WP_Error
     */
    private static function remove_existing_vote($poll_id, $restriction, $user_id, $context) {
        global $wpdb;

        if ($restriction === 'user' && !$user_id) {
            return new WP_Error('login_required', __('Please log in to vote.', '3task-polls'));
        }

        $where = self::voter_condition($poll_id, $restriction, $user_id, $context, $restriction === 'cookie');
        if (null === $where) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, $where is prepared in voter_condition().
        $deleted = $wpdb->query("DELETE FROM {$wpdb->prefix}tpoll_votes WHERE $where");

        return $deleted !== false;
    }

    /**
     * Check if the current visitor has voted
     *
     * @param int             $poll_id Poll ID.
     * @param TPoll_Poll|null $poll    Already loaded poll, saves a query.
     * @param int             $context Post ID for per-post polls, else 0.
     */
    public static function has_voted($poll_id, $poll = null, $context = 0) {
        global $wpdb;

        if (!$poll) {
            $poll = TPoll_Poll::get($poll_id);
        }
        if (!$poll) {
            return false;
        }

        $restriction = $poll->get_setting('vote_restriction', 'ip');

        if ($restriction === 'cookie') {
            $voted = self::has_voted_cookie($poll_id, absint($context));
        } else {
            $where = self::voter_condition($poll_id, $restriction, get_current_user_id(), absint($context));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, $where is prepared in voter_condition().
            $voted = null === $where ? false : (bool) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tpoll_votes WHERE $where");
        }

        /**
         * Filters whether the current visitor has voted, for types without votes of their own.
         *
         * @param bool       $voted   Result for this poll.
         * @param TPoll_Poll $poll    Poll.
         * @param int        $context Context (post ID or 0).
         */
        return (bool) apply_filters('tpoll_has_voted', $voted, $poll, absint($context));
    }

    /**
     * Remove all votes of one submission, for example when a later step of a questionnaire fails.
     *
     * @param string $ballot Ballot ID.
     * @return int Number of removed rows.
     */
    public static function delete_ballot($ballot) {
        global $wpdb;

        if ((string) $ballot === '') {
            return 0;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
        return (int) $wpdb->delete($wpdb->prefix . 'tpoll_votes', array('ballot' => (string) $ballot), array('%s'));
    }

    /**
     * Get results for a poll
     *
     * @param int             $poll_id Poll ID.
     * @param TPoll_Poll|null $poll    Already loaded poll, saves a query.
     * @param int|null        $context Post ID to count one post only, null for all votes.
     */
    public static function get_results($poll_id, $poll = null, $context = null) {
        global $wpdb;

        if (!$poll) {
            $poll = TPoll_Poll::get($poll_id);
        }
        if (!$poll) {
            return null;
        }

        $answers_table = $wpdb->prefix . 'tpoll_answers';
        $votes_table = $wpdb->prefix . 'tpoll_votes';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, table names are safe.
        $answers = $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.answer_text, a.answer_image, a.sort_order,
                    COALESCE(SUM(v.vote_value), 0) AS total_value,
                    COUNT(v.id) AS vote_count,
                    COALESCE(SUM(CASE WHEN v.vote_value > 0 THEN 1 ELSE 0 END), 0) AS up_count,
                    COALESCE(SUM(CASE WHEN v.vote_value < 0 THEN 1 ELSE 0 END), 0) AS down_count
             FROM $answers_table a
             LEFT JOIN $votes_table v ON a.id = v.answer_id AND v.poll_id = a.poll_id AND (%d = -1 OR v.context_id = %d)
             WHERE a.poll_id = %d
             GROUP BY a.id, a.answer_text, a.answer_image, a.sort_order
             ORDER BY a.sort_order ASC",
            null === $context ? -1 : absint($context),
            null === $context ? -1 : absint($context),
            $poll_id
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $total_votes = 0;
        $total_value = 0;
        $up = 0;
        $down = 0;

        foreach ($answers as $answer) {
            $total_votes += (int) $answer->vote_count;
            $total_value += (int) $answer->total_value;
            $up += (int) $answer->up_count;
            $down += (int) $answer->down_count;
        }

        $total_voters = $poll->get_total_voters($context);

        // Single choice: shares of all votes, rounded so they add up to 100.
        // Multiple choice: share of voters who picked the answer, so the sum can exceed 100.
        $counts = array();
        foreach ($answers as $answer) {
            $counts[] = (int) $answer->vote_count;
        }
        if ($poll->type === 'multiple') {
            $percentages = array();
            foreach ($counts as $count) {
                $percentages[] = $total_voters > 0 ? round(($count / $total_voters) * 100, 1) : 0;
            }
        } else {
            $percentages = self::shares_summing_to_100($counts);
        }

        $results = array(
            'poll_id' => (int) $poll_id,
            'poll_type' => $poll->type,
            'context' => null === $context ? null : absint($context),
            'total_votes' => $total_votes,
            'total_voters' => $total_voters,
            'percent_base' => $poll->type === 'multiple' ? 'voters' : 'votes',
            'answers' => array()
        );

        foreach ($answers as $i => $answer) {
            $results['answers'][] = array(
                'id' => (int) $answer->id,
                'text' => $answer->answer_text,
                'image' => $answer->answer_image,
                'votes' => (int) $answer->vote_count,
                'value' => (int) $answer->total_value,
                'percentage' => $percentages[$i]
            );
        }

        $results['average_value'] = $total_votes > 0 ? round($total_value / $total_votes, 1) : 0;

        if ($poll->type === 'rating') {
            $results['average_rating'] = $results['average_value'];
        }

        if ($poll->type === 'voting') {
            $results['up'] = $up;
            $results['down'] = $down;
            $results['net_score'] = $up - $down;
        }

        /**
         * Filters the results of a poll.
         *
         * @param array      $results Results.
         * @param TPoll_Poll $poll    Poll.
         */
        return apply_filters('tpoll_results', $results, $poll);
    }

    /**
     * Percentages with one decimal that add up to exactly 100 (largest remainder).
     *
     * @param int[] $counts Vote counts.
     * @return array<int,int|float>
     */
    private static function shares_summing_to_100($counts) {
        $total = array_sum($counts);
        if ($total <= 0) {
            return array_fill(0, count($counts), 0);
        }

        $units = array();
        $remainders = array();
        foreach ($counts as $i => $count) {
            $exact = $count / $total * 1000;
            $units[$i] = (int) floor($exact);
            $remainders[$i] = $exact - $units[$i];
        }

        $missing = 1000 - array_sum($units);
        arsort($remainders);
        foreach (array_keys($remainders) as $i) {
            if ($missing <= 0) {
                break;
            }
            $units[$i]++;
            $missing--;
        }

        $shares = array();
        foreach ($counts as $i => $count) {
            $shares[$i] = $units[$i] / 10;
        }
        return $shares;
    }

    /**
     * Delete votes for a poll
     */
    public static function delete_votes($poll_id) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
        return $wpdb->delete(
            $wpdb->prefix . 'tpoll_votes',
            array('poll_id' => $poll_id),
            array('%d')
        );
    }

    /**
     * Get the visitor's IP address.
     *
     * Only REMOTE_ADDR is trusted. Headers like X-Forwarded-For can be set by any visitor.
     * Sites behind a trusted reverse proxy can return the real client IP via the
     * "tpoll_client_ip" filter.
     */
    private static function get_ip() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

        /**
         * Filters the IP address used for vote restriction and rate limiting.
         *
         * @param string $ip Value of REMOTE_ADDR.
         */
        $ip = (string) apply_filters('tpoll_client_ip', $ip);

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    /**
     * Hash IP address. The plain IP is never stored.
     *
     * Since 1.0.6 the poll ID is part of the hash, so hashes of different polls
     * cannot be linked. Without a poll ID the form of 1.0.5 and older is returned
     * (used to recognise older votes and for the rate limit).
     *
     * @param string $ip      IP address.
     * @param int    $poll_id Poll ID, 0 for the legacy form.
     */
    private static function hash_ip($ip, $poll_id = 0) {
        $salt = wp_salt('auth');
        if ($poll_id) {
            return hash('sha256', 'tpoll|' . absint($poll_id) . '|' . $ip . '|' . $salt);
        }
        return hash('sha256', $ip . $salt);
    }

    /**
     * Hash user agent (same rules as hash_ip()).
     *
     * @param int $poll_id Poll ID, 0 for the legacy form.
     */
    private static function hash_user_agent($poll_id = 0) {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $salt = wp_salt('auth');
        if ($poll_id) {
            return hash('sha256', 'tpoll|' . absint($poll_id) . '|' . $user_agent . '|' . $salt);
        }
        return hash('sha256', $user_agent . $salt);
    }

    /**
     * Limit vote requests per network.
     */
    private static function is_rate_limited($ip_hash) {
        /**
         * Filters how many vote requests one IP may send within the window.
         *
         * @param int $limit Default 20.
         */
        $limit = (int) apply_filters('tpoll_rate_limit', 20);
        if ($limit <= 0) {
            return false;
        }

        $key = 'tpoll_rl_' . substr($ip_hash, 0, 32);
        $count = (int) get_transient($key);

        if ($count >= $limit) {
            return true;
        }

        set_transient($key, $count + 1, 10 * MINUTE_IN_SECONDS);
        return false;
    }

    /**
     * Set voted cookie (cookie restriction mode only)
     */
    private static function set_voted_cookie($poll_id, $context = 0) {
        $cookie_name = self::cookie_name($poll_id, $context);
        $expires = time() + (365 * DAY_IN_SECONDS);

        setcookie($cookie_name, '1', $expires, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    }

    /**
     * Check voted cookie
     */
    private static function has_voted_cookie($poll_id, $context = 0) {
        return isset($_COOKIE[self::cookie_name($poll_id, $context)]);
    }

    /**
     * Cookie name: tpoll_voted_<poll>, with _<post> for per-post polls.
     */
    private static function cookie_name($poll_id, $context = 0) {
        return 'tpoll_voted_' . absint($poll_id) . ($context ? '_' . absint($context) : '');
    }

    /**
     * Remove voter fingerprints (IP and user agent hashes) of polls that ended
     * more than $days days ago. Vote counts stay.
     *
     * @param int $days Retention in days.
     * @return int Number of updated rows.
     */
    public static function forget_fingerprints($days) {
        global $wpdb;

        $days = absint($days);
        if ($days < 1) {
            return 0;
        }

        $votes_table = $wpdb->prefix . 'tpoll_votes';
        $polls_table = $wpdb->prefix . 'tpoll_polls';
        $cutoff = wp_date('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom tables, table names are safe.
        $poll_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $polls_table
             WHERE (end_date IS NOT NULL AND end_date < %s)
                OR (status = 'closed' AND updated_at < %s)",
            $cutoff,
            $cutoff
        ));

        $updated = 0;
        foreach ($poll_ids as $poll_id) {
            $updated += (int) $wpdb->query($wpdb->prepare(
                "UPDATE $votes_table SET ip_hash = '', user_agent_hash = NULL WHERE poll_id = %d AND ip_hash <> ''",
                $poll_id
            ));
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        return $updated;
    }

    /**
     * Get vote log for a poll (admin use)
     */
    public static function get_vote_log($poll_id, $args = array()) {
        global $wpdb;

        $defaults = array(
            'per_page' => 50,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);
        $offset = ($args['page'] - 1) * $args['per_page'];

        $votes_table = $wpdb->prefix . 'tpoll_votes';
        $answers_table = $wpdb->prefix . 'tpoll_answers';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, table names are safe.
        $tpoll_votes_result = $wpdb->get_results($wpdb->prepare(
            "SELECT v.*, a.answer_text
             FROM $votes_table v
             LEFT JOIN $answers_table a ON v.answer_id = a.id
             WHERE v.poll_id = %d
             ORDER BY v.created_at DESC
             LIMIT %d OFFSET %d",
            $poll_id,
            $args['per_page'],
            $offset
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $tpoll_votes_result;
    }
}

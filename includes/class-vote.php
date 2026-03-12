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
     */
    public static function cast($poll_id, $answer_ids, $value = 1) {
        global $wpdb;

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

        $ip_hash = self::hash_ip(self::get_ip());
        $user_agent_hash = self::hash_user_agent();
        $user_id = get_current_user_id();
        $already_voted = self::has_voted($poll_id);
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

        // For single choice, only allow one answer
        if ($poll->type === 'single' && count($answer_ids) > 1) {
            $answer_ids = array($answer_ids[0]);
        }

        // For rating/voting types, use the value
        if (in_array($poll->type, array('rating', 'voting'))) {
            $value = intval($value);
            // Rating: 1-5, Voting: -1 or 1
            if ($poll->type === 'rating') {
                $value = max(1, min(5, $value));
            } else {
                $value = ($value >= 0) ? 1 : -1;
            }
        }

        if ($poll->type === 'multiple') {
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

        if ($already_voted && $allow_revote) {
            $remove_result = self::remove_existing_vote(
                $poll_id,
                $restriction,
                $user_id,
                $ip_hash,
                $user_agent_hash
            );

            if (is_wp_error($remove_result) || $remove_result === false) {
                if ($use_transaction) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control.
                    $wpdb->query('ROLLBACK');
                }
                return new WP_Error('vote_error', __('Error updating your existing vote.', '3task-polls'));
            }
        }

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
                    'vote_value' => $value,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%s', '%s', '%d', '%s')
            );

            if ($inserted === false) {
                if ($use_transaction) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Transaction control.
                    $wpdb->query('ROLLBACK');
                }
                return new WP_Error('vote_error', __('Error recording vote.', '3task-polls'));
            }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, table name is safe.
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE $answers_table SET votes = votes + %d WHERE id = %d",
                $value,
                $answer_id
            ));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ($updated === false) {
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

        // Set voted cookie
        self::set_voted_cookie($poll_id);

        // Fire action
        do_action('tpoll_vote_cast', $poll_id, $answer_ids, $value);

        return true;
    }

    /**
     * Remove previous vote(s) for a voter and rollback answer counters.
     *
     * @return bool|WP_Error
     */
    private static function remove_existing_vote($poll_id, $restriction, $user_id, $ip_hash, $user_agent_hash) {
        global $wpdb;

        $votes_table = $wpdb->prefix . 'tpoll_votes';
        $answers_table = $wpdb->prefix . 'tpoll_answers';

        switch ($restriction) {
            case 'user':
                if (!$user_id) {
                    return new WP_Error('login_required', __('Please log in to vote.', '3task-polls'));
                }
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, table names are safe.
                $answer_totals = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT answer_id, SUM(vote_value) AS vote_total
                         FROM $votes_table
                         WHERE poll_id = %d AND user_id = %d
                         GROUP BY answer_id",
                        $poll_id,
                        $user_id
                    )
                );
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Delete on custom table as part of vote replacement.
                $deleted = $wpdb->delete(
                    $votes_table,
                    array(
                        'poll_id' => $poll_id,
                        'user_id' => $user_id,
                    ),
                    array('%d', '%d')
                );
                break;
            case 'cookie':
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, table names are safe.
                $answer_totals = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT answer_id, SUM(vote_value) AS vote_total
                         FROM $votes_table
                         WHERE poll_id = %d AND ip_hash = %s AND user_agent_hash = %s
                         GROUP BY answer_id",
                        $poll_id,
                        $ip_hash,
                        $user_agent_hash
                    )
                );
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Delete on custom table as part of vote replacement.
                $deleted = $wpdb->delete(
                    $votes_table,
                    array(
                        'poll_id' => $poll_id,
                        'ip_hash' => $ip_hash,
                        'user_agent_hash' => $user_agent_hash,
                    ),
                    array('%d', '%s', '%s')
                );
                break;
            case 'ip':
            default:
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, table names are safe.
                $answer_totals = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT answer_id, SUM(vote_value) AS vote_total
                         FROM $votes_table
                         WHERE poll_id = %d AND ip_hash = %s
                         GROUP BY answer_id",
                        $poll_id,
                        $ip_hash
                    )
                );
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Delete on custom table as part of vote replacement.
                $deleted = $wpdb->delete(
                    $votes_table,
                    array(
                        'poll_id' => $poll_id,
                        'ip_hash' => $ip_hash,
                    ),
                    array('%d', '%s')
                );
                break;
        }

        if (!is_array($answer_totals)) {
            $answer_totals = array();
        }

        foreach ($answer_totals as $answer_total) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, table name is safe.
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE $answers_table SET votes = votes - %d WHERE id = %d",
                (int) $answer_total->vote_total,
                (int) $answer_total->answer_id
            ));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ($updated === false) {
                return false;
            }
        }

        return $deleted !== false;
    }

    /**
     * Check if user has voted
     */
    public static function has_voted($poll_id) {
        global $wpdb;

        // Get poll settings
        $poll = TPoll_Poll::get($poll_id);
        if (!$poll) {
            return false;
        }

        $restriction = $poll->get_setting('vote_restriction', 'ip');

        switch ($restriction) {
            case 'user':
                // Check by user ID
                $user_id = get_current_user_id();
                if (!$user_id) {
                    return false;
                }
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
                return (bool) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}tpoll_votes WHERE poll_id = %d AND user_id = %d",
                    $poll_id,
                    $user_id
                ));

            case 'cookie':
                // Check by cookie
                return self::has_voted_cookie($poll_id);

            case 'ip':
            default:
                // Check by IP hash (DSGVO compliant)
                $ip_hash = self::hash_ip(self::get_ip());
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
                return (bool) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}tpoll_votes WHERE poll_id = %d AND ip_hash = %s",
                    $poll_id,
                    $ip_hash
                ));
        }
    }

    /**
     * Get results for a poll
     */
    public static function get_results($poll_id) {
        global $wpdb;

        $poll = TPoll_Poll::get($poll_id);
        if (!$poll) {
            return null;
        }

        $answers_table = $wpdb->prefix . 'tpoll_answers';
        $votes_table = $wpdb->prefix . 'tpoll_votes';

        // Get answer results
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, table names are safe.
        $answers = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*,
                    COALESCE(SUM(v.vote_value), 0) as total_value,
                    COUNT(v.id) as vote_count
             FROM $answers_table a
             LEFT JOIN $votes_table v ON a.id = v.answer_id
             WHERE a.poll_id = %d
             GROUP BY a.id
             ORDER BY a.sort_order ASC",
            $poll_id
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        // Calculate totals and percentages
        $total_votes = 0;
        $total_value = 0;

        foreach ($answers as $answer) {
            $total_votes += $answer->vote_count;
            $total_value += $answer->total_value;
        }

        $results = array(
            'poll_id' => $poll_id,
            'poll_type' => $poll->type,
            'total_votes' => $total_votes,
            'total_voters' => $poll->get_total_voters(),
            'answers' => array()
        );

        foreach ($answers as $answer) {
            $percentage = $total_votes > 0 ? round(($answer->vote_count / $total_votes) * 100, 1) : 0;

            $results['answers'][] = array(
                'id' => (int) $answer->id,
                'text' => $answer->answer_text,
                'image' => $answer->answer_image,
                'votes' => (int) $answer->vote_count,
                'value' => (int) $answer->total_value,
                'percentage' => $percentage
            );
        }

        // For rating type, calculate average
        if ($poll->type === 'rating') {
            $results['average_rating'] = $total_votes > 0 ? round($total_value / $total_votes, 1) : 0;
        }

        // For voting type, calculate net score
        if ($poll->type === 'voting') {
            $results['net_score'] = $total_value;
        }

        return $results;
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
     * Get IP address
     */
    private static function get_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        // Handle comma-separated IPs
        if (strpos($ip, ',') !== false) {
            $ips = explode(',', $ip);
            $ip = trim($ips[0]);
        }

        return $ip;
    }

    /**
     * Hash IP address (DSGVO compliant)
     */
    private static function hash_ip($ip) {
        $salt = wp_salt('auth');
        return hash('sha256', $ip . $salt);
    }

    /**
     * Hash user agent
     */
    private static function hash_user_agent() {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $salt = wp_salt('auth');
        return hash('sha256', $user_agent . $salt);
    }

    /**
     * Set voted cookie
     */
    private static function set_voted_cookie($poll_id) {
        $cookie_name = 'tpoll_voted_' . $poll_id;
        $cookie_value = time();
        $expires = time() + (365 * DAY_IN_SECONDS);

        setcookie($cookie_name, $cookie_value, $expires, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    }

    /**
     * Check voted cookie
     */
    private static function has_voted_cookie($poll_id) {
        $cookie_name = 'tpoll_voted_' . $poll_id;
        return isset($_COOKIE[$cookie_name]);
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

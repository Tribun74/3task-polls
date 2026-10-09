<?php
/**
 * TPoll Public
 *
 * @package TPoll
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPoll_Public {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'register_style'), 5);
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('wp_ajax_tpoll_vote', array($this, 'ajax_vote'));
        add_action('wp_ajax_nopriv_tpoll_vote', array($this, 'ajax_vote'));
        add_action('wp_ajax_tpoll_get_results', array($this, 'ajax_get_results'));
        add_action('wp_ajax_nopriv_tpoll_get_results', array($this, 'ajax_get_results'));
        add_action('wp_ajax_tpoll_state', array($this, 'ajax_state'));
        add_action('wp_ajax_nopriv_tpoll_state', array($this, 'ajax_state'));
    }

    /**
     * Register assets
     */
    public function register_style() {
        wp_register_style(
            'tpoll-frontend',
            TPOLL_PLUGIN_URL . 'assets/css/tpoll-frontend.css',
            array(),
            TPOLL_VERSION
        );
    }

    /**
     * Register script
     */
    public function register_assets() {

        wp_register_script(
            'tpoll-frontend',
            TPOLL_PLUGIN_URL . 'public/js/tpoll-frontend.js',
            array(),
            TPOLL_VERSION,
            true
        );

        wp_localize_script('tpoll-frontend', 'tpollData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'strings' => array(
                'error' => __('An error occurred. Please try again.', '3task-polls'),
                'voted' => __('Thank you for voting!', '3task-polls'),
                'alreadyVoted' => __('You have already voted.', '3task-polls'),
                'selectAnswer' => __('Please select an answer.', '3task-polls'),
                'clickToRate' => __('Click to rate', '3task-polls'),
                /* translators: %s: rating value */
                'yourRating' => __('Your rating: %s', '3task-polls')
            )
        ));
    }

    /**
     * Reject requests that a browser sent from another site.
     */
    private function is_foreign_origin() {
        $source = '';
        if (!empty($_SERVER['HTTP_ORIGIN'])) {
            $source = esc_url_raw(wp_unslash($_SERVER['HTTP_ORIGIN']));
        } elseif (!empty($_SERVER['HTTP_REFERER'])) {
            $source = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
        }

        if ($source === '') {
            return false;
        }

        $host = wp_parse_url($source, PHP_URL_HOST);
        $allowed = array_filter(array(
            wp_parse_url(home_url(), PHP_URL_HOST),
            wp_parse_url(site_url(), PHP_URL_HOST),
        ));

        return !in_array($host, $allowed, true);
    }

    /**
     * Context (post ID) for polls that count per post. Anything else counts as 0.
     *
     * @param TPoll_Poll $poll Poll.
     * @param mixed      $raw  Submitted context.
     * @return int
     */
    public static function context_for($poll, $raw) {
        $context = absint($raw);
        if (!$context || !$poll || !$poll->get_setting('per_post', false)) {
            return 0;
        }
        return get_post_status($context) === 'publish' ? $context : 0;
    }

    /**
     * AJAX: Cast vote
     */
    public function ajax_vote() {
        if (!check_ajax_referer('tpoll_vote', 'nonce', false) || $this->is_foreign_origin()) {
            wp_send_json_error(array('message' => __('Security check failed. Please reload the page and try again.', '3task-polls')));
        }

        // Hidden field that people never see. Bots that fill every field are turned away.
        if (!empty($_POST['tpoll_hp'])) {
            wp_send_json_error(array('message' => __('Your vote could not be counted.', '3task-polls')));
        }

        $poll_id = isset($_POST['poll_id']) ? absint($_POST['poll_id']) : 0;
        $answers = isset($_POST['answers']) ? array_map('absint', (array) wp_unslash($_POST['answers'])) : array();
        $value = isset($_POST['value']) ? intval($_POST['value']) : 1;

        if (!$poll_id) {
            wp_send_json_error(array('message' => __('Invalid poll.', '3task-polls')));
        }

        if (isset($_POST['answer']) && !empty($_POST['answer'])) {
            $answers = array(absint($_POST['answer']));
        }

        if (empty($answers)) {
            wp_send_json_error(array('message' => __('Please select an answer.', '3task-polls')));
        }

        $poll = TPoll_Poll::get($poll_id);
        $context = self::context_for($poll, isset($_POST['context']) ? wp_unslash($_POST['context']) : 0); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint in context_for().

        $result = TPoll_Vote::cast($poll_id, $answers, $value, $context);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        $opt = TPoll_Shortcode::display_options($poll);
        /**
         * Filters the message shown after a successful vote.
         *
         * @param string     $message Message.
         * @param TPoll_Poll $poll    Poll.
         */
        $data = array('message' => (string) apply_filters('tpoll_vote_message', __('Thank you for voting!', '3task-polls'), $poll));

        if (TPoll_Shortcode::results_visible($poll, $opt['show_results'], true)) {
            $results = TPoll_Vote::get_results($poll_id, $poll, $context);
            $data['results'] = $this->public_results($results);
            $data['html'] = TPoll_Shortcode::results_html($poll, $results, $opt['show_voters']);
        }

        wp_send_json_success($data);
    }

    /**
     * AJAX: Get results, only when the poll settings allow it for this visitor.
     */
    public function ajax_get_results() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only, visibility is checked below.
        $poll_id = isset($_REQUEST['poll_id']) ? absint($_REQUEST['poll_id']) : 0;
        $poll = $poll_id ? TPoll_Poll::get($poll_id) : null;

        if (!$poll || !$this->is_public($poll)) {
            wp_send_json_error(array('message' => __('Poll not found.', '3task-polls')));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only, absint in context_for().
        $context = self::context_for($poll, isset($_REQUEST['context']) ? wp_unslash($_REQUEST['context']) : 0);
        $opt = TPoll_Shortcode::display_options($poll);
        if (!TPoll_Shortcode::results_visible($poll, $opt['show_results'], TPoll_Vote::has_voted($poll_id, $poll, $context))) {
            wp_send_json_error(array('message' => __('Results are not available.', '3task-polls')));
        }

        $results = TPoll_Vote::get_results($poll_id, $poll, $context);

        wp_send_json_success(array(
            'results' => $this->public_results($results),
            'html' => TPoll_Shortcode::results_html($poll, $results, $opt['show_voters'])
        ));
    }

    /**
     * AJAX: Visitor state for cached pages. Returns whether the visitor has voted,
     * a fresh nonce and the results if they may be shown.
     */
    public function ajax_state() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only, visibility is checked below.
        $raw = isset($_REQUEST['poll_ids']) ? sanitize_text_field(wp_unslash($_REQUEST['poll_ids'])) : '';
        // Items are "id" or "id:context" (per-post polls).
        $items = array_slice(array_unique(array_filter(explode(',', $raw))), 0, 20);

        $state = array();
        foreach ($items as $entry) {
            $parts = explode(':', $entry);
            $poll_id = absint($parts[0]);
            $poll = $poll_id ? TPoll_Poll::get($poll_id) : null;
            if (!$poll || !$this->is_public($poll)) {
                continue;
            }
            $context = self::context_for($poll, isset($parts[1]) ? $parts[1] : 0);
            $key = $context ? $poll_id . ':' . $context : (string) $poll_id;

            $has_voted = TPoll_Vote::has_voted($poll_id, $poll, $context);
            $opt = TPoll_Shortcode::display_options($poll);
            $item = array(
                'hasVoted' => $has_voted,
                'canRevote' => (bool) $poll->get_setting('allow_revote', false),
                'nonce' => wp_create_nonce('tpoll_vote'),
            );

            if (TPoll_Shortcode::results_visible($poll, $opt['show_results'], $has_voted)) {
                $results = TPoll_Vote::get_results($poll_id, $poll, $context);
                $item['results'] = $this->public_results($results);
                $item['html'] = TPoll_Shortcode::results_html($poll, $results, $opt['show_voters']);
            }

            $state[$key] = $item;
        }

        nocache_headers();
        wp_send_json_success($state);
    }

    /**
     * Drafts are only visible to poll editors.
     */
    private function is_public($poll) {
        if (in_array($poll->status, array('published', 'closed', 'scheduled'), true)) {
            return true;
        }
        return current_user_can('edit_tpoll');
    }

    /**
     * Results array without internal fields.
     */
    private function public_results($results) {
        if (!$results) {
            return null;
        }
        $keep = array('poll_id', 'poll_type', 'context', 'total_votes', 'total_voters', 'percent_base', 'answers', 'average_rating', 'up', 'down', 'net_score');
        return array_intersect_key($results, array_flip($keep));
    }
}

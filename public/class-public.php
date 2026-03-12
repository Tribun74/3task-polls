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
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('wp_ajax_tpoll_vote', array($this, 'ajax_vote'));
        add_action('wp_ajax_nopriv_tpoll_vote', array($this, 'ajax_vote'));
        add_action('wp_ajax_tpoll_get_results', array($this, 'ajax_get_results'));
        add_action('wp_ajax_nopriv_tpoll_get_results', array($this, 'ajax_get_results'));
    }

    /**
     * Register assets
     */
    public function register_assets() {
        // Register styles
        wp_register_style(
            'tpoll-frontend',
            TPOLL_PLUGIN_URL . 'assets/css/tpoll-frontend.css',
            array(),
            TPOLL_VERSION
        );

        // Register scripts
        wp_register_script(
            'tpoll-frontend',
            TPOLL_PLUGIN_URL . 'public/js/tpoll-frontend.js',
            array(),
            TPOLL_VERSION,
            true
        );

        // Localize script
        wp_localize_script('tpoll-frontend', 'tpollData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tpoll_vote'),
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
     * AJAX: Cast vote
     */
    public function ajax_vote() {
        // Verify nonce
        if (!check_ajax_referer('tpoll_vote', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', '3task-polls')));
        }

        $poll_id = isset($_POST['poll_id']) ? absint($_POST['poll_id']) : 0;
        $answers = isset($_POST['answers']) ? array_map('absint', (array) $_POST['answers']) : array();
        $value = isset($_POST['value']) ? intval($_POST['value']) : 1;

        if (!$poll_id) {
            wp_send_json_error(array('message' => __('Invalid poll.', '3task-polls')));
        }

        // Handle single answer
        if (isset($_POST['answer']) && !empty($_POST['answer'])) {
            $answers = array(absint($_POST['answer']));
        }

        if (empty($answers)) {
            wp_send_json_error(array('message' => __('Please select an answer.', '3task-polls')));
        }

        // Cast vote
        $result = TPoll_Vote::cast($poll_id, $answers, $value);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Get updated results
        $results = TPoll_Vote::get_results($poll_id);

        wp_send_json_success(array(
            'message' => __('Thank you for voting!', '3task-polls'),
            'results' => $results,
            'html' => $this->render_results_html($poll_id, $results)
        ));
    }

    /**
     * AJAX: Get results
     */
    public function ajax_get_results() {
        if (!check_ajax_referer('tpoll_vote', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', '3task-polls')));
        }

        $poll_id = isset($_GET['poll_id']) ? absint($_GET['poll_id']) : 0;

        if (!$poll_id) {
            wp_send_json_error(array('message' => __('Invalid poll.', '3task-polls')));
        }

        $results = TPoll_Vote::get_results($poll_id);

        if (!$results) {
            wp_send_json_error(array('message' => __('Poll not found.', '3task-polls')));
        }

        wp_send_json_success(array(
            'results' => $results,
            'html' => $this->render_results_html($poll_id, $results)
        ));
    }

    /**
     * Render results HTML
     */
    private function render_results_html($poll_id, $results) {
        $poll = TPoll_Poll::get($poll_id);
        $settings = get_option('tpoll_settings', array());
        $show_voters = $poll ? $poll->get_setting('show_voters_count', $settings['show_voters_count'] ?? true) : true;

        ob_start();
        ?>
        <div class="tpoll-results">
            <?php foreach ($results['answers'] as $answer) : ?>
                <div class="tpoll-result-item">
                    <div class="tpoll-result-text">
                        <span class="tpoll-result-answer"><?php echo esc_html($answer['text']); ?></span>
                        <span class="tpoll-result-percent"><?php echo esc_html($answer['percentage']); ?>%</span>
                    </div>
                    <div class="tpoll-result-bar-wrap">
                        <div class="tpoll-result-bar" style="width: <?php echo esc_attr($answer['percentage']); ?>%"></div>
                    </div>
                    <div class="tpoll-result-votes">
                        <?php
                        printf(
                            /* translators: %d: number of votes */
                            esc_html(_n('%d vote', '%d votes', $answer['votes'], '3task-polls')),
                            absint($answer['votes'])
                        );
                    ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if ($poll && $poll->type === 'rating' && isset($results['average_rating'])) : ?>
                <div class="tpoll-average-rating">
                    <?php
                        printf(
                            /* translators: %s: average rating value */
                            esc_html__('Average rating: %s / 5', '3task-polls'),
                            '<strong>' . esc_html($results['average_rating']) . '</strong>'
                        );
                    ?>
                </div>
            <?php endif; ?>

            <?php if ($show_voters) : ?>
                <div class="tpoll-total-voters">
                    <?php
                        printf(
                            /* translators: %d: number of voters */
                            esc_html(_n('%d voter', '%d voters', $results['total_voters'], '3task-polls')),
                            absint($results['total_voters'])
                        );
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

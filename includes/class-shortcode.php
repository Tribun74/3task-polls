<?php
/**
 * TPoll Shortcode Handler
 *
 * @package TPoll
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPoll_Shortcode {

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('tpoll', array($this, 'render'));
    }

    /**
     * Render shortcode
     */
    public function render($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'show_results' => '', // after_vote, always, never (empty = use poll setting)
            'show_voters' => '', // yes, no (empty = use poll setting)
            'theme' => '', // (empty = use poll setting)
            'dark_mode' => '' // auto, yes, no (empty = use poll setting)
        ), $atts, 'tpoll');

        $poll_id = absint($atts['id']);

        if (!$poll_id) {
            return '';
        }

        $poll = TPoll_Poll::get($poll_id);

        if (!$poll) {
            return '<!-- TPoll: Poll not found -->';
        }

        // Check status
        if ($poll->status === 'draft' && !current_user_can('edit_tpoll')) {
            return '<!-- TPoll: Poll is draft -->';
        }

        // Enqueue assets
        wp_enqueue_style('tpoll-frontend');
        wp_enqueue_script('tpoll-frontend');

        // Get settings
        $global_settings = get_option('tpoll_settings', array());
        $show_results = $atts['show_results'] ?: $poll->get_setting('show_results', $global_settings['show_results'] ?? 'after_vote');
        $show_voters = $atts['show_voters'] !== '' ? ($atts['show_voters'] === 'yes') : $poll->get_setting('show_voters_count', $global_settings['show_voters_count'] ?? true);
        $theme = $atts['theme'] ?: $poll->get_setting('theme', $global_settings['theme'] ?? 'default');
        $dark_mode = $atts['dark_mode'] ?: $poll->get_setting('dark_mode', $global_settings['dark_mode'] ?? 'auto');

        // Check if user has voted
        $has_voted = TPoll_Vote::has_voted($poll_id);

        // Determine what to show
        $show_form = true;
        $show_results_now = false;

        if ($poll->is_closed()) {
            $show_form = false;
            $show_results_now = true;
        } elseif ($has_voted) {
            $show_form = false;
            $show_results_now = ($show_results !== 'never');
        } elseif ($show_results === 'always') {
            $show_results_now = true;
        }

        // Build classes
        $classes = array(
            'tpoll-poll',
            'tpoll-type-' . esc_attr($poll->type),
            'tpoll-theme-' . esc_attr($theme)
        );

        if ($dark_mode === 'yes') {
            $classes[] = 'tpoll-dark';
        } elseif ($dark_mode === 'auto') {
            $classes[] = 'tpoll-dark-auto';
        }

        // Start output buffer
        ob_start();

        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>"
             id="tpoll-<?php echo esc_attr($poll_id); ?>"
             data-poll-id="<?php echo esc_attr($poll_id); ?>"
             data-poll-type="<?php echo esc_attr($poll->type); ?>"
             data-show-results="<?php echo esc_attr($show_results); ?>">

            <?php if (!empty($poll->question)) : ?>
                <div class="tpoll-question">
                    <?php echo esc_html($poll->question); ?>
                </div>
            <?php endif; ?>

            <?php if ($poll->is_closed()) : ?>
                <div class="tpoll-closed">
                    <?php echo esc_html($global_settings['closed_text'] ?? __('This poll is closed.', '3task-polls')); ?>
                </div>
            <?php endif; ?>

            <?php if ($show_form && !$poll->is_closed()) : ?>
                <?php $this->render_poll_form($poll, $global_settings); ?>
            <?php endif; ?>

            <?php if ($has_voted && !$poll->is_closed()) : ?>
                <div class="tpoll-voted">
                    <?php echo esc_html($global_settings['voted_text'] ?? __('You have already voted.', '3task-polls')); ?>
                </div>
            <?php endif; ?>

            <?php if ($show_results_now) : ?>
                <?php $this->render_results($poll, $show_voters); ?>
            <?php endif; ?>

            <div class="tpoll-results-container" style="<?php echo esc_attr( $show_results_now ? '' : 'display:none;' ); ?>">
                <!-- AJAX results will be loaded here -->
            </div>

            <?php if (!$has_voted && $show_results === 'always' && !$poll->is_closed()) : ?>
                <button type="button" class="tpoll-toggle-form tpoll-btn-link">
                    <?php esc_html_e('Vote', '3task-polls'); ?>
                </button>
            <?php endif; ?>

        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render poll form based on type
     */
    private function render_poll_form($poll, $settings) {
        $nonce = wp_create_nonce('tpoll_vote');
        $answers = $poll->get_answers();

        // Randomize if enabled
        if ($poll->get_setting('randomize_answers', false)) {
            shuffle($answers);
        }

        ?>
        <form class="tpoll-form" method="post" data-nonce="<?php echo esc_attr($nonce); ?>">
            <input type="hidden" name="poll_id" value="<?php echo esc_attr($poll->id); ?>">

            <?php
            switch ($poll->type) {
                case 'single':
                    $this->render_single_choice($poll, $answers);
                    break;
                case 'multiple':
                    $this->render_multiple_choice($poll, $answers);
                    break;
                case 'rating':
                    $this->render_rating($poll, $answers);
                    break;
                case 'voting':
                    $this->render_voting($poll, $answers);
                    break;
            }
            ?>

            <?php if (!in_array($poll->type, array('rating', 'voting'))) : ?>
                <button type="submit" class="tpoll-submit">
                    <?php echo esc_html($settings['button_text'] ?? __('Vote', '3task-polls')); ?>
                </button>
            <?php endif; ?>

            <div class="tpoll-message tpoll-hidden"></div>
        </form>
        <?php
    }

    /**
     * Render single choice
     */
    private function render_single_choice($poll, $answers) {
        ?>
        <div class="tpoll-answers tpoll-single">
            <?php foreach ($answers as $answer) : ?>
                <label class="tpoll-answer tpoll-radio">
                    <input type="radio"
                           name="tpoll_answer"
                           value="<?php echo esc_attr($answer->id); ?>"
                           required>
                    <span class="tpoll-checkmark"></span>
                    <span class="tpoll-answer-text">
                        <?php echo esc_html($answer->answer_text); ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render multiple choice
     */
    private function render_multiple_choice($poll, $answers) {
        $max_choices = $poll->get_setting('max_choices', 0);
        ?>
        <div class="tpoll-answers tpoll-multiple"
             <?php if ($max_choices) : ?>data-max-choices="<?php echo esc_attr($max_choices); ?>"<?php endif; ?>>
            <?php if ($max_choices) : ?>
                <p class="tpoll-hint">
                    <?php
                        /* translators: %d: maximum number of choices allowed */
                        printf(esc_html__('Select up to %d options', '3task-polls'), absint($max_choices));
                    ?>
                </p>
            <?php endif; ?>
            <?php foreach ($answers as $answer) : ?>
                <label class="tpoll-answer tpoll-checkbox">
                    <input type="checkbox"
                           name="tpoll_answers[]"
                           value="<?php echo esc_attr($answer->id); ?>">
                    <span class="tpoll-checkmark"></span>
                    <span class="tpoll-answer-text">
                        <?php echo esc_html($answer->answer_text); ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render rating (stars)
     */
    private function render_rating($poll, $answers) {
        $answer = isset($answers[0]) ? $answers[0] : null;
        $max_rating = $poll->get_setting('max_rating', 5);
        ?>
        <div class="tpoll-rating" data-max="<?php echo esc_attr($max_rating); ?>">
            <input type="hidden" name="tpoll_answer" value="<?php echo $answer ? esc_attr($answer->id) : ''; ?>">
            <input type="hidden" name="tpoll_value" value="0">
            <div class="tpoll-stars">
                <?php for ($i = 1; $i <= $max_rating; $i++) : ?>
                    <span class="tpoll-star" data-value="<?php echo esc_attr($i); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="32" height="32">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                    </span>
                <?php endfor; ?>
            </div>
            <span class="tpoll-rating-text"><?php esc_html_e('Click to rate', '3task-polls'); ?></span>
        </div>
        <?php
    }

    /**
     * Render voting (thumbs up/down)
     */
    private function render_voting($poll, $answers) {
        // Get current results for display
        $results = TPoll_Vote::get_results($poll->id);
        $up_votes = 0;
        $down_votes = 0;

        if ($results && isset($results['answers'][0])) {
            $up_votes = max(0, $results['answers'][0]['value']);
            $down_votes = abs(min(0, $results['answers'][0]['value']));
            // Actually we track net, so let's just show total
            $up_votes = $results['total_votes'];
        }

        $answer = isset($answers[0]) ? $answers[0] : null;
        ?>
        <div class="tpoll-voting">
            <input type="hidden" name="tpoll_answer" value="<?php echo $answer ? esc_attr($answer->id) : ''; ?>">
            <input type="hidden" name="tpoll_value" value="0">
            <button type="button" class="tpoll-vote-btn tpoll-vote-up" data-value="1">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
                    <path d="M2 20h2c.55 0 1-.45 1-1v-9c0-.55-.45-1-1-1H2v11zm19.83-7.12c.11-.25.17-.52.17-.8V11c0-1.1-.9-2-2-2h-5.5l.92-4.65c.05-.22.02-.46-.08-.66-.23-.45-.52-.86-.88-1.22L14 2 7.59 8.41C7.21 8.79 7 9.3 7 9.83v7.84C7 18.95 8.05 20 9.34 20h8.11c.7 0 1.36-.37 1.72-.97l2.66-6.15z"/>
                </svg>
                <span class="tpoll-count"><?php echo esc_html($up_votes); ?></span>
            </button>
            <button type="button" class="tpoll-vote-btn tpoll-vote-down" data-value="-1">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
                    <path d="M22 4h-2c-.55 0-1 .45-1 1v9c0 .55.45 1 1 1h2V4zM2.17 11.12c-.11.25-.17.52-.17.8V13c0 1.1.9 2 2 2h5.5l-.92 4.65c-.05.22-.02.46.08.66.23.45.52.86.88 1.22L10 22l6.41-6.41c.38-.38.59-.89.59-1.42V6.34C17 5.05 15.95 4 14.66 4h-8.1c-.71 0-1.36.37-1.72.97l-2.67 6.15z"/>
                </svg>
                <span class="tpoll-count">0</span>
            </button>
        </div>
        <?php
    }

    /**
     * Render results
     */
    private function render_results($poll, $show_voters) {
        $results = TPoll_Vote::get_results($poll->id);

        if (!$results) {
            return;
        }

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

            <?php if ($poll->type === 'rating' && isset($results['average_rating'])) : ?>
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
    }
}

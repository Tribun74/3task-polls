<?php
/**
 * TPoll Shortcode Handler
 *
 * The HTML is the same for every visitor, so full-page caches can store it.
 * Whether the current visitor has already voted is loaded afterwards via AJAX.
 *
 * @package TPoll
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPoll_Shortcode {

    /**
     * Context (post ID) of the poll being rendered, 0 if the poll is not counted per post.
     *
     * @var int
     */
    private $context = 0;

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('tpoll', array($this, 'render'));
    }

    /**
     * Text from the settings, or the translated default when the field is empty.
     *
     * @param string $key Setting key.
     */
    public static function text($key) {
        $settings = get_option('tpoll_settings', array());
        if (!empty($settings[$key]) && is_string($settings[$key])) {
            return $settings[$key];
        }

        switch ($key) {
            case 'button_text':
                return __('Vote', '3task-polls');
            case 'voted_text':
                return __('You have already voted.', '3task-polls');
            case 'closed_text':
                return __('This poll is closed.', '3task-polls');
        }
        return '';
    }

    /**
     * Available designs. Add-ons add their own via the filter.
     *
     * @return array Key => label.
     */
    public static function themes() {
        $themes = array(
            'default' => __('Default', '3task-polls'),
            'minimal' => __('Minimal', '3task-polls'),
            'rounded' => __('Rounded', '3task-polls'),
            'square' => __('Square', '3task-polls'),
            'pill' => __('Pill', '3task-polls'),
        );

        /**
         * Filters the poll designs.
         *
         * @param array $themes Key => label.
         */
        return (array) apply_filters('tpoll_themes', $themes);
    }

    /**
     * Effective display options of a poll: shortcode attribute, then poll setting, then global default.
     *
     * @param TPoll_Poll $poll Poll.
     * @param array      $atts Shortcode attributes.
     * @return array
     */
    public static function display_options($poll, $atts = array()) {
        $global = get_option('tpoll_settings', array());

        $show_results = !empty($atts['show_results']) ? $atts['show_results'] : $poll->get_setting('show_results', $global['show_results'] ?? 'after_vote');
        if (!in_array($show_results, array('after_vote', 'always', 'never'), true)) {
            $show_results = 'after_vote';
        }

        if (isset($atts['show_voters']) && $atts['show_voters'] !== '') {
            $show_voters = ($atts['show_voters'] === 'yes');
        } else {
            $show_voters = (bool) $poll->get_setting('show_voters_count', $global['show_voters_count'] ?? true);
        }

        $theme = !empty($atts['theme']) ? $atts['theme'] : $poll->get_setting('theme', $global['theme'] ?? 'default');
        if (!array_key_exists($theme, self::themes())) {
            $theme = 'default';
        }

        $dark_mode = !empty($atts['dark_mode']) ? $atts['dark_mode'] : $poll->get_setting('dark_mode', $global['dark_mode'] ?? 'no');
        if (!in_array($dark_mode, array('auto', 'yes', 'no'), true)) {
            $dark_mode = 'no';
        }

        // The former stored defaults (#4F46E5, #E5E7EB) count as "no custom color".
        $global_bar = (string) sanitize_hex_color($global['results_bar_color'] ?? '');
        $global_bar = strtoupper($global_bar) === '#4F46E5' ? '' : $global_bar;
        $bar_color = sanitize_hex_color($poll->get_style('bar_color', '')) ?: $global_bar;
        $bar_bg = (string) sanitize_hex_color($global['results_bar_bg'] ?? '');
        $bar_bg = strtoupper($bar_bg) === '#E5E7EB' ? '' : $bar_bg;
        $animation = ($global['animation'] ?? 'grow') === 'none' ? 'none' : 'grow';

        return compact('show_results', 'show_voters', 'theme', 'dark_mode', 'bar_color', 'bar_bg', 'animation');
    }

    /**
     * Whether results may be shown to the current visitor.
     *
     * @param TPoll_Poll $poll         Poll.
     * @param string     $show_results after_vote, always or never.
     * @param bool       $has_voted    Visitor has voted.
     */
    public static function results_visible($poll, $show_results, $has_voted) {
        if ($show_results === 'never') {
            $visible = false;
        } elseif ($show_results === 'always' || $poll->is_closed()) {
            $visible = true;
        } else {
            $visible = (bool) $has_voted;
        }

        /**
         * Filters whether the results may be shown to the current visitor.
         *
         * @param bool       $visible      Result of the poll setting.
         * @param TPoll_Poll $poll         Poll.
         * @param string     $show_results after_vote, always or never.
         * @param bool       $has_voted    Visitor has voted.
         */
        return (bool) apply_filters('tpoll_results_visible', $visible, $poll, $show_results, (bool) $has_voted);
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
            'dark_mode' => '', // auto, yes, no (empty = use poll setting)
            'context' => 0 // post ID, only for polls counted per post
        ), $atts, 'tpoll');

        $poll_id = absint($atts['id']);

        if (!$poll_id) {
            return '';
        }

        $poll = TPoll_Poll::get($poll_id);

        if (!$poll) {
            return '';
        }

        if ($poll->status === 'draft' && !current_user_can('edit_tpoll')) {
            return '';
        }

        $this->context = TPoll_Public::context_for($poll, $atts['context']);

        wp_enqueue_style('tpoll-frontend');
        wp_enqueue_script('tpoll-frontend');

        $opt = self::display_options($poll, $atts);

        /**
         * Fires when a poll is rendered, so add-ons can load their assets.
         *
         * @param TPoll_Poll $poll Poll.
         * @param array      $opt  Display options.
         */
        do_action('tpoll_enqueue_assets', $poll, $opt);
        $restriction = $poll->get_setting('vote_restriction', 'ip');
        $is_closed = $poll->is_closed();
        $is_upcoming = !$is_closed && $poll->is_upcoming();
        $needs_login = ($restriction === 'user' && !is_user_logged_in());
        $show_form = !$is_closed && !$is_upcoming && $poll->status !== 'draft';
        $show_results_now = self::results_visible($poll, $opt['show_results'], false);

        $classes = array(
            'tpoll-poll',
            'tpoll-type-' . $poll->type,
            'tpoll-theme-' . $opt['theme'],
        );
        if ($opt['dark_mode'] === 'yes') {
            $classes[] = 'tpoll-dark';
        } elseif ($opt['dark_mode'] === 'auto') {
            $classes[] = 'tpoll-dark-auto';
        }
        if ($opt['animation'] === 'none') {
            $classes[] = 'tpoll-no-animation';
        }

        $style = '';
        if ($opt['bar_color']) {
            $style .= '--tpoll-bar:' . $opt['bar_color'] . ';';
        }
        // Only a custom track color is set inline, so the dark mode keeps its own track color.
        if ($opt['bar_bg']) {
            $style .= '--tpoll-bar-bg:' . $opt['bar_bg'] . ';';
        }

        $question_id = 'tpoll-q-' . $poll_id;

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $classes)); ?>"
             id="tpoll-<?php echo esc_attr((string) $poll_id); ?>"
             data-poll-id="<?php echo esc_attr((string) $poll_id); ?>"
             data-poll-type="<?php echo esc_attr($poll->type); ?>"
             data-show-results="<?php echo esc_attr($opt['show_results']); ?>"
             data-active="<?php echo $show_form ? '1' : '0'; ?>"
             data-choice="<?php echo TPoll_Poll::is_choice_type($poll->type) ? '1' : '0'; ?>"
             <?php if ($this->context) : ?>data-context="<?php echo esc_attr((string) $this->context); ?>"<?php endif; ?>
             <?php if ($style) : ?>style="<?php echo esc_attr($style); ?>"<?php endif; ?>>

            <?php if (!empty($poll->question)) : ?>
                <div class="tpoll-question" id="<?php echo esc_attr($question_id); ?>">
                    <?php echo esc_html($poll->question); ?>
                </div>
            <?php endif; ?>

            <?php if ($is_closed) : ?>
                <div class="tpoll-closed"><?php echo esc_html(self::text('closed_text')); ?></div>
            <?php elseif ($is_upcoming) : ?>
                <div class="tpoll-closed">
                    <?php
                    if (!empty($poll->start_date)) {
                        printf(
                            /* translators: %s: date and time the poll opens */
                            esc_html__('This poll opens on %s', '3task-polls'),
                            esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $poll->start_date))
                        );
                    } else {
                        esc_html_e('This poll is not open yet.', '3task-polls');
                    }
                    ?>
                </div>
            <?php endif; ?>

            <?php if ($show_form && $needs_login) : ?>
                <div class="tpoll-closed tpoll-login-required">
                    <?php esc_html_e('Please log in to vote.', '3task-polls'); ?>
                    <a href="<?php echo esc_url(wp_login_url(get_permalink() ?: home_url('/'))); ?>"><?php esc_html_e('Log in', '3task-polls'); ?></a>
                </div>
            <?php elseif ($show_form) : ?>
                <?php $this->render_poll_form($poll, $question_id, $show_results_now); ?>
                <noscript><p class="tpoll-noscript"><?php esc_html_e('Please enable JavaScript to vote.', '3task-polls'); ?></p></noscript>
            <?php endif; ?>

            <div class="tpoll-voted tpoll-hidden"><?php echo esc_html(self::text('voted_text')); ?></div>

            <?php
            // Reactions show their counts inside the buttons while voting is possible.
            if ($poll->type === 'reactions' && $show_form) {
                $show_results_now = false;
            }
            ?>
            <div class="tpoll-results-container" aria-live="polite"<?php echo $show_results_now ? '' : ' hidden'; ?>>
                <?php
                if ($show_results_now) {
                    echo self::results_html($poll, TPoll_Vote::get_results($poll_id, $poll, $this->context), $opt['show_voters']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside results_html().
                }
                ?>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render poll form based on type
     */
    private function render_poll_form($poll, $question_id, $show_counts) {
        $answers = $poll->get_answers();

        /**
         * Filters whether the answers are shuffled (e.g. never for a versus poll).
         *
         * @param bool       $shuffle Poll setting "randomize answers".
         * @param TPoll_Poll $poll    Poll.
         */
        if (apply_filters('tpoll_shuffle_answers', (bool) $poll->get_setting('randomize_answers', false), $poll)) {
            shuffle($answers);
        }

        ?>
        <form class="tpoll-form" method="post" data-nonce="<?php echo esc_attr(wp_create_nonce('tpoll_vote')); ?>">
            <input type="hidden" name="poll_id" value="<?php echo esc_attr($poll->id); ?>">
            <div class="tpoll-hp-wrap" aria-hidden="true">
                <label><?php esc_html_e('Leave this field empty', '3task-polls'); ?>
                    <input type="text" name="tpoll_hp" value="" tabindex="-1" autocomplete="off">
                </label>
            </div>

            <?php
            /**
             * Fires before the answers, inside the vote form. Useful for fields that must be
             * filled before a click votes (stars, thumbs, versus).
             *
             * @param TPoll_Poll $poll Poll.
             */
            do_action('tpoll_form_before_answers', $poll);

            switch ($poll->type) {
                case 'single':
                case 'quiz':
                    $this->render_single_choice($poll, $answers, $question_id);
                    break;
                case 'multiple':
                    $this->render_multiple_choice($poll, $answers, $question_id);
                    break;
                case 'rating':
                    $this->render_rating($poll, $answers, $question_id);
                    break;
                case 'voting':
                    $this->render_voting($poll, $answers, $question_id, $show_counts);
                    break;
                case 'reactions':
                    $this->render_reactions($poll, $answers, $question_id, $show_counts);
                    break;
                default:
                    /**
                     * Renders the answer area of an add-on poll type.
                     *
                     * @param TPoll_Poll $poll        Poll.
                     * @param array      $answers     Answers (already shuffled if enabled).
                     * @param string     $question_id ID of the question element, for aria-labelledby.
                     */
                    do_action('tpoll_render_form_' . $poll->type, $poll, $answers, $question_id);
                    break;
            }

            /**
             * Fires after the answers, inside the vote form. Fields named tpoll_extra[...]
             * are sent along with the vote.
             *
             * @param TPoll_Poll $poll Poll.
             */
            do_action('tpoll_form_after_answers', $poll);

            /**
             * Filters the types that show the vote button.
             *
             * @param string[] $types Default: single, multiple.
             */
            $submit_types = (array) apply_filters('tpoll_submit_button_types', array('single', 'multiple'));
            ?>

            <?php if (in_array($poll->type, $submit_types, true)) : ?>
                <button type="submit" class="tpoll-submit">
                    <?php
                    /**
                     * Filters the text of the vote button.
                     *
                     * @param string     $text Text from the settings or the default.
                     * @param TPoll_Poll $poll Poll.
                     */
                    echo esc_html((string) apply_filters('tpoll_submit_button_text', self::text('button_text'), $poll));
                    ?>
                </button>
            <?php endif; ?>

            <div class="tpoll-message tpoll-hidden" role="status" aria-live="polite"></div>
        </form>
        <?php
    }

    /**
     * Render single choice
     */
    private function render_single_choice($poll, $answers, $question_id) {
        ?>
        <div class="tpoll-answers tpoll-single" role="radiogroup" aria-labelledby="<?php echo esc_attr($question_id); ?>">
            <?php foreach ($answers as $answer) : ?>
                <label class="tpoll-answer tpoll-radio">
                    <input type="radio"
                           name="tpoll_answer"
                           value="<?php echo esc_attr($answer->id); ?>">
                    <span class="tpoll-checkmark" aria-hidden="true"></span>
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
    private function render_multiple_choice($poll, $answers, $question_id) {
        $max_choices = (int) $poll->get_setting('max_choices', 0);
        ?>
        <div class="tpoll-answers tpoll-multiple" role="group" aria-labelledby="<?php echo esc_attr($question_id); ?>"
             <?php if ($max_choices) : ?>data-max-choices="<?php echo esc_attr((string) $max_choices); ?>"<?php endif; ?>>
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
                    <span class="tpoll-checkmark" aria-hidden="true"></span>
                    <span class="tpoll-answer-text">
                        <?php echo esc_html($answer->answer_text); ?>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render rating (stars). Each star is a button, so it works with the keyboard.
     */
    private function render_rating($poll, $answers, $question_id) {
        $answer = isset($answers[0]) ? $answers[0] : null;
        $max_rating = 5;
        ?>
        <div class="tpoll-rating" data-max="<?php echo esc_attr((string) $max_rating); ?>">
            <input type="hidden" name="tpoll_answer" value="<?php echo $answer ? esc_attr($answer->id) : ''; ?>">
            <input type="hidden" name="tpoll_value" value="0">
            <div class="tpoll-stars" role="group" aria-labelledby="<?php echo esc_attr($question_id); ?>">
                <?php for ($i = 1; $i <= $max_rating; $i++) : ?>
                    <button type="button" class="tpoll-star" data-value="<?php echo esc_attr((string) $i); ?>"
                            aria-label="<?php
                            /* translators: 1: star value, 2: maximum stars */
                            echo esc_attr(sprintf(__('Rate %1$d of %2$d stars', '3task-polls'), $i, $max_rating));
                            ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="32" height="32" aria-hidden="true" focusable="false">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                    </button>
                <?php endfor; ?>
            </div>
            <span class="tpoll-rating-text"><?php esc_html_e('Click to rate', '3task-polls'); ?></span>
        </div>
        <?php
    }

    /**
     * Render voting (thumbs up/down)
     */
    private function render_voting($poll, $answers, $question_id, $show_counts) {
        $up = '';
        $down = '';

        if ($show_counts) {
            $results = TPoll_Vote::get_results($poll->id, $poll, $this->context);
            if ($results) {
                $up = (string) $results['up'];
                $down = (string) $results['down'];
            }
        }

        $answer = isset($answers[0]) ? $answers[0] : null;
        ?>
        <div class="tpoll-voting" role="group" aria-labelledby="<?php echo esc_attr($question_id); ?>">
            <input type="hidden" name="tpoll_answer" value="<?php echo $answer ? esc_attr($answer->id) : ''; ?>">
            <input type="hidden" name="tpoll_value" value="0">
            <button type="button" class="tpoll-vote-btn tpoll-vote-up" data-value="1" aria-label="<?php esc_attr_e('Thumbs up', '3task-polls'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24" aria-hidden="true" focusable="false">
                    <path d="M2 20h2c.55 0 1-.45 1-1v-9c0-.55-.45-1-1-1H2v11zm19.83-7.12c.11-.25.17-.52.17-.8V11c0-1.1-.9-2-2-2h-5.5l.92-4.65c.05-.22.02-.46-.08-.66-.23-.45-.52-.86-.88-1.22L14 2 7.59 8.41C7.21 8.79 7 9.3 7 9.83v7.84C7 18.95 8.05 20 9.34 20h8.11c.7 0 1.36-.37 1.72-.97l2.66-6.15z"/>
                </svg>
                <span class="tpoll-count tpoll-count-up"><?php echo esc_html($up); ?></span>
            </button>
            <button type="button" class="tpoll-vote-btn tpoll-vote-down" data-value="-1" aria-label="<?php esc_attr_e('Thumbs down', '3task-polls'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24" aria-hidden="true" focusable="false">
                    <path d="M22 4h-2c-.55 0-1 .45-1 1v9c0 .55.45 1 1 1h2V4zM2.17 11.12c-.11.25-.17.52-.17.8V13c0 1.1.9 2 2 2h5.5l-.92 4.65c-.05.22-.02.46.08.66.23.45.52.86.88 1.22L10 22l6.41-6.41c.38-.38.59-.89.59-1.42V6.34C17 5.05 15.95 4 14.66 4h-8.1c-.71 0-1.36.37-1.72.97l-2.67 6.15z"/>
                </svg>
                <span class="tpoll-count tpoll-count-down"><?php echo esc_html($down); ?></span>
            </button>
        </div>
        <?php
    }

    /**
     * Render reactions: one button per answer (usually an emoji), a click votes.
     */
    private function render_reactions($poll, $answers, $question_id, $show_counts) {
        $counts = array();
        if ($show_counts) {
            $results = TPoll_Vote::get_results($poll->id, $poll, $this->context);
            foreach ((array) ($results['answers'] ?? array()) as $row) {
                $counts[(int) $row['id']] = (int) $row['votes'];
            }
        }
        ?>
        <div class="tpoll-reactions" role="group" aria-labelledby="<?php echo esc_attr($question_id); ?>">
            <?php foreach ($answers as $answer) : ?>
                <button type="button" class="tpoll-reaction" data-answer="<?php echo esc_attr((string) $answer->id); ?>">
                    <span class="tpoll-reaction-icon" aria-hidden="true"><?php echo esc_html($answer->answer_text); ?></span>
                    <span class="screen-reader-text"><?php echo esc_html($answer->answer_text); ?></span>
                    <span class="tpoll-count"><?php echo isset($counts[(int) $answer->id]) ? esc_html(number_format_i18n($counts[(int) $answer->id])) : ''; ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Donut chart for single choice results (inline SVG, no library).
     *
     * @param array $answers Result answers with percentage.
     * @return string
     */
    private static function donut_html($answers) {
        $radius = 15.9155; // circumference of 100
        $offset = 25; // start at 12 o'clock
        $parts = '';
        $legend = '';
        $i = 0;
        foreach ($answers as $answer) {
            $color = 'var(--tpoll-c' . (($i % 8) + 1) . ')';
            $pct = max(0, min(100, (float) $answer['percentage']));
            if ($pct > 0) {
                $parts .= '<circle cx="21" cy="21" r="' . $radius . '" fill="none" stroke="' . esc_attr($color) . '" stroke-width="7" stroke-dasharray="' . esc_attr(number_format($pct, 2, '.', '') . ' ' . number_format(100 - $pct, 2, '.', '')) . '" stroke-dashoffset="' . esc_attr(number_format($offset, 2, '.', '')) . '"></circle>';
                $offset -= $pct;
            }
            $legend .= '<li><span class="tpoll-donut-key" style="background:' . esc_attr($color) . '"></span><span class="tpoll-result-answer">' . esc_html($answer['text']) . '</span> <span class="tpoll-result-percent">' . esc_html(number_format_i18n($pct, 1)) . '&nbsp;%</span> <span class="tpoll-result-votes">'
                /* translators: %d: number of votes */
                . esc_html(sprintf(_n('%d vote', '%d votes', (int) $answer['votes'], '3task-polls'), (int) $answer['votes'])) . '</span></li>';
            $i++;
        }

        return '<div class="tpoll-donut"><svg viewBox="0 0 42 42" class="tpoll-donut-chart" aria-hidden="true" focusable="false"><circle cx="21" cy="21" r="' . $radius . '" fill="none" stroke="var(--tpoll-bar-bg, var(--tpoll-border))" stroke-width="7"></circle>' . $parts . '</svg><ul class="tpoll-donut-legend">' . $legend . '</ul></div>';
    }

    /**
     * Results HTML, shared by the shortcode and the AJAX responses.
     *
     * @param TPoll_Poll|null $poll        Poll.
     * @param array|null      $results     Result of TPoll_Vote::get_results().
     * @param bool       $show_voters Show the voter count.
     * @return string
     */
    public static function results_html($poll, $results, $show_voters) {
        if (!$poll || !$results) {
            return '';
        }

        /**
         * Lets add-on types render their own results. Return a non-empty string to replace the default output.
         *
         * @param string     $html        Empty string.
         * @param TPoll_Poll $poll        Poll.
         * @param array      $results     Results.
         * @param bool       $show_voters Show the voter count.
         */
        $custom = (string) apply_filters('tpoll_results_html', '', $poll, $results, $show_voters);
        if ($custom !== '') {
            return $custom;
        }

        ob_start();
        ?>
        <div class="tpoll-results">
            <?php if ($poll->type === 'voting') : ?>
                <div class="tpoll-result-item tpoll-result-voting">
                    <span class="tpoll-result-up">
                        <?php
                        /* translators: %d: number of thumbs up */
                        printf(esc_html__('Thumbs up: %d', '3task-polls'), absint($results['up']));
                        ?>
                    </span>
                    <span class="tpoll-result-down">
                        <?php
                        /* translators: %d: number of thumbs down */
                        printf(esc_html__('Thumbs down: %d', '3task-polls'), absint($results['down']));
                        ?>
                    </span>
                </div>
            <?php elseif ($poll->type === 'reactions') : ?>
                <div class="tpoll-reactions tpoll-reactions-result">
                    <?php foreach ($results['answers'] as $answer) : ?>
                        <span class="tpoll-reaction is-result">
                            <span class="tpoll-reaction-icon" aria-hidden="true"><?php echo esc_html($answer['text']); ?></span>
                            <span class="tpoll-count"><?php echo esc_html(number_format_i18n((int) $answer['votes'])); ?></span>
                            <span class="screen-reader-text"><?php
                            /* translators: 1: reaction, 2: number of votes */
                            echo esc_html(sprintf(__('%1$s: %2$d', '3task-polls'), $answer['text'], (int) $answer['votes']));
                            ?></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($poll->type === 'single' && $poll->get_setting('result_chart', 'bars') === 'donut') : ?>
                <?php echo self::donut_html($results['answers']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside donut_html(). ?>
            <?php elseif ($poll->type === 'rating') : ?>
                <div class="tpoll-average-rating">
                    <?php
                    printf(
                        /* translators: 1: average rating, 2: number of ratings */
                        esc_html(_n('Average rating: %1$s / 5 (%2$d rating)', 'Average rating: %1$s / 5 (%2$d ratings)', (int) $results['total_votes'], '3task-polls')),
                        esc_html(number_format_i18n((float) $results['average_rating'], 1)),
                        absint($results['total_votes'])
                    );
                    ?>
                </div>
            <?php else : ?>
                <?php foreach ($results['answers'] as $answer) : ?>
                    <div class="tpoll-result-item">
                        <div class="tpoll-result-text">
                            <span class="tpoll-result-answer"><?php echo esc_html($answer['text']); ?></span>
                            <span class="tpoll-result-percent"><?php echo esc_html(number_format_i18n((float) $answer['percentage'], 1)); ?>&nbsp;%</span>
                        </div>
                        <div class="tpoll-result-bar-wrap" aria-hidden="true">
                            <div class="tpoll-result-bar" style="width: <?php echo esc_attr((string) min(100, (float) $answer['percentage'])); ?>%"></div>
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
                <?php if ($poll->type === 'multiple') : ?>
                    <p class="tpoll-result-note"><?php esc_html_e('Percentages show the share of voters who picked each answer. Voters could pick more than one.', '3task-polls'); ?></p>
                <?php endif; ?>
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

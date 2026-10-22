<?php
/**
 * Questionnaire: bundles existing polls into one form, shown step by step and sent at the end.
 *
 * Uses the poll tables as they are. A questionnaire is a poll of the type "survey"; its steps are the
 * IDs of other polls in its settings. All answers of one submission share one ballot.
 *
 * @package TPoll
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPoll_Survey {

    /**
     * Type key.
     */
    const TYPE = 'survey';

    /**
     * Register hooks.
     */
    public static function init() {
        add_filter('tpoll_poll_types', array(__CLASS__, 'register_type'));
        add_filter('tpoll_save_poll_settings', array(__CLASS__, 'save_settings'), 10, 3);
        add_filter('tpoll_validate_poll', array(__CLASS__, 'validate'), 10, 2);
        add_filter('tpoll_submit_button_types', array(__CLASS__, 'add_type'));
        add_filter('tpoll_submit_button_text', array(__CLASS__, 'button_text'), 10, 2);
        add_action('tpoll_render_form_' . self::TYPE, array(__CLASS__, 'render_form'), 10, 3);
        add_filter('tpoll_has_voted', array(__CLASS__, 'has_voted'), 10, 2);
        add_filter('tpoll_results_visible', array(__CLASS__, 'results_visible'), 10, 4);
        add_filter('tpoll_results_html', array(__CLASS__, 'results_html'), 10, 2);
        add_filter('tpoll_pre_vote', array(__CLASS__, 'block_direct_vote'), 10, 2);
        add_action('tpoll_edit_after_answers', array(__CLASS__, 'render_edit_box'));
        add_action('wp_ajax_tpoll_survey_submit', array(__CLASS__, 'ajax_submit'));
        add_action('wp_ajax_nopriv_tpoll_survey_submit', array(__CLASS__, 'ajax_submit'));
    }

    /**
     * Is this poll a questionnaire?
     *
     * @param TPoll_Poll|null $poll Poll.
     */
    public static function is_survey($poll) {
        return $poll && $poll->type === self::TYPE;
    }

    /**
     * Add the type to the type list.
     *
     * @param array $types Type key => label.
     */
    public static function register_type($types) {
        $types[self::TYPE] = __('Questionnaire', '3task-polls');
        return $types;
    }

    /**
     * Add the type to a list of type keys.
     *
     * @param string[] $types Type keys.
     */
    public static function add_type($types) {
        $types[] = self::TYPE;
        return $types;
    }

    /**
     * The questionnaire is sent once at the end.
     *
     * @param string     $text Button text.
     * @param TPoll_Poll $poll Poll.
     */
    public static function button_text($text, $poll) {
        return self::is_survey($poll) ? _x('Send', 'questionnaire button', '3task-polls') : $text;
    }

    /**
     * Steps as loaded polls, in their order. Steps that no longer exist are skipped.
     *
     * @param TPoll_Poll $poll Questionnaire.
     * @return TPoll_Poll[]
     */
    public static function steps($poll) {
        $steps = array();
        foreach (self::step_ids($poll) as $id) {
            $step = TPoll_Poll::get($id);
            if ($step && in_array($step->type, self::step_types(), true)) {
                $steps[] = $step;
            }
        }
        return $steps;
    }

    /**
     * Editor box: the questions of the questionnaire in their order.
     *
     * @param TPoll_Poll $poll Poll being edited.
     */
    public static function render_edit_box($poll) {
        $types = TPoll_Poll::get_types();
        $label = function ($step) use ($types) {
            return $step->title . ' (' . (isset($types[$step->type]) ? $types[$step->type] : $step->type) . ')';
        };
        $chosen = self::is_survey($poll) ? self::steps($poll) : array();
        $chosen_ids = wp_list_pluck($chosen, 'id');
        $available = array();
        foreach (TPoll_Poll::get_all(array('status' => 'published', 'per_page' => 200, 'orderby' => 'title', 'order' => 'ASC')) as $candidate) {
            if (in_array($candidate->type, self::step_types(), true) && (int) $candidate->id !== (int) $poll->id) {
                $available[] = $candidate;
            }
        }
        ?>
        <div class="postbox tpoll-survey-only<?php echo self::is_survey($poll) ? '' : ' tpoll-hidden'; ?>" id="tpoll-survey-box">
            <h2 class="hndle"><?php esc_html_e('Questions of the questionnaire', '3task-polls'); ?></h2>
            <div class="inside">
                <p class="description"><?php esc_html_e('Visitors answer the questions one after the other and send all answers together at the end. Possible are published polls of the types single choice, multiple choice, quiz question and star rating. The question field above is shown as the introduction.', '3task-polls'); ?></p>
                <ol id="tpoll-survey-steps" class="tpoll-survey-steps">
                    <?php foreach ($chosen as $step) : ?>
                        <?php self::render_step_item((int) $step->id, $label($step)); ?>
                    <?php endforeach; ?>
                </ol>
                <p class="tpoll-survey-add-row">
                    <label for="tpoll-survey-add"><?php esc_html_e('Add question', '3task-polls'); ?></label>
                    <select id="tpoll-survey-add">
                        <option value=""><?php esc_html_e('Select a poll', '3task-polls'); ?></option>
                        <?php foreach ($available as $candidate) : ?>
                            <option value="<?php echo esc_attr((string) $candidate->id); ?>"<?php disabled(in_array($candidate->id, $chosen_ids, false)); ?>><?php echo esc_html($label($candidate)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button" id="tpoll-survey-add-btn"><?php esc_html_e('Add', '3task-polls'); ?></button>
                </p>
                <?php if (empty($available)) : ?>
                    <p class="description"><?php esc_html_e('There is no published poll of a suitable type yet. Create and publish the questions first.', '3task-polls'); ?></p>
                <?php endif; ?>
                <?php if (self::is_survey($poll) && $poll->id) : ?>
                    <p><?php
                    $count = count(self::participations($poll));
                    /* translators: %d: number of submitted questionnaires */
                    echo esc_html(sprintf(_n('%d participation', '%d participations', $count, '3task-polls'), $count));
                    ?></p>
                <?php endif; ?>
            </div>
        </div>
        <script type="text/html" id="tmpl-tpoll-survey-step">
            <?php self::render_step_item(0, ''); ?>
        </script>
        <?php
    }

    /**
     * One row in the list of questions.
     *
     * @param int    $id    Poll ID.
     * @param string $label Title and type.
     */
    private static function render_step_item($id, $label) {
        ?>
        <li>
            <input type="hidden" name="survey_steps[]" value="<?php echo esc_attr((string) $id); ?>">
            <span class="tpoll-survey-step-title"><?php echo esc_html($label); ?></span>
            <button type="button" class="button-link tpoll-survey-step-up" aria-label="<?php esc_attr_e('Move up', '3task-polls'); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
            <button type="button" class="button-link tpoll-survey-step-down" aria-label="<?php esc_attr_e('Move down', '3task-polls'); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
            <button type="button" class="button-link-delete tpoll-survey-step-remove" aria-label="<?php esc_attr_e('Remove', '3task-polls'); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
        </li>
        <?php
    }

    /**
     * All steps in one form. Without JavaScript they are listed one below the other,
     * the front-end script shows one step at a time.
     *
     * @param TPoll_Poll $poll        Questionnaire.
     * @param array      $answers     Answers of the questionnaire (unused).
     * @param string     $question_id ID of the introduction element.
     */
    public static function render_form($poll, $answers, $question_id) {
        $steps = self::steps($poll);
        $total = count($steps);
        ?>
        <p class="tpoll-survey-progress" aria-live="polite"><?php
        /* translators: 1: number of the current question, 2: number of questions */
        echo esc_html(sprintf(__('Question %1$d of %2$d', '3task-polls'), 1, $total));
        ?></p>
        <?php foreach ($steps as $index => $step) : ?>
            <fieldset class="tpoll-survey-step" data-step="<?php echo esc_attr((string) ($index + 1)); ?>" data-poll-id="<?php echo esc_attr((string) $step->id); ?>" data-type="<?php echo esc_attr($step->type); ?>">
                <legend class="tpoll-survey-question"><?php echo esc_html($step->question); ?></legend>
                <?php self::render_step($poll, $step); ?>
            </fieldset>
        <?php endforeach; ?>
        <div class="tpoll-survey-nav">
            <button type="button" class="tpoll-survey-back"><?php esc_html_e('Back', '3task-polls'); ?></button>
            <button type="button" class="tpoll-survey-next"><?php esc_html_e('Next', '3task-polls'); ?></button>
        </div>
        <?php
    }

    /**
     * Answer fields of one step. All fields are named steps[<poll ID>][].
     *
     * @param TPoll_Poll $poll Questionnaire.
     * @param TPoll_Poll $step Step.
     */
    private static function render_step($poll, $step) {
        $name = 'steps[' . (int) $step->id . '][]';

        if ($step->type === 'rating') {
            ?>
            <div class="tpoll-survey-stars">
                <?php for ($i = 5; $i >= 1; $i--) : ?>
                    <?php $field_id = 'tpoll-s' . (int) $poll->id . '-' . (int) $step->id . '-' . $i; ?>
                    <input type="radio" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $i); ?>" id="<?php echo esc_attr($field_id); ?>">
                    <label for="<?php echo esc_attr($field_id); ?>"><span class="screen-reader-text"><?php
                    /* translators: 1: star value, 2: maximum stars */
                    echo esc_html(sprintf(__('Rate %1$d of %2$d stars', '3task-polls'), $i, 5));
                    ?></span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="32" height="32" aria-hidden="true" focusable="false"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></label>
                <?php endfor; ?>
            </div>
            <?php
            return;
        }

        $answers = $step->get_answers();
        if (apply_filters('tpoll_shuffle_answers', (bool) $step->get_setting('randomize_answers', false), $step)) {
            shuffle($answers);
        }
        $multiple = $step->type === 'multiple';
        $max = $multiple ? (int) $step->get_setting('max_choices', 0) : 0;
        ?>
        <div class="tpoll-answers <?php echo $multiple ? 'tpoll-multiple' : 'tpoll-single'; ?>"<?php echo $max ? ' data-max-choices="' . esc_attr((string) $max) . '"' : ''; ?>>
            <?php if ($max) : ?>
                <p class="tpoll-hint"><?php
                /* translators: %d: maximum number of choices allowed */
                printf(esc_html__('Select up to %d options', '3task-polls'), absint($max));
                ?></p>
            <?php endif; ?>
            <?php foreach ($answers as $answer) : ?>
                <label class="tpoll-answer <?php echo $multiple ? 'tpoll-checkbox' : 'tpoll-radio'; ?>">
                    <input type="<?php echo $multiple ? 'checkbox' : 'radio'; ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) $answer->id); ?>">
                    <span class="tpoll-checkmark" aria-hidden="true"></span>
                    <span class="tpoll-answer-text"><?php echo esc_html($answer->answer_text); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Poll types that can be a step.
     *
     * @return string[]
     */
    public static function step_types() {
        /**
         * Filters the poll types that can be a step of a questionnaire.
         *
         * @param string[] $types Default: single, multiple, quiz, rating.
         */
        return (array) apply_filters('tpoll_survey_step_types', array('single', 'multiple', 'quiz', 'rating'));
    }

    /**
     * IDs of the steps in their order.
     *
     * @param TPoll_Poll $poll Questionnaire.
     * @return int[]
     */
    public static function step_ids($poll) {
        return array_values(array_map('intval', (array) $poll->get_setting('survey_steps', array())));
    }

    /**
     * Store the selected steps (in order, each once).
     *
     * @param array      $settings Sanitized settings.
     * @param array      $raw      Unslashed POST data.
     * @param TPoll_Poll $poll     Poll being saved.
     */
    public static function save_settings($settings, $raw, $poll) {
        if (!self::is_survey($poll)) {
            return $settings;
        }
        $ids = isset($raw['survey_steps']) ? array_map('absint', (array) $raw['survey_steps']) : array();
        $settings['survey_steps'] = array_values(array_unique(array_filter($ids)));
        return $settings;
    }

    /**
     * Steps must exist, be published and have a type that fits into a form.
     *
     * @param true|WP_Error $valid Result so far.
     * @param TPoll_Poll    $poll  Poll with the new values.
     */
    public static function validate($valid, $poll) {
        if (is_wp_error($valid) || !self::is_survey($poll)) {
            return $valid;
        }
        $ids = self::step_ids($poll);
        if (count($ids) < 2) {
            return new WP_Error('survey_steps_missing', __('A questionnaire needs at least two questions.', '3task-polls'));
        }
        foreach ($ids as $id) {
            $step = TPoll_Poll::get($id);
            if (!$step) {
                return new WP_Error('survey_step_missing', __('A selected question no longer exists.', '3task-polls'));
            }
            if ((int) $step->id === (int) $poll->id || !in_array($step->type, self::step_types(), true)) {
                /* translators: %s: title of the poll */
                return new WP_Error('survey_step_type', sprintf(__('The question "%s" cannot be part of a questionnaire.', '3task-polls'), $step->title));
            }
            if ($step->status !== 'published') {
                /* translators: %s: title of the poll */
                return new WP_Error('survey_step_draft', sprintf(__('The question "%s" is not published yet.', '3task-polls'), $step->title));
            }
        }
        return $valid;
    }

    /**
     * A visitor has taken part when one of the steps has a vote from them,
     * also a vote given outside the questionnaire.
     *
     * @param bool       $voted Result for the poll itself.
     * @param TPoll_Poll $poll  Poll.
     */
    public static function has_voted($voted, $poll) {
        if (!self::is_survey($poll)) {
            return $voted;
        }
        foreach (self::steps($poll) as $step) {
            if (TPoll_Vote::has_voted($step->id, $step)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Results only for participants (the steps may contain quiz questions), or when closed.
     *
     * @param bool       $visible      Result of the poll setting.
     * @param TPoll_Poll $poll         Poll.
     * @param string     $show_results Poll setting.
     * @param bool       $has_voted    Visitor has taken part.
     */
    public static function results_visible($visible, $poll, $show_results, $has_voted) {
        if (!self::is_survey($poll)) {
            return $visible;
        }
        return $has_voted || $poll->is_closed();
    }

    /**
     * Results of a questionnaire: the results of each step.
     *
     * @param string     $html Empty string.
     * @param TPoll_Poll $poll Poll.
     */
    public static function results_html($html, $poll) {
        return self::is_survey($poll) ? self::summary_html($poll) : $html;
    }

    /**
     * A questionnaire has no answers of its own to vote on.
     *
     * @param null|WP_Error $check Result so far.
     * @param TPoll_Poll    $poll  Poll.
     */
    public static function block_direct_vote($check, $poll) {
        if (self::is_survey($poll)) {
            return new WP_Error('invalid_poll', __('Invalid poll.', '3task-polls'));
        }
        return $check;
    }

    /**
     * Ballot prefix of one questionnaire. The rest of the 32 characters is random.
     *
     * @param TPoll_Poll $poll Questionnaire.
     */
    private static function ballot_prefix($poll) {
        return 's' . (int) $poll->id . '-';
    }

    /**
     * All submissions of a questionnaire, each with its answers per step.
     *
     * @param TPoll_Poll $poll Questionnaire.
     * @return array[] List of array('ballot' => string, 'steps' => array(poll ID => array('answers' => int[], 'value' => int))).
     */
    public static function participations($poll) {
        global $wpdb;

        if (!self::is_survey($poll)) {
            return array();
        }
        $table = $wpdb->prefix . 'tpoll_votes';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, table name is safe.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ballot, poll_id, answer_id, vote_value FROM $table WHERE ballot LIKE %s ORDER BY id ASC",
            $wpdb->esc_like(self::ballot_prefix($poll)) . '%'
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $list = array();
        foreach ((array) $rows as $row) {
            $ballot = (string) $row->ballot;
            $step = (int) $row->poll_id;
            if (!isset($list[$ballot])) {
                $list[$ballot] = array('ballot' => $ballot, 'steps' => array());
            }
            if (!isset($list[$ballot]['steps'][$step])) {
                $list[$ballot]['steps'][$step] = array('answers' => array(), 'value' => (int) $row->vote_value);
            }
            $list[$ballot]['steps'][$step]['answers'][] = (int) $row->answer_id;
        }
        return array_values($list);
    }

    /**
     * Results of all steps, with the number of correct quiz answers when it is known.
     *
     * @param TPoll_Poll $poll  Questionnaire.
     * @param array|null $score array('correct' => int, 'total' => int) right after sending, else null.
     * @return string
     */
    public static function summary_html($poll, $score = null) {
        ob_start();
        ?>
        <div class="tpoll-results tpoll-survey-results">
            <?php if ($score && $score['total'] > 0) : ?>
                <p class="tpoll-survey-score"><?php
                /* translators: 1: number of correct answers, 2: number of quiz questions */
                echo esc_html(sprintf(__('%1$d of %2$d correct.', '3task-polls'), $score['correct'], $score['total']));
                ?></p>
            <?php endif; ?>
            <?php foreach (self::steps($poll) as $step) : ?>
                <?php
                $options = TPoll_Shortcode::display_options($step);
                if (!TPoll_Shortcode::results_visible($step, $options['show_results'], true)) {
                    continue;
                }
                ?>
                <div class="tpoll-survey-result">
                    <p class="tpoll-survey-question"><?php echo esc_html($step->question); ?></p>
                    <?php echo TPoll_Shortcode::results_html($step, TPoll_Vote::get_results($step->id, $step), false); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inside results_html(). ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * AJAX: send all answers of a questionnaire. Either every step is stored or none.
     */
    public static function ajax_submit() {
        if (!check_ajax_referer('tpoll_vote', 'nonce', false) || TPoll_Public::is_foreign_origin()) {
            wp_send_json_error(array('message' => __('Security check failed. Please reload the page and try again.', '3task-polls')));
        }
        if (!empty($_POST['tpoll_hp'])) {
            wp_send_json_error(array('message' => __('Your vote could not be counted.', '3task-polls')));
        }

        $poll = TPoll_Poll::get(isset($_POST['poll_id']) ? absint($_POST['poll_id']) : 0);
        if (!self::is_survey($poll)) {
            wp_send_json_error(array('message' => __('Invalid poll.', '3task-polls')));
        }
        if (!$poll->is_active()) {
            wp_send_json_error(array('message' => __('This poll is not active.', '3task-polls')));
        }
        if ($poll->get_setting('vote_restriction', 'ip') === 'user' && !is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Please log in to vote.', '3task-polls')));
        }
        if (TPoll_Vote::has_voted($poll->id, $poll)) {
            wp_send_json_error(array('message' => __('You have already taken part.', '3task-polls')));
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value goes through absint() below.
        $raw = isset($_POST['steps']) && is_array($_POST['steps']) ? wp_unslash($_POST['steps']) : array();
        $plan = array();
        foreach (self::steps($poll) as $step) {
            $values = isset($raw[$step->id]) ? array_values(array_filter(array_map('absint', (array) $raw[$step->id]))) : array();
            if ($step->type === 'rating') {
                $answers = $step->get_answers();
                if (count($values) !== 1 || $values[0] > 5 || empty($answers)) {
                    wp_send_json_error(array('message' => __('Please answer all questions.', '3task-polls')));
                }
                $plan[] = array($step, array((int) $answers[0]->id), $values[0]);
                continue;
            }
            if (empty($values)) {
                wp_send_json_error(array('message' => __('Please answer all questions.', '3task-polls')));
            }
            $plan[] = array($step, $values, 1);
        }

        $ballot = self::ballot_prefix($poll);
        $ballot .= wp_generate_password(32 - strlen($ballot), false, false);
        $score = array('correct' => 0, 'total' => 0);
        // The rate limit counts one request, not one per step.
        $no_limit = function () {
            return 0;
        };
        foreach ($plan as $index => $item) {
            list($step, $answers, $value) = $item;
            if ($index === 1) {
                add_filter('tpoll_rate_limit', $no_limit);
            }
            $result = TPoll_Vote::cast($step->id, $answers, $value, 0, $ballot);
            if (is_wp_error($result)) {
                remove_filter('tpoll_rate_limit', $no_limit);
                TPoll_Vote::delete_ballot($ballot);
                wp_send_json_error(array('message' => $result->get_error_message()));
            }
            if (TPoll_Quiz::is_quiz($step)) {
                $score['total']++;
                if (in_array((int) $step->get_setting('quiz_correct', 0), $answers, true)) {
                    $score['correct']++;
                }
            }
        }
        remove_filter('tpoll_rate_limit', $no_limit);

        wp_send_json_success(array(
            'message' => __('Thank you for taking part!', '3task-polls'),
            'score' => $score,
            'html' => self::summary_html($poll, $score),
        ));
    }
}

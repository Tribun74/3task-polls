<?php
/**
 * Quiz question: one question, one correct answer, an explanation after the vote
 * and the share of visitors who answered correctly.
 *
 * Uses the poll tables as they are. The correct answer and the explanation live in the poll settings.
 *
 * @package TPoll
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPoll_Quiz {

    /**
     * Type key.
     */
    const TYPE = 'quiz';

    /**
     * Register hooks.
     */
    public static function init() {
        add_filter('tpoll_poll_types', array(__CLASS__, 'register_type'));
        add_filter('tpoll_choice_types', array(__CLASS__, 'add_type'));
        add_filter('tpoll_submit_button_types', array(__CLASS__, 'add_type'));
        add_filter('tpoll_results_visible', array(__CLASS__, 'results_visible'), 10, 4);
        add_filter('tpoll_results', array(__CLASS__, 'add_results'), 10, 2);
        add_filter('tpoll_results_html', array(__CLASS__, 'results_html'), 10, 4);
        add_filter('tpoll_vote_response', array(__CLASS__, 'vote_response'), 10, 3);
        add_action('tpoll_edit_after_answers', array(__CLASS__, 'render_edit_box'));
        add_filter('tpoll_submit_button_text', array(__CLASS__, 'button_text'), 10, 2);
        add_filter('tpoll_save_poll_settings', array(__CLASS__, 'save_settings'), 10, 3);
        add_filter('tpoll_validate_poll', array(__CLASS__, 'validate'), 10, 2);
        add_action('tpoll_poll_saved', array(__CLASS__, 'save_correct_answer'));
    }

    /**
     * Is this poll a quiz question?
     *
     * @param TPoll_Poll|null $poll Poll.
     */
    public static function is_quiz($poll) {
        return $poll && $poll->type === self::TYPE;
    }

    /**
     * Add the type to the type list.
     *
     * @param array $types Type key => label.
     */
    public static function register_type($types) {
        $types[self::TYPE] = __('Quiz question', '3task-polls');
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
     * The solution is shown after the vote, whatever the poll setting says, and never before
     * (the page HTML must not give it away). A closed quiz shows it to everyone.
     *
     * @param bool       $visible      Result of the poll setting.
     * @param TPoll_Poll $poll         Poll.
     * @param string     $show_results Poll setting.
     * @param bool       $has_voted    Visitor has voted.
     */
    public static function results_visible($visible, $poll, $show_results, $has_voted) {
        if (!self::is_quiz($poll)) {
            return $visible;
        }
        return $has_voted || $poll->is_closed();
    }

    /**
     * The correct answer of a quiz question.
     *
     * @param TPoll_Poll $poll Poll.
     * @return object|null Answer row.
     */
    public static function correct_answer($poll) {
        $id = (int) $poll->get_setting('quiz_correct', 0);
        foreach ($poll->get_answers() as $answer) {
            if ((int) $answer->id === $id) {
                return $answer;
            }
        }
        return null;
    }

    /**
     * Tags allowed in the explanation.
     *
     * @return array
     */
    private static function allowed_html() {
        return array(
            'a' => array('href' => true),
            'strong' => array(),
            'em' => array(),
            'br' => array(),
        );
    }

    /**
     * Explanation shown after the vote (sanitized when saved).
     *
     * @param TPoll_Poll $poll Poll.
     * @return string
     */
    public static function explanation($poll) {
        return (string) $poll->get_setting('quiz_explanation', '');
    }

    /**
     * Row index of the marked answer, counted among the answers that are kept (empty rows are dropped).
     *
     * @param array $raw Unslashed POST data.
     * @return int|null Position or null when no kept answer is marked.
     */
    private static function marked_position($raw) {
        if (!isset($raw['quiz_correct'], $raw['answers']) || !is_array($raw['answers']) || $raw['quiz_correct'] === '') {
            return null;
        }
        $marked = (string) $raw['quiz_correct'];
        $position = 0;
        foreach ($raw['answers'] as $key => $answer) {
            if (!is_array($answer) || !isset($answer['text']) || trim((string) $answer['text']) === '') {
                continue;
            }
            if ((string) $key === $marked) {
                return $position;
            }
            $position++;
        }
        return null;
    }

    /**
     * A quiz question is answered, not voted on.
     *
     * @param string     $text Button text.
     * @param TPoll_Poll $poll Poll.
     */
    public static function button_text($text, $poll) {
        return self::is_quiz($poll) ? _x('Answer', 'quiz button', '3task-polls') : $text;
    }

    /**
     * Editor box for the explanation, shown for quiz questions only.
     *
     * @param TPoll_Poll $poll Poll being edited.
     */
    public static function render_edit_box($poll) {
        ?>
        <div class="postbox tpoll-quiz-only<?php echo self::is_quiz($poll) ? '' : ' tpoll-hidden'; ?>" id="tpoll-quiz-box">
            <h2 class="hndle"><?php esc_html_e('Quiz question', '3task-polls'); ?></h2>
            <div class="inside">
                <p class="description"><?php esc_html_e('Mark the correct answer in the list above. After the vote, visitors see whether they were right, the correct answer, the explanation and how many answered correctly. Before the vote, none of this is in the page.', '3task-polls'); ?></p>
                <p>
                    <label for="tpoll-quiz-explanation"><strong><?php esc_html_e('Explanation', '3task-polls'); ?></strong></label><br>
                    <textarea name="quiz_explanation" id="tpoll-quiz-explanation" rows="4"><?php echo esc_textarea(self::explanation($poll)); ?></textarea>
                    <span class="description"><?php esc_html_e('Optional. Shown after the vote. Links, bold and italic are allowed.', '3task-polls'); ?></span>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Remember which of the kept answers is marked as correct. Its ID is only known after saving.
     *
     * @param array      $settings Sanitized settings.
     * @param array      $raw      Unslashed POST data.
     * @param TPoll_Poll $poll     Poll being saved.
     */
    public static function save_settings($settings, $raw, $poll) {
        if (!self::is_quiz($poll)) {
            return $settings;
        }
        $position = self::marked_position($raw);
        if (null !== $position) {
            $settings['quiz_correct_pos'] = $position;
        }
        $settings['quiz_explanation'] = isset($raw['quiz_explanation']) ? trim(wp_kses((string) $raw['quiz_explanation'], self::allowed_html())) : '';
        return $settings;
    }

    /**
     * A quiz question needs a marked answer.
     *
     * @param true|WP_Error $valid Result so far.
     * @param TPoll_Poll    $poll  Poll with the new values.
     */
    public static function validate($valid, $poll) {
        if (is_wp_error($valid) || !self::is_quiz($poll)) {
            return $valid;
        }
        if (null === $poll->get_setting('quiz_correct_pos')) {
            return new WP_Error('quiz_correct_missing', __('Please mark the correct answer.', '3task-polls'));
        }
        return $valid;
    }

    /**
     * Store the ID of the marked answer once the answers have their IDs.
     *
     * @param TPoll_Poll $poll Saved poll.
     */
    public static function save_correct_answer($poll) {
        if (!self::is_quiz($poll)) {
            return;
        }
        $position = $poll->get_setting('quiz_correct_pos');
        unset($poll->settings['quiz_correct_pos']);
        $answers = array_values($poll->get_answers());
        if (null !== $position && isset($answers[(int) $position])) {
            $poll->settings['quiz_correct'] = (int) $answers[(int) $position]->id;
        }
        $poll->save();
    }

    /**
     * Correct answer and share of correct answers in the results.
     *
     * @param array      $results Results.
     * @param TPoll_Poll $poll    Poll.
     */
    public static function add_results($results, $poll) {
        if (!self::is_quiz($poll) || !is_array($results)) {
            return $results;
        }
        $correct = (int) $poll->get_setting('quiz_correct', 0);
        $results['correct_answer'] = $correct;
        $results['correct_share'] = 0.0;
        foreach ($results['answers'] as $answer) {
            if ((int) $answer['id'] === $correct) {
                $results['correct_share'] = (float) $answer['percentage'];
            }
        }
        return $results;
    }

    /**
     * Results of a quiz question: bars with the correct answer marked, the share of correct answers
     * and the explanation.
     *
     * @param string     $html        Empty string.
     * @param TPoll_Poll $poll        Poll.
     * @param array      $results     Results.
     * @param bool       $show_voters Show the voter count.
     */
    public static function results_html($html, $poll, $results, $show_voters) {
        if (!self::is_quiz($poll)) {
            return $html;
        }
        $correct = isset($results['correct_answer']) ? (int) $results['correct_answer'] : 0;
        $explanation = self::explanation($poll);

        ob_start();
        ?>
        <div class="tpoll-results tpoll-quiz-results">
            <p class="tpoll-quiz-share"><?php
            printf(
                /* translators: %s: share of correct answers, for example "75.0 %" */
                esc_html__('%s answered correctly.', '3task-polls'),
                esc_html(number_format_i18n((float) ($results['correct_share'] ?? 0), 1)) . '&nbsp;%'
            );
            ?></p>
            <?php foreach ($results['answers'] as $answer) : ?>
                <?php $is_correct = (int) $answer['id'] === $correct; ?>
                <div class="tpoll-result-item<?php echo $is_correct ? ' tpoll-result-correct' : ''; ?>">
                    <div class="tpoll-result-text">
                        <span class="tpoll-result-answer"><?php echo esc_html($answer['text']); ?></span>
                        <?php if ($is_correct) : ?>
                            <span class="tpoll-correct-label"><?php esc_html_e('Correct answer', '3task-polls'); ?></span>
                        <?php endif; ?>
                        <span class="tpoll-result-percent"><?php echo esc_html(number_format_i18n((float) $answer['percentage'], 1)); ?>&nbsp;%</span>
                    </div>
                    <div class="tpoll-result-bar-wrap" aria-hidden="true">
                        <div class="tpoll-result-bar" style="width: <?php echo esc_attr((string) min(100, (float) $answer['percentage'])); ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($explanation !== '') : ?>
                <div class="tpoll-quiz-explanation"><?php echo wp_kses(nl2br($explanation, false), self::allowed_html()); ?></div>
            <?php endif; ?>
            <?php if ($show_voters) : ?>
                <div class="tpoll-total-voters">
                    <?php
                    printf(
                        /* translators: %d: number of voters */
                        esc_html(_n('%d voter', '%d voters', (int) $results['total_voters'], '3task-polls')),
                        absint($results['total_voters'])
                    );
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Tell the visitor right after the vote whether the answer was correct.
     *
     * @param array      $data    Response data.
     * @param TPoll_Poll $poll    Poll.
     * @param int[]      $answers Submitted answer IDs.
     */
    public static function vote_response($data, $poll, $answers) {
        if (!self::is_quiz($poll)) {
            return $data;
        }
        $correct = in_array((int) $poll->get_setting('quiz_correct', 0), array_map('intval', (array) $answers), true);
        $data['verdict'] = $correct ? 'correct' : 'wrong';
        $data['message'] = $correct ? __('Correct!', '3task-polls') : __('Not quite.', '3task-polls');
        return $data;
    }
}

<?php
/**
 * Poll Edit View
 *
 * @package TPoll
 */

if (!defined('ABSPATH')) {
    exit;
}

$tpoll_is_new = !$poll->id;
$tpoll_page_title = $tpoll_is_new ? __('Add New Poll', '3task-polls') : __('Edit Poll', '3task-polls');
?>
<div class="wrap tpoll-wrap tpoll-edit-wrap">
    <h1 class="wp-heading-inline"><?php echo esc_html($tpoll_page_title); ?></h1>

    <a href="<?php echo esc_url(admin_url('admin.php?page=tpoll')); ?>" class="page-title-action">
        <?php esc_html_e('Back to Polls', '3task-polls'); ?>
    </a>

    <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, read-only. ?>
    <?php if (isset($_GET['saved'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Poll saved successfully!', '3task-polls'); ?></p>
        </div>
    <?php endif; ?>

    <hr class="wp-header-end">

    <form id="tpoll-edit-form" method="post" class="tpoll-form-wrap">
        <input type="hidden" name="poll_id" value="<?php echo esc_attr($poll->id); ?>">

        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-2">

                <!-- Main Content -->
                <div id="post-body-content">
                    <div id="titlediv">
                        <input type="text"
                               name="title"
                               id="title"
                               value="<?php echo esc_attr($poll->title); ?>"
                               placeholder="<?php esc_attr_e('Poll Title', '3task-polls'); ?>"
                               class="tpoll-title-input"
                               required>
                    </div>

                    <div class="tpoll-question-wrap">
                        <label for="question"><?php esc_html_e('Poll Question', '3task-polls'); ?></label>
                        <textarea name="question"
                                  id="question"
                                  rows="3"
                                  placeholder="<?php esc_attr_e('Enter your poll question...', '3task-polls'); ?>"
                                  required><?php echo esc_textarea($poll->question); ?></textarea>
                    </div>

                    <!-- Poll Type -->
                    <div class="tpoll-type-wrap postbox">
                        <h2 class="hndle"><?php esc_html_e('Poll Type', '3task-polls'); ?></h2>
                        <div class="inside">
                            <div class="tpoll-type-options">
                                <?php foreach (TPoll_Poll::get_types() as $tpoll_type_value => $tpoll_type_label) : ?>
                                    <label class="tpoll-type-option <?php echo esc_attr($poll->type === $tpoll_type_value ? 'selected' : ''); ?>">
                                        <input type="radio"
                                               name="type"
                                               value="<?php echo esc_attr($tpoll_type_value); ?>"
                                               <?php checked($poll->type, $tpoll_type_value); ?>>
                                        <span class="tpoll-type-icon tpoll-icon-<?php echo esc_attr($tpoll_type_value); ?>"></span>
                                        <span class="tpoll-type-label"><?php echo esc_html($tpoll_type_label); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Answers -->
                    <div class="tpoll-answers-wrap postbox" id="tpoll-answers-section">
                        <h2 class="hndle"><?php esc_html_e('Poll Answers', '3task-polls'); ?></h2>
                        <div class="inside">
                            <div id="tpoll-answers-list" class="tpoll-answers-list">
                                <?php
                                $tpoll_answers = $poll->get_answers();
                                if (empty($tpoll_answers)) {
                                    $tpoll_answers = array(
                                        (object) array('id' => 0, 'answer_text' => '', 'answer_image' => ''),
                                        (object) array('id' => 0, 'answer_text' => '', 'answer_image' => '')
                                    );
                                }

                                foreach ($tpoll_answers as $tpoll_index => $tpoll_answer) :
                                ?>
                                    <div class="tpoll-answer-row" data-index="<?php echo esc_attr($tpoll_index); ?>">
                                        <span class="tpoll-answer-handle dashicons dashicons-move"></span>
                                        <input type="text"
                                               name="answers[<?php echo esc_attr($tpoll_index); ?>][text]"
                                               value="<?php echo esc_attr($tpoll_answer->answer_text); ?>"
                                               <?php /* translators: %d: answer number */ ?>
                                               placeholder="<?php printf(esc_attr__('Answer %d', '3task-polls'), absint($tpoll_index + 1)); ?>"
                                               class="tpoll-answer-input">
                                        <button type="button" class="tpoll-remove-answer button-link-delete" title="<?php esc_attr_e('Remove', '3task-polls'); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <button type="button" id="tpoll-add-answer" class="button">
                                <span class="dashicons dashicons-plus-alt2"></span>
                                <?php esc_html_e('Add Answer', '3task-polls'); ?>
                            </button>

                            <p class="description tpoll-rating-hint tpoll-hidden">
                                <?php esc_html_e('Rating and Voting polls use the question as the item to rate/vote on.', '3task-polls'); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div id="postbox-container-1" class="postbox-container">

                    <!-- Publish Box -->
                    <div class="postbox">
                        <h2 class="hndle"><?php esc_html_e('Publish', '3task-polls'); ?></h2>
                        <div class="inside">
                            <div class="tpoll-status-wrap">
                                <label for="status"><?php esc_html_e('Status', '3task-polls'); ?></label>
                                <select name="status" id="status">
                                    <?php foreach (TPoll_Poll::get_statuses() as $tpoll_status_value => $tpoll_status_label) : ?>
                                        <option value="<?php echo esc_attr($tpoll_status_value); ?>" <?php selected($poll->status, $tpoll_status_value); ?>>
                                            <?php echo esc_html($tpoll_status_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($poll->id) : ?>
                                <div class="tpoll-shortcode-wrap">
                                    <label><?php esc_html_e('Shortcode', '3task-polls'); ?></label>
                                    <code class="tpoll-shortcode-display">[tpoll id="<?php echo esc_attr($poll->id); ?>"]</code>
                                </div>

                                <div class="tpoll-stats-wrap">
                                    <span><?php
                                        /* translators: %d: number of total votes */
                                        printf(esc_html__('Total Votes: %d', '3task-polls'), absint($poll->get_total_votes()));
                                    ?></span>
                                    <span><?php
                                        /* translators: %d: number of total voters */
                                        printf(esc_html__('Total Voters: %d', '3task-polls'), absint($poll->get_total_voters()));
                                    ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="tpoll-publish-actions">
                                <?php if ($poll->id) : ?>
                                    <a href="#" class="tpoll-reset-votes submitdelete" data-poll-id="<?php echo esc_attr($poll->id); ?>">
                                        <?php esc_html_e('Reset Votes', '3task-polls'); ?>
                                    </a>
                                <?php endif; ?>
                                <button type="submit" class="button button-primary button-large" id="tpoll-save">
                                    <?php echo $tpoll_is_new ? esc_html__('Create Poll', '3task-polls') : esc_html__('Update Poll', '3task-polls'); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule Box -->
                    <div class="postbox">
                        <h2 class="hndle"><?php esc_html_e('Schedule', '3task-polls'); ?></h2>
                        <div class="inside">
                            <p>
                                <label for="start_date"><?php esc_html_e('Start Date', '3task-polls'); ?></label>
                                <input type="datetime-local"
                                       name="start_date"
                                       id="start_date"
                                       value="<?php echo $poll->start_date ? esc_attr(gmdate('Y-m-d\TH:i', strtotime($poll->start_date))) : ''; ?>"
                                       class="widefat">
                            </p>
                            <p>
                                <label for="end_date"><?php esc_html_e('End Date', '3task-polls'); ?></label>
                                <input type="datetime-local"
                                       name="end_date"
                                       id="end_date"
                                       value="<?php echo $poll->end_date ? esc_attr(gmdate('Y-m-d\TH:i', strtotime($poll->end_date))) : ''; ?>"
                                       class="widefat">
                            </p>
                            <p class="description">
                                <?php esc_html_e('Leave empty for no time restriction.', '3task-polls'); ?>
                            </p>
                        </div>
                    </div>

                    <!-- Settings Box -->
                    <div class="postbox">
                        <h2 class="hndle"><?php esc_html_e('Poll Settings', '3task-polls'); ?></h2>
                        <div class="inside">
                            <p>
                                <label for="vote_restriction"><?php esc_html_e('Vote Restriction', '3task-polls'); ?></label>
                                <select name="vote_restriction" id="vote_restriction" class="widefat">
                                    <option value="ip" <?php selected($poll->get_setting('vote_restriction', 'ip'), 'ip'); ?>>
                                        <?php esc_html_e('By IP Address (DSGVO-compliant)', '3task-polls'); ?>
                                    </option>
                                    <option value="cookie" <?php selected($poll->get_setting('vote_restriction'), 'cookie'); ?>>
                                        <?php esc_html_e('By Cookie', '3task-polls'); ?>
                                    </option>
                                    <option value="user" <?php selected($poll->get_setting('vote_restriction'), 'user'); ?>>
                                        <?php esc_html_e('By User Account', '3task-polls'); ?>
                                    </option>
                                </select>
                            </p>
                            <p>
                                <label for="show_results"><?php esc_html_e('Show Results', '3task-polls'); ?></label>
                                <select name="show_results" id="show_results" class="widefat">
                                    <option value="after_vote" <?php selected($poll->get_setting('show_results', 'after_vote'), 'after_vote'); ?>>
                                        <?php esc_html_e('After Voting', '3task-polls'); ?>
                                    </option>
                                    <option value="always" <?php selected($poll->get_setting('show_results'), 'always'); ?>>
                                        <?php esc_html_e('Always', '3task-polls'); ?>
                                    </option>
                                    <option value="never" <?php selected($poll->get_setting('show_results'), 'never'); ?>>
                                        <?php esc_html_e('Never', '3task-polls'); ?>
                                    </option>
                                </select>
                            </p>
                            <p>
                                <label>
                                    <input type="checkbox" name="show_voters_count" value="1" <?php checked($poll->get_setting('show_voters_count', true)); ?>>
                                    <?php esc_html_e('Show total voters count', '3task-polls'); ?>
                                </label>
                            </p>
                            <p>
                                <label>
                                    <input type="checkbox" name="randomize_answers" value="1" <?php checked($poll->get_setting('randomize_answers', false)); ?>>
                                    <?php esc_html_e('Randomize answer order', '3task-polls'); ?>
                                </label>
                            </p>
                            <p class="tpoll-multiple-only tpoll-hidden">
                                <label for="max_choices"><?php esc_html_e('Max Choices', '3task-polls'); ?></label>
                                <input type="number"
                                       name="max_choices"
                                       id="max_choices"
                                       value="<?php echo esc_attr($poll->get_setting('max_choices', 0)); ?>"
                                       min="0"
                                       class="small-text">
                                <span class="description"><?php esc_html_e('0 = unlimited', '3task-polls'); ?></span>
                            </p>
                        </div>
                    </div>

                    <!-- Appearance Box -->
                    <div class="postbox">
                        <h2 class="hndle"><?php esc_html_e('Appearance', '3task-polls'); ?></h2>
                        <div class="inside">
                            <p>
                                <label for="theme"><?php esc_html_e('Theme', '3task-polls'); ?></label>
                                <select name="theme" id="theme" class="widefat">
                                    <option value="default" <?php selected($poll->get_setting('theme', 'default'), 'default'); ?>>
                                        <?php esc_html_e('Default', '3task-polls'); ?>
                                    </option>
                                    <option value="minimal" <?php selected($poll->get_setting('theme'), 'minimal'); ?>>
                                        <?php esc_html_e('Minimal', '3task-polls'); ?>
                                    </option>
                                    <option value="rounded" <?php selected($poll->get_setting('theme'), 'rounded'); ?>>
                                        <?php esc_html_e('Rounded', '3task-polls'); ?>
                                    </option>
                                    <option value="square" <?php selected($poll->get_setting('theme'), 'square'); ?>>
                                        <?php esc_html_e('Square', '3task-polls'); ?>
                                    </option>
                                    <option value="pill" <?php selected($poll->get_setting('theme'), 'pill'); ?>>
                                        <?php esc_html_e('Pill', '3task-polls'); ?>
                                    </option>
                                </select>
                            </p>
                            <p>
                                <label for="dark_mode"><?php esc_html_e('Dark Mode', '3task-polls'); ?></label>
                                <select name="dark_mode" id="dark_mode" class="widefat">
                                    <option value="auto" <?php selected($poll->get_setting('dark_mode', 'auto'), 'auto'); ?>>
                                        <?php esc_html_e('Auto (follow system)', '3task-polls'); ?>
                                    </option>
                                    <option value="yes" <?php selected($poll->get_setting('dark_mode'), 'yes'); ?>>
                                        <?php esc_html_e('Always Dark', '3task-polls'); ?>
                                    </option>
                                    <option value="no" <?php selected($poll->get_setting('dark_mode'), 'no'); ?>>
                                        <?php esc_html_e('Always Light', '3task-polls'); ?>
                                    </option>
                                </select>
                            </p>
                            <p>
                                <label for="bar_color"><?php esc_html_e('Bar Color', '3task-polls'); ?></label>
                                <input type="text"
                                       name="bar_color"
                                       id="bar_color"
                                       value="<?php echo esc_attr($poll->get_style('bar_color', '#4F46E5')); ?>"
                                       class="tpoll-color-picker"
                                       data-default-color="#4F46E5">
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </form>
</div>

<script type="text/html" id="tmpl-tpoll-answer-row">
    <div class="tpoll-answer-row" data-index="{{data.index}}">
        <span class="tpoll-answer-handle dashicons dashicons-move"></span>
        <input type="text"
               name="answers[{{data.index}}][text]"
               value=""
               <?php /* translators: %s: answer number placeholder */ ?>
               placeholder="<?php printf(esc_attr__('Answer %s', '3task-polls'), '{{data.num}}'); ?>"
               class="tpoll-answer-input">
        <button type="button" class="tpoll-remove-answer button-link-delete" title="<?php esc_attr_e('Remove', '3task-polls'); ?>">
            <span class="dashicons dashicons-trash"></span>
        </button>
    </div>
</script>

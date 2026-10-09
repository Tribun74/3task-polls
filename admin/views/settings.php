<?php
/**
 * Settings View
 *
 * @package TPoll
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Template variable, intentional.
$tpoll_settings = get_option('tpoll_settings', array());
?>

<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WP core settings-updated flag. ?>
<?php if (isset($_GET['settings-updated'])) : ?>
    <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e('Settings saved successfully!', '3task-polls'); ?></p>
    </div>
<?php endif; ?>

<form method="post" action="options.php">
    <?php settings_fields('tpoll_settings_group'); ?>

    <div class="tpoll-settings-wrap">
        <div class="tpoll-settings-main">

            <!-- Voting Settings -->
            <div class="tpoll-card">
                <div class="tpoll-card-header">
                    <h3 class="tpoll-card-title">
                        <span class="tpoll-card-title-icon">&#x1F5F3;</span>
                        <?php esc_html_e('Voting Settings', '3task-polls'); ?>
                    </h3>
                </div>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="vote_restriction"><?php esc_html_e('Default Vote Restriction', '3task-polls'); ?></label>
                        </th>
                        <td>
                            <select name="tpoll_settings[vote_restriction]" id="vote_restriction">
                                <option value="ip" <?php selected($tpoll_settings['vote_restriction'] ?? 'ip', 'ip'); ?>>
                                    <?php esc_html_e('One vote per IP address (hashed, no cookie)', '3task-polls'); ?>
                                </option>
                                <option value="cookie" <?php selected($tpoll_settings['vote_restriction'] ?? '', 'cookie'); ?>>
                                    <?php esc_html_e('One vote per browser (cookie, easy to bypass)', '3task-polls'); ?>
                                </option>
                                <option value="user" <?php selected($tpoll_settings['vote_restriction'] ?? '', 'user'); ?>>
                                    <?php esc_html_e('Logged-in users only, one vote per account', '3task-polls'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Used for new polls. The plain IP address is never stored, only a salted SHA-256 hash.', '3task-polls'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="show_results"><?php esc_html_e('Default Results Display', '3task-polls'); ?></label>
                        </th>
                        <td>
                            <select name="tpoll_settings[show_results]" id="show_results">
                                <option value="after_vote" <?php selected($tpoll_settings['show_results'] ?? 'after_vote', 'after_vote'); ?>>
                                    <?php esc_html_e('After Voting', '3task-polls'); ?>
                                </option>
                                <option value="always" <?php selected($tpoll_settings['show_results'] ?? '', 'always'); ?>>
                                    <?php esc_html_e('Always Visible', '3task-polls'); ?>
                                </option>
                                <option value="never" <?php selected($tpoll_settings['show_results'] ?? '', 'never'); ?>>
                                    <?php esc_html_e('Never Show', '3task-polls'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Other Options', '3task-polls'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="tpoll_settings[allow_revote]"
                                       value="1"
                                       <?php checked(!empty($tpoll_settings['allow_revote'])); ?>>
                                <?php esc_html_e('Allow voters to change their vote (default for new polls)', '3task-polls'); ?>
                            </label>
                            <br><br>
                            <label>
                                <input type="checkbox"
                                       name="tpoll_settings[show_voters_count]"
                                       value="1"
                                       <?php checked($tpoll_settings['show_voters_count'] ?? true); ?>>
                                <?php esc_html_e('Show total voters count', '3task-polls'); ?>
                            </label>
                            <br><br>
                            <label>
                                <input type="checkbox"
                                       name="tpoll_settings[randomize_answers]"
                                       value="1"
                                       <?php checked(!empty($tpoll_settings['randomize_answers'])); ?>>
                                <?php esc_html_e('Randomize answer order by default', '3task-polls'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Feedback below posts -->
            <div class="tpoll-card">
                <div class="tpoll-card-header">
                    <h3 class="tpoll-card-title">
                        <span class="tpoll-card-title-icon">&#x1F44D;</span>
                        <?php esc_html_e('Feedback below posts', '3task-polls'); ?>
                    </h3>
                </div>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Show below posts', '3task-polls'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="tpoll_settings[feedback_enabled]" value="1" <?php checked(!empty($tpoll_settings['feedback_enabled'])); ?>>
                                <?php esc_html_e('Ask readers below every post and count the answers per post', '3task-polls'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="feedback_style"><?php esc_html_e('Style', '3task-polls'); ?></label></th>
                        <td>
                            <select name="tpoll_settings[feedback_style]" id="feedback_style">
                                <option value="helpful" <?php selected($tpoll_settings['feedback_style'] ?? 'helpful', 'helpful'); ?>><?php esc_html_e('Was this post helpful? (thumbs up/down)', '3task-polls'); ?></option>
                                <option value="reactions" <?php selected($tpoll_settings['feedback_style'] ?? 'helpful', 'reactions'); ?>><?php esc_html_e('Reactions (emoji)', '3task-polls'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="feedback_question"><?php esc_html_e('Question', '3task-polls'); ?></label></th>
                        <td>
                            <input type="text" class="regular-text" name="tpoll_settings[feedback_question]" id="feedback_question" value="<?php echo esc_attr($tpoll_settings['feedback_question'] ?? ''); ?>" placeholder="<?php esc_attr_e('Was this post helpful?', '3task-polls'); ?>">
                            <p class="description"><?php esc_html_e('Leave empty for the translated default question.', '3task-polls'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Post types', '3task-polls'); ?></th>
                        <td>
                            <?php
                            $tpoll_fb_types = (array) ($tpoll_settings['feedback_post_types'] ?? array('post'));
                            foreach (get_post_types(array('public' => true), 'objects') as $tpoll_pt) :
                                if ('attachment' === $tpoll_pt->name) {
                                    continue;
                                }
                                ?>
                                <label style="margin-right:14px">
                                    <input type="checkbox" name="tpoll_settings[feedback_post_types][]" value="<?php echo esc_attr($tpoll_pt->name); ?>" <?php checked(in_array($tpoll_pt->name, $tpoll_fb_types, true)); ?>>
                                    <?php echo esc_html($tpoll_pt->labels->name); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description"><?php esc_html_e('No cookie, same privacy rules as your polls. The results per post are in the tab "Feedback".', '3task-polls'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Privacy Settings -->
            <div class="tpoll-card">
                <div class="tpoll-card-header">
                    <h3 class="tpoll-card-title">
                        <span class="tpoll-card-title-icon">&#x1F512;</span>
                        <?php esc_html_e('Privacy', '3task-polls'); ?>
                    </h3>
                </div>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="retention_days"><?php esc_html_e('Forget voter hashes after', '3task-polls'); ?></label>
                        </th>
                        <td>
                            <input type="number" min="1" max="3650" class="small-text"
                                   name="tpoll_settings[retention_days]"
                                   id="retention_days"
                                   value="<?php echo absint($tpoll_settings['retention_days'] ?? 30); ?>">
                            <?php esc_html_e('days after a poll has ended or was closed', '3task-polls'); ?>
                            <p class="description">
                                <?php esc_html_e('The IP and browser hashes are only needed while a poll is open, to prevent duplicate votes. After this period they are removed every day. The vote counts stay.', '3task-polls'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Uninstall', '3task-polls'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="tpoll_settings[delete_on_uninstall]"
                                       value="1"
                                       <?php checked(!empty($tpoll_settings['delete_on_uninstall'])); ?>>
                                <?php esc_html_e('Delete all polls, votes and settings when the plugin is deleted', '3task-polls'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Appearance Settings -->
            <div class="tpoll-card">
                <div class="tpoll-card-header">
                    <h3 class="tpoll-card-title">
                        <span class="tpoll-card-title-icon">&#x1F3A8;</span>
                        <?php esc_html_e('Appearance Settings', '3task-polls'); ?>
                    </h3>
                </div>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="theme"><?php esc_html_e('Default Theme', '3task-polls'); ?></label>
                        </th>
                        <td>
                            <select name="tpoll_settings[theme]" id="theme">
                                <?php foreach (TPoll_Shortcode::themes() as $tpoll_theme_key => $tpoll_theme_label) : ?>
                                    <option value="<?php echo esc_attr($tpoll_theme_key); ?>" <?php selected($tpoll_settings['theme'] ?? 'default', $tpoll_theme_key); ?>>
                                        <?php echo esc_html($tpoll_theme_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="dark_mode"><?php esc_html_e('Dark Mode', '3task-polls'); ?></label>
                        </th>
                        <td>
                            <select name="tpoll_settings[dark_mode]" id="dark_mode">
                                <option value="auto" <?php selected($tpoll_settings['dark_mode'] ?? 'no', 'auto'); ?>>
                                    <?php esc_html_e('Auto (follow system)', '3task-polls'); ?>
                                </option>
                                <option value="yes" <?php selected($tpoll_settings['dark_mode'] ?? '', 'yes'); ?>>
                                    <?php esc_html_e('Always Dark', '3task-polls'); ?>
                                </option>
                                <option value="no" <?php selected($tpoll_settings['dark_mode'] ?? 'no', 'no'); ?>>
                                    <?php esc_html_e('Always Light', '3task-polls'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="animation"><?php esc_html_e('Animation', '3task-polls'); ?></label>
                        </th>
                        <td>
                            <select name="tpoll_settings[animation]" id="animation">
                                <option value="grow" <?php selected(in_array($tpoll_settings['animation'] ?? 'grow', array('grow', 'fade', 'slide'), true)); ?>>
                                    <?php esc_html_e('Growing result bars', '3task-polls'); ?>
                                </option>
                                <option value="none" <?php selected($tpoll_settings['animation'] ?? '', 'none'); ?>>
                                    <?php esc_html_e('None', '3task-polls'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="results_bar_color"><?php esc_html_e('Results Bar Color', '3task-polls'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="tpoll_settings[results_bar_color]"
                                   id="results_bar_color"
                                   value="<?php echo esc_attr($tpoll_settings['results_bar_color'] ?? '#4F46E5'); ?>"
                                   class="tpoll-color-picker"
                                   data-default-color="#4F46E5">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="results_bar_bg"><?php esc_html_e('Results Bar Background', '3task-polls'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="tpoll_settings[results_bar_bg]"
                                   id="results_bar_bg"
                                   value="<?php echo esc_attr($tpoll_settings['results_bar_bg'] ?? '#E5E7EB'); ?>"
                                   class="tpoll-color-picker"
                                   data-default-color="#E5E7EB">
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Text Labels -->
            <div class="tpoll-card">
                <div class="tpoll-card-header">
                    <h3 class="tpoll-card-title">
                        <span class="tpoll-card-title-icon">&#x1F4DD;</span>
                        <?php esc_html_e('Text Labels', '3task-polls'); ?>
                    </h3>
                </div>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="button_text"><?php esc_html_e('Vote Button', '3task-polls'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="tpoll_settings[button_text]"
                                   id="button_text"
                                   value="<?php echo esc_attr($tpoll_settings['button_text'] ?? ''); ?>"
                                   placeholder="<?php esc_attr_e('Vote', '3task-polls'); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="voted_text"><?php esc_html_e('Already Voted Message', '3task-polls'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="tpoll_settings[voted_text]"
                                   id="voted_text"
                                   value="<?php echo esc_attr($tpoll_settings['voted_text'] ?? ''); ?>"
                                   placeholder="<?php esc_attr_e('You have already voted.', '3task-polls'); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="closed_text"><?php esc_html_e('Poll Closed Message', '3task-polls'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="tpoll_settings[closed_text]"
                                   id="closed_text"
                                   value="<?php echo esc_attr($tpoll_settings['closed_text'] ?? ''); ?>"
                                   placeholder="<?php esc_attr_e('This poll is closed.', '3task-polls'); ?>"
                                   class="regular-text">
                            <p class="description"><?php esc_html_e('Leave the text fields empty to use the translated default texts.', '3task-polls'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

        </div>

        <div class="tpoll-settings-sidebar">
            <!-- Support Box -->
            <div class="tpoll-card">
                <div class="tpoll-card-header">
                    <h3 class="tpoll-card-title">
                        <span class="tpoll-card-title-icon">&#x1F4AC;</span>
                        <?php esc_html_e('Support', '3task-polls'); ?>
                    </h3>
                </div>
                <p><?php esc_html_e('Need help? Check out our resources:', '3task-polls'); ?></p>
                <ul class="tpoll-support-links">
                    <li>
                        <a href="https://wordpress.org/support/plugin/3task-polls/" target="_blank" rel="noopener noreferrer">
                            &#x1F4AC; <?php esc_html_e('Support Forum', '3task-polls'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://wordpress.org/plugins/3task-polls/#faq" target="_blank" rel="noopener noreferrer">
                            &#x1F4D6; <?php esc_html_e('FAQ', '3task-polls'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://wordpress.org/plugins/3task-polls/#reviews" target="_blank" rel="noopener noreferrer">
                            &#x2B50; <?php esc_html_e('Rate this Plugin', '3task-polls'); ?>
                        </a>
                    </li>
                </ul>
            </div>

        </div>
    </div>

    <?php submit_button(); ?>
</form>

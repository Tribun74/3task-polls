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
                                    <?php esc_html_e('By IP Address (GDPR-compliant)', '3task-polls'); ?>
                                </option>
                                <option value="cookie" <?php selected($tpoll_settings['vote_restriction'] ?? '', 'cookie'); ?>>
                                    <?php esc_html_e('By Cookie', '3task-polls'); ?>
                                </option>
                                <option value="user" <?php selected($tpoll_settings['vote_restriction'] ?? '', 'user'); ?>>
                                    <?php esc_html_e('By User Account', '3task-polls'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('IP-based voting uses hashed IPs for GDPR compliance.', '3task-polls'); ?>
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
                                <?php esc_html_e('Allow users to change their vote', '3task-polls'); ?>
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
                                <option value="default" <?php selected($tpoll_settings['theme'] ?? 'default', 'default'); ?>>
                                    <?php esc_html_e('Default', '3task-polls'); ?>
                                </option>
                                <option value="minimal" <?php selected($tpoll_settings['theme'] ?? '', 'minimal'); ?>>
                                    <?php esc_html_e('Minimal', '3task-polls'); ?>
                                </option>
                                <option value="rounded" <?php selected($tpoll_settings['theme'] ?? '', 'rounded'); ?>>
                                    <?php esc_html_e('Rounded', '3task-polls'); ?>
                                </option>
                                <option value="square" <?php selected($tpoll_settings['theme'] ?? '', 'square'); ?>>
                                    <?php esc_html_e('Square', '3task-polls'); ?>
                                </option>
                                <option value="pill" <?php selected($tpoll_settings['theme'] ?? '', 'pill'); ?>>
                                    <?php esc_html_e('Pill', '3task-polls'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="dark_mode"><?php esc_html_e('Dark Mode', '3task-polls'); ?></label>
                        </th>
                        <td>
                            <select name="tpoll_settings[dark_mode]" id="dark_mode">
                                <option value="auto" <?php selected($tpoll_settings['dark_mode'] ?? 'auto', 'auto'); ?>>
                                    <?php esc_html_e('Auto (follow system)', '3task-polls'); ?>
                                </option>
                                <option value="yes" <?php selected($tpoll_settings['dark_mode'] ?? '', 'yes'); ?>>
                                    <?php esc_html_e('Always Dark', '3task-polls'); ?>
                                </option>
                                <option value="no" <?php selected($tpoll_settings['dark_mode'] ?? '', 'no'); ?>>
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
                                <option value="fade" <?php selected($tpoll_settings['animation'] ?? 'fade', 'fade'); ?>>
                                    <?php esc_html_e('Fade', '3task-polls'); ?>
                                </option>
                                <option value="slide" <?php selected($tpoll_settings['animation'] ?? '', 'slide'); ?>>
                                    <?php esc_html_e('Slide', '3task-polls'); ?>
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
                                   value="<?php echo esc_attr($tpoll_settings['button_text'] ?? __('Vote', '3task-polls')); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="results_text"><?php esc_html_e('View Results', '3task-polls'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="tpoll_settings[results_text]"
                                   id="results_text"
                                   value="<?php echo esc_attr($tpoll_settings['results_text'] ?? __('View Results', '3task-polls')); ?>"
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
                                   value="<?php echo esc_attr($tpoll_settings['voted_text'] ?? __('You have already voted.', '3task-polls')); ?>"
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
                                   value="<?php echo esc_attr($tpoll_settings['closed_text'] ?? __('This poll is closed.', '3task-polls')); ?>"
                                   class="regular-text">
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
                        <a href="https://www.3task.de/docs/3task-polls/" target="_blank" rel="noopener noreferrer">
                            &#x1F4D6; <?php esc_html_e('Documentation', '3task-polls'); ?>
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

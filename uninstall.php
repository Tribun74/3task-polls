<?php
/**
 * TPoll Uninstall
 *
 * Fired when the plugin is deleted. Data is only removed when the site owner
 * enabled "Delete all polls, votes and settings" in the settings.
 *
 * @package TPoll
 */

// Exit if not called by WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

wp_clear_scheduled_hook('tpoll_daily_cleanup');

// 3task Polls Pro uses the same tables. Keep the data while Pro is installed.
if (file_exists(WP_PLUGIN_DIR . '/3task-polls-pro/3task-polls-pro.php')) {
    return;
}

/**
 * Remove the data of one site.
 */
function tpoll_uninstall_site() {
    global $wpdb;

    $settings = get_option('tpoll_settings', array());
    if (empty($settings['delete_on_uninstall'])) {
        return;
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall cleanup.
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tpoll_votes");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tpoll_answers");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tpoll_polls");
    // phpcs:enable

    delete_option('tpoll_settings');
    delete_option('tpoll_feedback_polls');
    delete_option('tpoll_version');
    delete_option('tpoll_db_version');

    $roles = array('administrator', 'editor');
    $caps = array('manage_tpoll', 'create_tpoll', 'edit_tpoll', 'delete_tpoll', 'view_tpoll_results');

    foreach ($roles as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            foreach ($caps as $cap) {
                $role->remove_cap($cap);
            }
        }
    }
}

if (is_multisite()) {
    $tpoll_site_ids = get_sites(array('fields' => 'ids', 'number' => 0));
    foreach ($tpoll_site_ids as $tpoll_site_id) {
        switch_to_blog($tpoll_site_id);
        tpoll_uninstall_site();
        restore_current_blog();
    }
} else {
    tpoll_uninstall_site();
}

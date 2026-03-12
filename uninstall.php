<?php
/**
 * TPoll Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package TPoll
 */

// Exit if not called by WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user wants to delete data
$tpoll_settings = get_option('tpoll_settings', array());
$tpoll_delete_data = isset($tpoll_settings['delete_on_uninstall']) ? $tpoll_settings['delete_on_uninstall'] : false;

if ($tpoll_delete_data) {
    global $wpdb;

    // Delete tables
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall cleanup.
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tpoll_votes");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tpoll_answers");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tpoll_polls");
    // phpcs:enable

    // Delete options
    delete_option('tpoll_settings');
    delete_option('tpoll_version');
    delete_option('tpoll_db_version');

    // Remove capabilities
    $tpoll_roles = array('administrator', 'editor');
    $tpoll_caps = array('manage_tpoll', 'create_tpoll', 'edit_tpoll', 'delete_tpoll', 'view_tpoll_results');

    foreach ($tpoll_roles as $tpoll_role_name) {
        $tpoll_role = get_role($tpoll_role_name);
        if ($tpoll_role) {
            foreach ($tpoll_caps as $tpoll_cap) {
                $tpoll_role->remove_cap($tpoll_cap);
            }
        }
    }
}

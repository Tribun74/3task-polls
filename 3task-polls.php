<?php
/**
 * Plugin Name:       3task Polls: Cookieless Polls, Star Rating & Voting
 * Plugin URI:        https://wordpress.org/plugins/3task-polls/
 * Description:       Polls, star ratings, reactions and up/down voting that stay on your own server. Feedback below posts. No third-party service, no tracking cookie, IP addresses only as salted hashes. Gutenberg block and shortcode.
 * Version:           1.0.7
 * Author:            3task
 * Author URI:        https://www.3task.de
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       3task-polls
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Tested up to:      7.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if ( ! function_exists( 'tpoll_free_should_stand_down' ) ) {
	/**
	 * True when the old 3task Polls Pro 1.0.x is installed. It was a standalone copy of
	 * this plugin. Pro 2.0 and newer is an add-on and runs together with this plugin.
	 */
	function tpoll_old_pro_installed() {
		$file = WP_PLUGIN_DIR . '/3task-polls-pro/3task-polls-pro.php';
		if ( ! file_exists( $file ) ) {
			return false;
		}
		$data = get_file_data( $file, array( 'Version' => 'Version' ) );
		return ! empty( $data['Version'] ) && version_compare( $data['Version'], '2.0.0', '<' );
	}

	/**
	 * True when 3task Polls Pro 1.0.x (a standalone copy of this plugin) is active.
	 */
	function tpoll_free_should_stand_down() {
		$pro    = '3task-polls-pro/3task-polls-pro.php';
		$active = in_array( $pro, (array) get_option( 'active_plugins', array() ), true );
		if ( ! $active && is_multisite() ) {
			$network = (array) get_site_option( 'active_sitewide_plugins', array() );
			$active  = isset( $network[ $pro ] );
		}
		return $active && tpoll_old_pro_installed();
	}

	/**
	 * Admin notice while the free plugin is paused.
	 */
	function tpoll_free_stand_down_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-info"><p>' . esc_html__( '3task Polls is paused because 3task Polls Pro is active and already contains all poll features. Your polls and votes are not affected.', '3task-polls' ) . '</p></div>';
	}
}

// 3task Polls Pro 1.0.x ships its own copy of these classes. Running both would
// cause a fatal error, so the free plugin steps aside while Pro is active.
if ( tpoll_free_should_stand_down() ) {
	add_action( 'admin_notices', 'tpoll_free_stand_down_notice' );
	return;
}

define( 'TPOLL_PLUGIN_FILE', __FILE__ );

// All classes and functions live in a separate file. PHP declares top-level functions and
// classes of a file before its first line runs, so keeping them here would collide with
// Pro 1.0.x even when the check above returns early.
require_once __DIR__ . '/includes/bootstrap.php';

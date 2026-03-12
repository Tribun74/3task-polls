<?php
/**
 * Plugin Name:       3task Polls – Surveys, Quizzes & Voting
 * Plugin URI:        https://wordpress.org/plugins/3task-polls/
 * Description:       Create polls, surveys, quizzes and voting with 4 poll types. AJAX-based, GDPR-compliant, Gutenberg block included. Lightweight and fully customizable.
 * Version:           1.0.2
 * Author:            3task
 * Author URI:        https://www.3task.de
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       3task-polls
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Tested up to:      6.9
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
if ( ! defined( 'TPOLL_VERSION' ) ) {
	define( 'TPOLL_VERSION', '1.0.2' );
}
define('TPOLL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TPOLL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TPOLL_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('TPOLL_DB_VERSION', '1.0.0');

/**
 * Main TPoll Class
 */
final class TPoll {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Admin instance
     */
    public $admin = null;

    /**
     * Public instance
     */
    public $public = null;

    /**
     * Get instance
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once TPOLL_PLUGIN_DIR . 'includes/class-activator.php';
        require_once TPOLL_PLUGIN_DIR . 'includes/class-deactivator.php';
        require_once TPOLL_PLUGIN_DIR . 'includes/class-poll.php';
        require_once TPOLL_PLUGIN_DIR . 'includes/class-vote.php';
        require_once TPOLL_PLUGIN_DIR . 'includes/class-shortcode.php';

        // Admin classes
        if (is_admin()) {
            require_once TPOLL_PLUGIN_DIR . 'admin/class-admin.php';
            require_once TPOLL_PLUGIN_DIR . 'admin/class-polls-list.php';
        }

        // Public classes
        require_once TPOLL_PLUGIN_DIR . 'public/class-public.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin
        if (is_admin()) {
            $this->admin = new TPoll_Admin();
        }

        // Public
        $this->public = new TPoll_Public();

        // Shortcode
        new TPoll_Shortcode();

        // Register block
        add_action('init', array($this, 'register_block'));
    }

    /**
     * Register Gutenberg block
     */
    public function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        // Register block script
        wp_register_script(
            'tpoll-block-editor',
            TPOLL_PLUGIN_URL . 'blocks/poll-block/index.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render'),
            TPOLL_VERSION,
            true
        );

        // Get polls for block
        $polls = TPoll_Poll::get_all(array('status' => 'published', 'per_page' => 100));
        $poll_options = array(
            array('value' => 0, 'label' => __('-- Select Poll --', '3task-polls'))
        );

        foreach ($polls as $poll) {
            $poll_options[] = array(
                'value' => $poll->id,
                'label' => $poll->title
            );
        }

        wp_localize_script('tpoll-block-editor', 'tpollBlockData', array(
            'polls' => $poll_options
        ));

        // Register block style
        wp_register_style(
            'tpoll-block-editor',
            TPOLL_PLUGIN_URL . 'blocks/poll-block/editor.css',
            array(),
            TPOLL_VERSION
        );

        // Register block type
        register_block_type('tpoll/poll', array(
            'editor_script' => 'tpoll-block-editor',
            'editor_style' => 'tpoll-block-editor',
            'render_callback' => array($this, 'render_block'),
            'attributes' => array(
                'pollId' => array(
                    'type' => 'number',
                    'default' => 0
                )
            )
        ));
    }

    /**
     * Render block callback
     */
    public function render_block($attributes) {
        if (empty($attributes['pollId'])) {
            return '<p>' . esc_html__('Please select a poll.', '3task-polls') . '</p>';
        }

        return do_shortcode('[tpoll id="' . absint($attributes['pollId']) . '"]');
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialize
     */
    public function __wakeup() {
        throw new Exception('Cannot unserialize singleton');
    }
}

/**
 * Activation hook
 */
function tpoll_activate() {
    require_once TPOLL_PLUGIN_DIR . 'includes/class-activator.php';
    TPoll_Activator::activate();
}
register_activation_hook(__FILE__, 'tpoll_activate');

/**
 * Deactivation hook
 */
function tpoll_deactivate() {
    require_once TPOLL_PLUGIN_DIR . 'includes/class-deactivator.php';
    TPoll_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'tpoll_deactivate');

/**
 * Initialize plugin
 */
function tpoll() {
    return TPoll::instance();
}

/**
 * Plugin action links
 *
 * @param array $links Existing links.
 * @return array
 */
function tpoll_plugin_action_links( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=tpoll' ) ) . '">'
		. esc_html__( 'Settings', '3task-polls' )
		. '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'tpoll_plugin_action_links' );

// Start plugin
add_action('plugins_loaded', 'tpoll');

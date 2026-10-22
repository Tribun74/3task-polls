<?php
/**
 * 3task Polls bootstrap: constants, main class and hooks.
 * Loaded by 3task-polls.php after the check for 3task Polls Pro 1.0.x.
 *
 * @package TPoll
 */

if (!defined('ABSPATH')) {
    exit;
}

if ( ! defined( 'TPOLL_VERSION' ) ) {
	define( 'TPOLL_VERSION', '1.1.0' );
}
define('TPOLL_PLUGIN_DIR', plugin_dir_path(TPOLL_PLUGIN_FILE));
define('TPOLL_PLUGIN_URL', plugin_dir_url(TPOLL_PLUGIN_FILE));
define('TPOLL_PLUGIN_BASENAME', plugin_basename(TPOLL_PLUGIN_FILE));
define('TPOLL_DB_VERSION', '1.2.0');

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
        require_once TPOLL_PLUGIN_DIR . 'includes/class-feedback.php';
        require_once TPOLL_PLUGIN_DIR . 'includes/class-quiz.php';
        require_once TPOLL_PLUGIN_DIR . 'includes/class-survey.php';

        // Admin classes
        if (is_admin()) {
            require_once TPOLL_PLUGIN_DIR . 'admin/class-admin.php';
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

        // Feedback below posts
        TPoll_Feedback::init();

        // Quiz question type
        TPoll_Quiz::init();

        // Questionnaire type
        TPoll_Survey::init();

        // Bundled German translation, used until translate.wordpress.org provides one.
        add_action('init', array($this, 'load_textdomain'), 1);

        // Register block
        add_action('init', array($this, 'register_block'));
        add_action('enqueue_block_editor_assets', array($this, 'block_editor_data'));

        // Updates, retention and privacy
        add_action('plugins_loaded', array('TPoll_Activator', 'maybe_upgrade'), 20);
        add_action('tpoll_daily_cleanup', array($this, 'daily_cleanup'));
        add_filter('wp_privacy_personal_data_exporters', array($this, 'register_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array($this, 'register_eraser'));
        add_action('admin_init', array($this, 'privacy_policy_text'));
    }

    /**
     * Load the bundled translations. Files from translate.wordpress.org in
     * wp-content/languages/plugins take precedence.
     */
    public function load_textdomain() {
        // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Bundled de_DE files until the official language pack exists.
        load_plugin_textdomain('3task-polls', false, dirname(TPOLL_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Remove voter fingerprints after the retention period.
     */
    public function daily_cleanup() {
        $settings = get_option('tpoll_settings', array());
        $days = isset($settings['retention_days']) ? absint($settings['retention_days']) : 30;
        TPoll_Vote::forget_fingerprints($days);
    }

    /**
     * Register personal data exporter (votes of registered users).
     */
    public function register_exporter($exporters) {
        $exporters['3task-polls'] = array(
            'exporter_friendly_name' => __('3task Polls votes', '3task-polls'),
            'callback' => array($this, 'export_personal_data'),
        );
        return $exporters;
    }

    /**
     * Register personal data eraser.
     */
    public function register_eraser($erasers) {
        $erasers['3task-polls'] = array(
            'eraser_friendly_name' => __('3task Polls votes', '3task-polls'),
            'callback' => array($this, 'erase_personal_data'),
        );
        return $erasers;
    }

    /**
     * Export votes linked to a user account.
     */
    public function export_personal_data($email, $page = 1) {
        global $wpdb;

        $user = get_user_by('email', $email);
        $items = array();

        if ($user) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables.
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT v.id, v.created_at, v.vote_value, p.title, a.answer_text
                 FROM {$wpdb->prefix}tpoll_votes v
                 LEFT JOIN {$wpdb->prefix}tpoll_polls p ON p.id = v.poll_id
                 LEFT JOIN {$wpdb->prefix}tpoll_answers a ON a.id = v.answer_id
                 WHERE v.user_id = %d",
                $user->ID
            ));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            foreach ((array) $rows as $row) {
                $items[] = array(
                    'group_id' => 'tpoll-votes',
                    'group_label' => __('Poll votes', '3task-polls'),
                    'item_id' => 'tpoll-vote-' . $row->id,
                    'data' => array(
                        array('name' => __('Poll', '3task-polls'), 'value' => (string) $row->title),
                        array('name' => __('Answer', '3task-polls'), 'value' => (string) $row->answer_text),
                        array('name' => __('Value', '3task-polls'), 'value' => (string) $row->vote_value),
                        array('name' => __('Date', '3task-polls'), 'value' => (string) $row->created_at),
                    ),
                );
            }
        }

        return array('data' => $items, 'done' => true);
    }

    /**
     * Unlink votes from a user account. The anonymous vote count stays.
     */
    public function erase_personal_data($email, $page = 1) {
        global $wpdb;

        $user = get_user_by('email', $email);
        $removed = 0;

        if ($user) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
            $removed = (int) $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}tpoll_votes SET user_id = NULL, ip_hash = '', user_agent_hash = NULL WHERE user_id = %d",
                $user->ID
            ));
        }

        return array(
            'items_removed' => $removed > 0,
            'items_retained' => false,
            'messages' => array(),
            'done' => true,
        );
    }

    /**
     * Suggested text for the privacy policy.
     */
    public function privacy_policy_text() {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        $content = '<p>' . esc_html__('When you vote in a poll, this site stores your answer, the time and a salted SHA-256 hash of your IP address and browser identifier to prevent duplicate votes. The plain IP address is not stored. If you are logged in, the vote is linked to your user account. If the poll uses the cookie restriction, a cookie named tpoll_voted_ followed by the poll number is set for one year. The hashes are removed automatically after the poll has ended and the retention period set by the site owner has passed. No data is sent to third parties.', '3task-polls') . '</p>';

        wp_add_privacy_policy_content('3task Polls', wp_kses_post($content));
    }

    /**
     * Register Gutenberg block from block.json
     */
    public function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type(TPOLL_PLUGIN_DIR . 'blocks/poll-block', array(
            'render_callback' => array($this, 'render_block'),
            // Front-end styles also in the editor preview.
            'style' => 'tpoll-frontend',
        ));

        $handle = function_exists('generate_block_asset_handle') ? generate_block_asset_handle('tpoll/poll', 'editorScript') : '';
        if ($handle && function_exists('wp_set_script_translations')) {
            wp_set_script_translations($handle, '3task-polls', TPOLL_PLUGIN_DIR . 'languages');
        }
    }

    /**
     * Poll list for the block, only loaded in the editor.
     */
    public function block_editor_data() {
        $polls = TPoll_Poll::get_all(array('per_page' => 200, 'orderby' => 'title', 'order' => 'ASC'));
        $poll_options = array(
            array('value' => 0, 'label' => __('-- Select Poll --', '3task-polls'))
        );

        foreach ($polls as $poll) {
            $label = $poll->title;
            if ($poll->status !== 'published') {
                $statuses = TPoll_Poll::get_statuses();
                $label .= ' (' . (isset($statuses[$poll->status]) ? $statuses[$poll->status] : $poll->status) . ')';
            }
            $poll_options[] = array(
                'value' => (int) $poll->id,
                'label' => $label
            );
        }

        $handle = generate_block_asset_handle('tpoll/poll', 'editorScript');
        wp_add_inline_script($handle, 'window.tpollBlockData = ' . wp_json_encode(array('polls' => $poll_options)) . ';', 'before');
    }

    /**
     * Render block callback
     */
    public function render_block($attributes) {
        if (empty($attributes['pollId'])) {
            if (defined('REST_REQUEST') && REST_REQUEST) {
                return '<p>' . esc_html__('Please select a poll.', '3task-polls') . '</p>';
            }
            return '';
        }

        $html = do_shortcode('[tpoll id="' . absint($attributes['pollId']) . '"]');
        if ($html === '') {
            return '';
        }

        $wrapper = function_exists('get_block_wrapper_attributes') ? get_block_wrapper_attributes() : 'class="wp-block-tpoll-poll"';
        return '<div ' . $wrapper . '>' . $html . '</div>';
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
register_activation_hook(TPOLL_PLUGIN_FILE, 'tpoll_activate');

/**
 * Deactivation hook
 */
function tpoll_deactivate() {
    require_once TPOLL_PLUGIN_DIR . 'includes/class-deactivator.php';
    TPoll_Deactivator::deactivate();
}
register_deactivation_hook(TPOLL_PLUGIN_FILE, 'tpoll_deactivate');

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
	$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=tpoll&tab=settings' ) ) . '">'
		. esc_html__( 'Settings', '3task-polls' )
		. '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . TPOLL_PLUGIN_BASENAME, 'tpoll_plugin_action_links' );

/**
 * Only for the old 3task Polls Pro 1.0.x, which contains a full copy of this plugin. WordPress loads the Pro file
 * while this plugin is still active, which ends in a fatal error. When the site owner clicks
 * "Activate" for Pro, this plugin deactivates itself first and repeats the same request.
 */
function tpoll_make_room_for_pro() {
	$pro = '3task-polls-pro/3task-polls-pro.php';
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only reads which plugin is being activated. The activation itself checks its own nonce on the repeated request.
	$action  = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
	$single  = isset( $_REQUEST['plugin'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) : '';
	$checked = isset( $_REQUEST['checked'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_REQUEST['checked'] ) ) : array();
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$activating_pro = ( 'activate' === $action && $pro === $single ) || ( 'activate-selected' === $action && in_array( $pro, $checked, true ) );
	if ( ! $activating_pro || ! current_user_can( 'activate_plugins' ) || ! tpoll_old_pro_installed() ) {
		return;
	}

	deactivate_plugins( TPOLL_PLUGIN_BASENAME, true );
	if ( 'activate-selected' === $action ) {
		// Bulk activation is a POST request. Activate Pro on the next page load instead.
		wp_safe_redirect( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $pro ) ), 'activate-plugin_' . $pro ) );
		exit;
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Same URL is requested again, wp_safe_redirect() validates it.
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : admin_url( 'plugins.php' );
	wp_safe_redirect( $uri );
	exit;
}
add_action( 'load-plugins.php', 'tpoll_make_room_for_pro', 1 );

// Start plugin
add_action('plugins_loaded', 'tpoll');

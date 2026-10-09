<?php
/**
 * TPoll Activator
 *
 * @package TPoll
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPoll_Activator {

    /**
     * Activate plugin
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();
        self::create_capabilities();
        self::migrate();
        self::schedule_cleanup();

        // Store version
        update_option('tpoll_version', TPOLL_VERSION);
        update_option('tpoll_db_version', TPOLL_DB_VERSION);
    }

    /**
     * Run after a plugin update, when the stored DB version is older.
     */
    public static function maybe_upgrade() {
        if (version_compare((string) get_option('tpoll_db_version', '0'), TPOLL_DB_VERSION, '>=')) {
            return;
        }

        self::create_tables();
        self::migrate();
        self::schedule_cleanup();

        update_option('tpoll_version', TPOLL_VERSION);
        update_option('tpoll_db_version', TPOLL_DB_VERSION);
    }

    /**
     * Data migrations for existing installations.
     */
    private static function migrate() {
        global $wpdb;

        // 1.0.4: votes get a ballot ID. Older rows count as one ballot per IP hash, as before.
        $votes_table = $wpdb->prefix . 'tpoll_votes';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, one-time migration.
        $wpdb->query("UPDATE $votes_table SET ballot = SUBSTR(ip_hash, 1, 32) WHERE ballot IS NULL OR ballot = ''");
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        // 1.0.4: texts are translated on output. Stored English defaults are removed,
        // custom texts stay.
        $settings = get_option('tpoll_settings', array());
        if (is_array($settings)) {
            $english_defaults = array(
                'button_text' => 'Vote',
                'results_text' => 'View Results',
                'voted_text' => 'You have already voted.',
                'closed_text' => 'This poll is closed.',
            );
            foreach ($english_defaults as $key => $english) {
                if (isset($settings[$key]) && $settings[$key] === $english) {
                    $settings[$key] = '';
                }
            }
            if (!isset($settings['retention_days'])) {
                $settings['retention_days'] = 30;
            }
            update_option('tpoll_settings', $settings);
        }
    }

    /**
     * Daily removal of voter fingerprints after the retention period.
     */
    public static function schedule_cleanup() {
        if (!wp_next_scheduled('tpoll_daily_cleanup')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'tpoll_daily_cleanup');
        }
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Polls table
        $table_polls = $wpdb->prefix . 'tpoll_polls';
        $sql_polls = "CREATE TABLE $table_polls (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            question text NOT NULL,
            type varchar(20) NOT NULL DEFAULT 'single',
            status varchar(20) NOT NULL DEFAULT 'draft',
            settings longtext,
            styles longtext,
            start_date datetime DEFAULT NULL,
            end_date datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_status (status),
            KEY idx_type (type)
        ) $charset_collate;";

        // Answers table
        $table_answers = $wpdb->prefix . 'tpoll_answers';
        $sql_answers = "CREATE TABLE $table_answers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            poll_id bigint(20) unsigned NOT NULL,
            answer_text varchar(500) NOT NULL,
            answer_image varchar(500) DEFAULT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            votes int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_poll_id (poll_id)
        ) $charset_collate;";

        // Votes table
        $table_votes = $wpdb->prefix . 'tpoll_votes';
        $sql_votes = "CREATE TABLE $table_votes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            poll_id bigint(20) unsigned NOT NULL,
            answer_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            ip_hash varchar(64) NOT NULL,
            user_agent_hash varchar(64) DEFAULT NULL,
            ballot varchar(32) DEFAULT NULL,
            context_id bigint(20) unsigned NOT NULL DEFAULT 0,
            vote_value int(11) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_poll_id (poll_id),
            KEY idx_ip_hash (ip_hash),
            KEY idx_poll_ip (poll_id, ip_hash),
            KEY idx_ballot (ballot),
            KEY idx_poll_context (poll_id, context_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_polls);
        dbDelta($sql_answers);
        dbDelta($sql_votes);
    }

    /**
     * Set default options
     */
    private static function set_default_options() {
        $defaults = array(
            'tpoll_settings' => array(
                'vote_restriction' => 'ip',
                'show_results' => 'after_vote',
                'allow_revote' => false,
                'results_bar_color' => '',
                'results_bar_bg' => '',
                'button_text' => '',
                'voted_text' => '',
                'closed_text' => '',
                'theme' => 'default',
                'dark_mode' => 'no',
                'animation' => 'grow',
                'show_voters_count' => true,
                'randomize_answers' => false,
                'retention_days' => 30,
                'delete_on_uninstall' => false
            )
        );

        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }

    /**
     * Create capabilities
     */
    private static function create_capabilities() {
        $admin = get_role('administrator');

        if ($admin) {
            $admin->add_cap('manage_tpoll');
            $admin->add_cap('create_tpoll');
            $admin->add_cap('edit_tpoll');
            $admin->add_cap('delete_tpoll');
            $admin->add_cap('view_tpoll_results');
        }

        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap('create_tpoll');
            $editor->add_cap('edit_tpoll');
            $editor->add_cap('view_tpoll_results');
        }
    }
}

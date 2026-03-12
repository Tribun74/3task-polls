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

        // Store version
        update_option('tpoll_version', TPOLL_VERSION);
        update_option('tpoll_db_version', TPOLL_DB_VERSION);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Polls table
        $table_polls = $wpdb->prefix . 'tpoll_polls';
        $sql_polls = "CREATE TABLE IF NOT EXISTS $table_polls (
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
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_type (type)
        ) $charset_collate;";

        // Answers table
        $table_answers = $wpdb->prefix . 'tpoll_answers';
        $sql_answers = "CREATE TABLE IF NOT EXISTS $table_answers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            poll_id bigint(20) unsigned NOT NULL,
            answer_text varchar(500) NOT NULL,
            answer_image varchar(500) DEFAULT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            votes int(11) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_poll_id (poll_id)
        ) $charset_collate;";

        // Votes table
        $table_votes = $wpdb->prefix . 'tpoll_votes';
        $sql_votes = "CREATE TABLE IF NOT EXISTS $table_votes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            poll_id bigint(20) unsigned NOT NULL,
            answer_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            ip_hash varchar(64) NOT NULL,
            user_agent_hash varchar(64) DEFAULT NULL,
            vote_value int(11) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_poll_id (poll_id),
            KEY idx_ip_hash (ip_hash),
            KEY idx_poll_ip (poll_id, ip_hash)
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
                'results_bar_color' => '#4F46E5',
                'results_bar_bg' => '#E5E7EB',
                'button_text' => __('Vote', '3task-polls'),
                'results_text' => __('View Results', '3task-polls'),
                'voted_text' => __('You have already voted.', '3task-polls'),
                'closed_text' => __('This poll is closed.', '3task-polls'),
                'theme' => 'default',
                'dark_mode' => 'auto',
                'animation' => 'fade',
                'show_voters_count' => true,
                'randomize_answers' => false
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

<?php
/**
 * TPoll LITE Admin
 *
 * @package TPoll
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPoll_Admin {

    /**
     * Current tab
     */
    private $current_tab = 'dashboard';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_init', array($this, 'handle_actions'));
        add_action('admin_init', array($this, 'register_settings'));

        // Set current tab
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation, read-only.
        $this->current_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'dashboard';

        // AJAX handlers
        add_action('wp_ajax_tpoll_save_poll', array($this, 'ajax_save_poll'));
        add_action('wp_ajax_tpoll_delete_poll', array($this, 'ajax_delete_poll'));
        add_action('wp_ajax_tpoll_duplicate_poll', array($this, 'ajax_duplicate_poll'));
        add_action('wp_ajax_tpoll_reset_votes', array($this, 'ajax_reset_votes'));
    }

    /**
     * Add admin menu
     */
    public function add_menu() {
        // Main menu
        add_menu_page(
            __('3task Polls', '3task-polls'),
            __('3task Polls', '3task-polls'),
            'create_tpoll',
            'tpoll',
            array($this, 'render_admin_page'),
            'dashicons-chart-bar',
            30
        );
    }

    /**
     * Get available tabs
     */
    private function get_tabs() {
        return array(
            'dashboard' => array(
                'title' => __('Dashboard', '3task-polls'),
                'icon' => 'dashicons-dashboard'
            ),
            'polls' => array(
                'title' => __('Polls', '3task-polls'),
                'icon' => 'dashicons-chart-bar'
            ),
            'results' => array(
                'title' => __('Results', '3task-polls'),
                'icon' => 'dashicons-analytics'
            ),
            'settings' => array(
                'title' => __('Settings', '3task-polls'),
                'icon' => 'dashicons-admin-settings'
            ),
        );
    }

    /**
     * Render main admin page with tabs
     */
    public function render_admin_page() {
        $tabs = $this->get_tabs();
        $tab_icons = array(
            'dashboard' => '&#x1F3E0;',
            'polls'     => '&#x1F4CA;',
            'results'   => '&#x1F4C8;',
            'settings'  => '&#x2699;&#xFE0F;',
        );
        $poll_count = TPoll_Poll::get_count();
        ?>
        <div class="tpoll-admin">
            <!-- Gradient Header -->
            <div class="tpoll-admin-header">
                <div class="tpoll-admin-header-content">
                    <div class="tpoll-admin-header-left">
                        <div class="tpoll-admin-icon">&#x1F4CA;</div>
                        <div class="tpoll-admin-title-text">
                            <h1><?php esc_html_e( '3task Polls', '3task-polls' ); ?></h1>
                            <div class="tpoll-admin-title-meta">
                                <span class="tpoll-version"><?php echo esc_html( 'v' . TPOLL_VERSION ); ?></span>
                                <span class="tpoll-status-dot"><?php esc_html_e( 'Active', '3task-polls' ); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="tpoll-admin-header-right">
                        <a href="https://wordpress.org/support/plugin/3task-polls/" target="_blank" class="tpoll-header-btn">&#x1F4AC; <?php esc_html_e( 'Support', '3task-polls' ); ?></a>
                        <a href="https://wordpress.org/plugins/3task-polls/#reviews" target="_blank" class="tpoll-header-btn">&#x2B50; <?php esc_html_e( 'Rate', '3task-polls' ); ?></a>
                    </div>
                </div>
            </div>

            <!-- Tab Navigation -->
            <div class="tpoll-tabs nav-tab-wrapper">
                <?php foreach ( $tabs as $tab_key => $tab ) :
                    $active_class = ( $this->current_tab === $tab_key ) ? 'nav-tab-active' : '';
                    $url = admin_url( 'admin.php?page=tpoll&tab=' . $tab_key );
                    $icon = isset( $tab_icons[ $tab_key ] ) ? $tab_icons[ $tab_key ] : '';
                ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="nav-tab <?php echo esc_attr( $active_class ); ?>">
                        <span class="tpoll-tab-icon"><?php echo wp_kses( $icon, array() ); ?></span>
                        <?php echo esc_html( $tab['title'] ); ?>
                        <?php if ( 'polls' === $tab_key && $poll_count > 0 ) : ?>
                            <span class="tpoll-tab-badge"><?php echo absint( $poll_count ); ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Tab Content -->
            <div class="tpoll-tab-content">
                <?php $this->render_tab_content(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render tab content
     */
    private function render_tab_content() {
        switch ($this->current_tab) {
            case 'dashboard':
                $this->render_dashboard();
                break;
            case 'polls':
                $this->render_polls();
                break;
            case 'results':
                $this->render_results();
                break;
            case 'settings':
                $this->render_settings();
                break;
            default:
                $this->render_dashboard();
        }
    }

    /**
     * Render dashboard tab
     */
    private function render_dashboard() {
        $stats = $this->get_poll_stats();
        ?>
        <!-- Stats Grid -->
        <div class="tpoll-stats-grid">
            <div class="tpoll-stat-card stat-purple">
                <div class="tpoll-stat-header">
                    <span class="tpoll-stat-icon">&#x1F4CA;</span>
                </div>
                <div class="tpoll-stat-value"><?php echo absint( $stats['total_polls'] ); ?></div>
                <div class="tpoll-stat-label"><?php esc_html_e( 'Total Polls', '3task-polls' ); ?></div>
            </div>
            <div class="tpoll-stat-card stat-green">
                <div class="tpoll-stat-header">
                    <span class="tpoll-stat-icon">&#x2705;</span>
                </div>
                <div class="tpoll-stat-value"><?php echo absint( $stats['published_polls'] ); ?></div>
                <div class="tpoll-stat-label"><?php esc_html_e( 'Published', '3task-polls' ); ?></div>
            </div>
            <div class="tpoll-stat-card stat-orange">
                <div class="tpoll-stat-header">
                    <span class="tpoll-stat-icon">&#x1F4DD;</span>
                </div>
                <div class="tpoll-stat-value"><?php echo absint( $stats['draft_polls'] ); ?></div>
                <div class="tpoll-stat-label"><?php esc_html_e( 'Drafts', '3task-polls' ); ?></div>
            </div>
            <div class="tpoll-stat-card stat-blue">
                <div class="tpoll-stat-header">
                    <span class="tpoll-stat-icon">&#x1F5F3;</span>
                </div>
                <div class="tpoll-stat-value"><?php echo absint( $stats['total_votes'] ); ?></div>
                <div class="tpoll-stat-label"><?php esc_html_e( 'Total Votes', '3task-polls' ); ?></div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="tpoll-content-grid">
            <div>
                <!-- Welcome Card -->
                <div class="tpoll-card">
                    <div class="tpoll-card-header">
                        <h2 class="tpoll-card-title">
                            <span class="tpoll-card-title-icon">&#x1F44B;</span>
                            <?php esc_html_e( 'Welcome to 3task Polls', '3task-polls' ); ?>
                        </h2>
                    </div>
                    <p><?php esc_html_e( 'Create polls, surveys, quizzes and voting for your WordPress website. Use the shortcode or Gutenberg block to display polls anywhere.', '3task-polls' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpoll&tab=polls&action=new' ) ); ?>" class="tpoll-btn-primary">
                        &#x2795; <?php esc_html_e( 'Create Your First Poll', '3task-polls' ); ?>
                    </a>
                </div>
            </div>

            <div>
                <!-- Quick Actions -->
                <div class="tpoll-quick-actions">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpoll&tab=polls&action=new' ) ); ?>" class="tpoll-quick-action">
                        <div class="tpoll-quick-action-icon">&#x2795;</div>
                        <div class="tpoll-quick-action-label"><?php esc_html_e( 'New Poll', '3task-polls' ); ?></div>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpoll&tab=results' ) ); ?>" class="tpoll-quick-action">
                        <div class="tpoll-quick-action-icon">&#x1F4C8;</div>
                        <div class="tpoll-quick-action-label"><?php esc_html_e( 'Results', '3task-polls' ); ?></div>
                    </a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpoll&tab=settings' ) ); ?>" class="tpoll-quick-action">
                        <div class="tpoll-quick-action-icon">&#x2699;&#xFE0F;</div>
                        <div class="tpoll-quick-action-label"><?php esc_html_e( 'Settings', '3task-polls' ); ?></div>
                    </a>
                    <a href="https://wordpress.org/support/plugin/3task-polls/" target="_blank" class="tpoll-quick-action">
                        <div class="tpoll-quick-action-icon">&#x2753;</div>
                        <div class="tpoll-quick-action-label"><?php esc_html_e( 'Help', '3task-polls' ); ?></div>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render polls tab
     */
    private function render_polls() {
        // Check if editing or creating a poll
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View routing, read-only.
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['poll'])) {
            $this->render_edit_poll();
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View routing, read-only.
        if (isset($_GET['action']) && $_GET['action'] === 'new') {
            $this->render_new_poll();
            return;
        }

        // List all polls
        $polls = $this->get_polls();
        ?>
        <div class="tpoll-card-header">
            <h2 class="tpoll-card-title">
                <span class="tpoll-card-title-icon">&#x1F4CA;</span>
                <?php esc_html_e( 'All Polls', '3task-polls' ); ?>
            </h2>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpoll&tab=polls&action=new' ) ); ?>" class="tpoll-card-action">
                &#x2795; <?php esc_html_e( 'Add New Poll', '3task-polls' ); ?>
            </a>
        </div>

        <?php if ( empty( $polls ) ) : ?>
            <div class="tpoll-empty-state">
                <div class="tpoll-empty-state-icon">&#x1F4CA;</div>
                <h3><?php esc_html_e( 'No polls yet', '3task-polls' ); ?></h3>
                <p><?php esc_html_e( 'Create your first poll to get started!', '3task-polls' ); ?></p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpoll&tab=polls&action=new' ) ); ?>" class="tpoll-btn-primary">
                    &#x2795; <?php esc_html_e( 'Create Poll', '3task-polls' ); ?>
                </a>
            </div>
        <?php else : ?>
            <div class="tpoll-polls-grid">
                <?php foreach ( $polls as $poll ) : ?>
                    <div class="tpoll-poll-card">
                        <div class="tpoll-poll-card-header">
                            <h4 class="tpoll-poll-card-title"><?php echo esc_html( $poll->title ); ?></h4>
                            <span class="tpoll-badge tpoll-badge-<?php echo esc_attr( $poll->status ); ?>"><?php echo esc_html( ucfirst( $poll->status ) ); ?></span>
                        </div>
                        <div class="tpoll-poll-card-meta">
                            <span class="tpoll-badge"><?php echo esc_html( ucfirst( $poll->type ) ); ?></span>
                        </div>
                        <p><?php echo esc_html( wp_trim_words( $poll->question, 15 ) ); ?></p>
                        <div class="tpoll-poll-card-actions">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpoll&tab=polls&action=edit&poll=' . $poll->id ) ); ?>">
                                <?php esc_html_e( 'Edit', '3task-polls' ); ?>
                            </a>
                            <button type="button" class="tpoll-delete-btn" data-poll-id="<?php echo absint( $poll->id ); ?>">
                                <?php esc_html_e( 'Delete', '3task-polls' ); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Render edit poll form
     */
    private function render_edit_poll() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Poll ID for editing, read-only.
        $poll_id = isset($_GET['poll']) ? absint($_GET['poll']) : 0;
        $poll = TPoll_Poll::get($poll_id);

        if (!$poll) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Poll not found.', '3task-polls') . '</p></div>';
            return;
        }

        require_once TPOLL_PLUGIN_DIR . 'admin/views/poll-edit.php';
    }

    /**
     * Render new poll form
     */
    private function render_new_poll() {
        $poll = new TPoll_Poll();
        require_once TPOLL_PLUGIN_DIR . 'admin/views/poll-edit.php';
    }

    /**
     * Render results tab
     */
    private function render_results() {
        $polls = $this->get_polls();
        ?>
        <div class="tpoll-card-header">
            <h2 class="tpoll-card-title">
                <span class="tpoll-card-title-icon">&#x1F4C8;</span>
                <?php esc_html_e( 'Poll Results', '3task-polls' ); ?>
            </h2>
        </div>

        <?php if ( empty( $polls ) ) : ?>
            <div class="tpoll-empty-state">
                <div class="tpoll-empty-state-icon">&#x1F4C8;</div>
                <h3><?php esc_html_e( 'No results yet', '3task-polls' ); ?></h3>
                <p><?php esc_html_e( 'Create and publish a poll to start collecting votes.', '3task-polls' ); ?></p>
            </div>
        <?php else : ?>
            <?php foreach ( $polls as $poll_obj ) :
                $results = TPoll_Vote::get_results( $poll_obj->id );
                if ( ! $results || empty( $results['total_voters'] ) ) {
                    continue;
                }
            ?>
                <div class="tpoll-card tpoll-result-card">
                    <div class="tpoll-card-header">
                        <h3 class="tpoll-card-title"><?php echo esc_html( $poll_obj->title ); ?></h3>
                        <span class="tpoll-badge tpoll-badge-<?php echo esc_attr( $poll_obj->status ); ?>">
                            <?php echo esc_html( ucfirst( $poll_obj->status ) ); ?>
                        </span>
                    </div>
                    <p class="tpoll-text-muted"><?php echo esc_html( $poll_obj->question ); ?></p>

                    <div class="tpoll-results-bars">
                        <?php foreach ( $results['answers'] as $answer ) : ?>
                            <div class="tpoll-result-row">
                                <div class="tpoll-result-label">
                                    <span><?php echo esc_html( $answer['text'] ); ?></span>
                                    <span class="tpoll-result-meta">
                                        <?php
                                        printf(
                                            /* translators: 1: number of votes, 2: percentage */
                                            esc_html__( '%1$d votes (%2$s%%)', '3task-polls' ),
                                            absint( $answer['votes'] ),
                                            esc_html( $answer['percentage'] )
                                        );
                                        ?>
                                    </span>
                                </div>
                                <div class="tpoll-result-bar-wrap">
                                    <div class="tpoll-result-bar" style="width: <?php echo esc_attr( $answer['percentage'] ); ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="tpoll-result-footer">
                        <?php
                        printf(
                            /* translators: %d: total number of voters */
                            esc_html__( 'Total voters: %d', '3task-polls' ),
                            absint( $results['total_voters'] )
                        );
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif;
    }

    /**
     * Render settings tab
     */
    private function render_settings() {
        require_once TPOLL_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Get poll statistics
     */
    private function get_poll_stats() {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables, simple counts.
        $total_polls = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tpoll_polls");
        $published_polls = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tpoll_polls WHERE status = 'published'");
        $draft_polls = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tpoll_polls WHERE status = 'draft'");
        $total_votes = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tpoll_votes");
        // phpcs:enable

        return array(
            'total_polls' => $total_polls ?: 0,
            'published_polls' => $published_polls ?: 0,
            'draft_polls' => $draft_polls ?: 0,
            'total_votes' => $total_votes ?: 0
        );
    }

    /**
     * Get polls
     */
    private function get_polls() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}tpoll_polls ORDER BY created_at DESC");

        return $results ?: array();
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        // Only on our pages
        if (strpos($hook, 'tpoll') === false) {
            return;
        }

        wp_enqueue_style(
            'tpoll-admin',
            TPOLL_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            TPOLL_VERSION
        );

        wp_enqueue_script(
            'tpoll-admin',
            TPOLL_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-sortable'),
            TPOLL_VERSION,
            true
        );

        wp_localize_script('tpoll-admin', 'tpollAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tpoll_admin'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this poll? This cannot be undone.', '3task-polls'),
                'confirmReset' => __('Are you sure you want to reset all votes for this poll?', '3task-polls'),
                'saved' => __('Poll saved successfully!', '3task-polls'),
                'error' => __('An error occurred. Please try again.', '3task-polls'),
                'addAnswer' => __('Add Answer', '3task-polls'),
                'removeAnswer' => __('Remove', '3task-polls')
            )
        ));

        // Media uploader for image polls (Pro feature indicator)
        wp_enqueue_media();

        // Color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }

    /**
     * Handle admin actions
     */
    public function handle_actions() {
        if (!current_user_can('edit_tpoll')) {
            return;
        }

        // Handle single poll delete
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['poll'])) {
            if (!current_user_can('delete_tpoll')) {
                wp_die(esc_html__('Permission denied.', '3task-polls'));
            }
            $tpoll_poll_id = absint($_GET['poll']);
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'tpoll_delete_' . $tpoll_poll_id)) {
                wp_die(esc_html__('Security check failed', '3task-polls'));
            }

            $poll = TPoll_Poll::get($tpoll_poll_id);
            if ($poll) {
                $poll->delete();
            }

            wp_safe_redirect(admin_url('admin.php?page=tpoll&tab=polls&deleted=1&tpoll_notice=' . wp_create_nonce('tpoll_admin_notice')));
            exit;
        }

        // Handle bulk actions
        if (isset($_POST['action']) && isset($_POST['poll_ids']) && is_array($_POST['poll_ids'])) {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'bulk-tpoll')) {
                wp_die(esc_html__('Security check failed', '3task-polls'));
            }

            $action = sanitize_text_field(wp_unslash($_POST['action']));
            $poll_ids = array_map('absint', wp_unslash($_POST['poll_ids']));
            $tpoll_notice_nonce = wp_create_nonce('tpoll_admin_notice');

            switch ($action) {
                case 'delete':
                    foreach ($poll_ids as $poll_id) {
                        $poll = TPoll_Poll::get($poll_id);
                        if ($poll) {
                            $poll->delete();
                        }
                    }
                    wp_safe_redirect(admin_url('admin.php?page=tpoll&tab=polls&deleted=' . count($poll_ids) . '&tpoll_notice=' . $tpoll_notice_nonce));
                    exit;

                case 'publish':
                    foreach ($poll_ids as $poll_id) {
                        $poll = TPoll_Poll::get($poll_id);
                        if ($poll) {
                            $poll->status = 'published';
                            $poll->save();
                        }
                    }
                    wp_safe_redirect(admin_url('admin.php?page=tpoll&tab=polls&updated=' . count($poll_ids) . '&tpoll_notice=' . $tpoll_notice_nonce));
                    exit;

                case 'close':
                    foreach ($poll_ids as $poll_id) {
                        $poll = TPoll_Poll::get($poll_id);
                        if ($poll) {
                            $poll->status = 'closed';
                            $poll->save();
                        }
                    }
                    wp_safe_redirect(admin_url('admin.php?page=tpoll&tab=polls&updated=' . count($poll_ids) . '&tpoll_notice=' . $tpoll_notice_nonce));
                    exit;
            }
        }
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('tpoll_settings_group', 'tpoll_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        $sanitized['vote_restriction'] = sanitize_text_field($input['vote_restriction'] ?? 'ip');
        $sanitized['show_results'] = sanitize_text_field($input['show_results'] ?? 'after_vote');
        $sanitized['allow_revote'] = !empty($input['allow_revote']);
        $sanitized['results_bar_color'] = sanitize_hex_color($input['results_bar_color'] ?? '#4F46E5');
        $sanitized['results_bar_bg'] = sanitize_hex_color($input['results_bar_bg'] ?? '#E5E7EB');
        $sanitized['button_text'] = sanitize_text_field($input['button_text'] ?? __('Vote', '3task-polls'));
        $sanitized['results_text'] = sanitize_text_field($input['results_text'] ?? __('View Results', '3task-polls'));
        $sanitized['voted_text'] = sanitize_text_field($input['voted_text'] ?? __('You have already voted.', '3task-polls'));
        $sanitized['closed_text'] = sanitize_text_field($input['closed_text'] ?? __('This poll is closed.', '3task-polls'));
        $sanitized['theme'] = sanitize_text_field($input['theme'] ?? 'default');
        $sanitized['dark_mode'] = sanitize_text_field($input['dark_mode'] ?? 'auto');
        $sanitized['animation'] = sanitize_text_field($input['animation'] ?? 'fade');
        $sanitized['show_voters_count'] = !empty($input['show_voters_count']);
        $sanitized['randomize_answers'] = !empty($input['randomize_answers']);

        return $sanitized;
    }

    /**
     * Render polls list page
     */
    public function render_polls_page() {
        // Check if editing
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View routing, read-only.
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['poll'])) {
            $this->render_edit_page();
            return;
        }

        require_once TPOLL_PLUGIN_DIR . 'admin/views/polls-list.php';
    }

    /**
     * Render edit page
     */
    public function render_edit_page() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Poll ID for editing, read-only.
        $poll_id = isset($_GET['poll']) ? absint($_GET['poll']) : 0;
        $poll = $poll_id ? TPoll_Poll::get($poll_id) : new TPoll_Poll();

        require_once TPOLL_PLUGIN_DIR . 'admin/views/poll-edit.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        require_once TPOLL_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * AJAX: Save poll
     */
    public function ajax_save_poll() {
        check_ajax_referer('tpoll_admin', 'nonce');

        if (!current_user_can('create_tpoll')) {
            wp_send_json_error(array('message' => __('Permission denied.', '3task-polls')));
        }

        $poll_id = isset($_POST['poll_id']) ? absint($_POST['poll_id']) : 0;
        $poll = $poll_id ? TPoll_Poll::get($poll_id) : new TPoll_Poll();

        if (!$poll) {
            $poll = new TPoll_Poll();
        }

        // Basic fields
        $poll->title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $poll->question = isset($_POST['question']) ? sanitize_textarea_field(wp_unslash($_POST['question'])) : '';
        $poll->type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'single';
        $poll->status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'draft';

        // Dates
        $poll->start_date = !empty($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : null;
        $poll->end_date = !empty($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : null;

        // Settings
        $poll->settings = array(
            'vote_restriction' => isset($_POST['vote_restriction']) ? sanitize_text_field(wp_unslash($_POST['vote_restriction'])) : 'ip',
            'show_results' => isset($_POST['show_results']) ? sanitize_text_field(wp_unslash($_POST['show_results'])) : 'after_vote',
            'allow_revote' => !empty($_POST['allow_revote']),
            'max_choices' => isset($_POST['max_choices']) ? absint($_POST['max_choices']) : 0,
            'max_rating' => isset($_POST['max_rating']) ? absint($_POST['max_rating']) : 5,
            'randomize_answers' => !empty($_POST['randomize_answers']),
            'show_voters_count' => !empty($_POST['show_voters_count']),
            'theme' => isset($_POST['theme']) ? sanitize_text_field(wp_unslash($_POST['theme'])) : 'default',
            'dark_mode' => isset($_POST['dark_mode']) ? sanitize_text_field(wp_unslash($_POST['dark_mode'])) : 'auto'
        );

        // Styles
        $poll->styles = array(
            'bar_color' => isset($_POST['bar_color']) ? sanitize_hex_color(wp_unslash($_POST['bar_color'])) : '#4F46E5',
            'bar_bg' => isset($_POST['bar_bg']) ? sanitize_hex_color(wp_unslash($_POST['bar_bg'])) : '#E5E7EB',
            'text_color' => isset($_POST['text_color']) ? sanitize_hex_color(wp_unslash($_POST['text_color'])) : '#1F2937',
            'bg_color' => isset($_POST['bg_color']) ? sanitize_hex_color(wp_unslash($_POST['bg_color'])) : '#FFFFFF'
        );

        // Save poll
        $poll_id = $poll->save();

        // Check for WP_Error (poll limit reached)
        if (is_wp_error($poll_id)) {
            wp_send_json_error(array('message' => $poll_id->get_error_message()));
        }

        if (!$poll_id) {
            wp_send_json_error(array('message' => __('Error saving poll.', '3task-polls')));
        }

        // Save answers
        $answers = array();
        if (isset($_POST['answers']) && is_array($_POST['answers'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in loop below.
            $raw_answers = wp_unslash($_POST['answers']);
            foreach ($raw_answers as $answer) {
                if (!empty($answer['text'])) {
                    $answers[] = array(
                        'text' => sanitize_text_field($answer['text']),
                        'image' => isset($answer['image']) ? esc_url_raw($answer['image']) : ''
                    );
                }
            }
        }

        // For rating/voting types, ensure at least one answer
        if (in_array($poll->type, array('rating', 'voting')) && empty($answers)) {
            $answers[] = array('text' => $poll->question, 'image' => '');
        }

        $poll->save_answers($answers);

        wp_send_json_success(array(
            'message' => __('Poll saved successfully!', '3task-polls'),
            'poll_id' => $poll_id,
            'redirect' => admin_url('admin.php?page=tpoll&tab=polls&action=edit&poll=' . $poll_id . '&saved=1')
        ));
    }

    /**
     * AJAX: Delete poll
     */
    public function ajax_delete_poll() {
        check_ajax_referer('tpoll_admin', 'nonce');

        if (!current_user_can('delete_tpoll')) {
            wp_send_json_error(array('message' => __('Permission denied.', '3task-polls')));
        }

        $poll_id = isset($_POST['poll_id']) ? absint($_POST['poll_id']) : 0;
        $poll = TPoll_Poll::get($poll_id);

        if (!$poll) {
            wp_send_json_error(array('message' => __('Poll not found.', '3task-polls')));
        }

        $poll->delete();

        wp_send_json_success(array(
            'message' => __('Poll deleted successfully!', '3task-polls')
        ));
    }

    /**
     * AJAX: Duplicate poll
     */
    public function ajax_duplicate_poll() {
        check_ajax_referer('tpoll_admin', 'nonce');

        if (!current_user_can('create_tpoll')) {
            wp_send_json_error(array('message' => __('Permission denied.', '3task-polls')));
        }

        $poll_id = isset($_POST['poll_id']) ? absint($_POST['poll_id']) : 0;
        $poll = TPoll_Poll::get($poll_id);

        if (!$poll) {
            wp_send_json_error(array('message' => __('Poll not found.', '3task-polls')));
        }

        $new_poll = $poll->duplicate();

        wp_send_json_success(array(
            'message' => __('Poll duplicated successfully!', '3task-polls'),
            'poll_id' => $new_poll->id,
            'redirect' => admin_url('admin.php?page=tpoll&tab=polls&action=edit&poll=' . $new_poll->id)
        ));
    }

    /**
     * AJAX: Reset votes
     */
    public function ajax_reset_votes() {
        check_ajax_referer('tpoll_admin', 'nonce');

        if (!current_user_can('manage_tpoll')) {
            wp_send_json_error(array('message' => __('Permission denied.', '3task-polls')));
        }

        $poll_id = isset($_POST['poll_id']) ? absint($_POST['poll_id']) : 0;
        $poll = TPoll_Poll::get($poll_id);

        if (!$poll) {
            wp_send_json_error(array('message' => __('Poll not found.', '3task-polls')));
        }

        // Delete votes
        TPoll_Vote::delete_votes($poll_id);

        // Reset answer vote counts
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
        $wpdb->update(
            $wpdb->prefix . 'tpoll_answers',
            array('votes' => 0),
            array('poll_id' => $poll_id),
            array('%d'),
            array('%d')
        );

        wp_send_json_success(array(
            'message' => __('Votes reset successfully!', '3task-polls')
        ));
    }
}

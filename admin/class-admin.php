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
        $tabs = array(
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

        /**
         * Filters the admin tabs. Add-ons render their tab on the action
         * "tpoll_admin_tab_{key}".
         *
         * @param array $tabs Key => array('title' => ..., 'icon' => ...).
         */
        $tabs = (array) apply_filters('tpoll_admin_tabs', $tabs);

        // Settings are saved through options.php, which requires manage_options.
        if (!current_user_can('manage_options')) {
            unset($tabs['settings']);
        }

        return $tabs;
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
                        <a href="https://wordpress.org/support/plugin/3task-polls/" target="_blank" rel="noopener noreferrer" class="tpoll-header-btn">&#x1F4AC; <?php esc_html_e( 'Support', '3task-polls' ); ?></a>
                        <a href="https://wordpress.org/plugins/3task-polls/#reviews" target="_blank" rel="noopener noreferrer" class="tpoll-header-btn">&#x2B50; <?php esc_html_e( 'Rate', '3task-polls' ); ?></a>
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
                        <?php if ( '' !== $icon ) : ?>
                            <span class="tpoll-tab-icon"><?php echo wp_kses( $icon, array() ); ?></span>
                        <?php elseif ( ! empty( $tab['icon'] ) ) : ?>
                            <span class="tpoll-tab-icon dashicons <?php echo esc_attr( $tab['icon'] ); ?>" aria-hidden="true"></span>
                        <?php endif; ?>
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
                if (current_user_can('manage_options')) {
                    $this->render_settings();
                } else {
                    $this->render_dashboard();
                }
                break;
            default:
                if (array_key_exists($this->current_tab, $this->get_tabs()) && has_action('tpoll_admin_tab_' . $this->current_tab)) {
                    /**
                     * Renders an add-on tab.
                     */
                    do_action('tpoll_admin_tab_' . $this->current_tab);
                } else {
                    $this->render_dashboard();
                }
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
                <?php if ( $stats['total_polls'] > 0 ) : ?>
                <!-- Latest Polls -->
                <div class="tpoll-card">
                    <div class="tpoll-card-header">
                        <h2 class="tpoll-card-title">
                            <span class="tpoll-card-title-icon">&#x1F4CA;</span>
                            <?php esc_html_e( 'Latest Polls', '3task-polls' ); ?>
                        </h2>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpoll&tab=polls' ) ); ?>" class="tpoll-card-action">
                            <?php esc_html_e( 'All Polls', '3task-polls' ); ?>
                        </a>
                    </div>
                    <ul class="tpoll-latest-list">
                        <?php
                        $tpoll_statuses = TPoll_Poll::get_statuses();
                        foreach ( TPoll_Poll::get_all( array( 'per_page' => 5, 'orderby' => 'id' ) ) as $tpoll_item ) :
                            $tpoll_voters = $tpoll_item->get_total_voters();
                            ?>
                            <li>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpoll&tab=polls&action=edit&poll=' . $tpoll_item->id ) ); ?>"><?php echo esc_html( $tpoll_item->title ); ?></a>
                                <span class="tpoll-badge tpoll-badge-<?php echo esc_attr( $tpoll_item->status ); ?>"><?php echo esc_html( $tpoll_statuses[ $tpoll_item->status ] ?? $tpoll_item->status ); ?></span>
                                <span class="tpoll-latest-count">
                                    <?php
                                    /* translators: %d: number of voters */
                                    echo esc_html( sprintf( _n( '%d voter', '%d voters', $tpoll_voters, '3task-polls' ), $tpoll_voters ) );
                                    ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpoll&tab=polls&action=new' ) ); ?>" class="tpoll-btn-primary">
                        &#x2795; <?php esc_html_e( 'Add New Poll', '3task-polls' ); ?>
                    </a>
                </div>
                <?php else : ?>
                <!-- Welcome Card -->
                <div class="tpoll-card">
                    <div class="tpoll-card-header">
                        <h2 class="tpoll-card-title">
                            <span class="tpoll-card-title-icon">&#x1F44B;</span>
                            <?php esc_html_e( 'Welcome to 3task Polls', '3task-polls' ); ?>
                        </h2>
                    </div>
                    <p><?php esc_html_e( 'Create polls, star ratings and up/down voting for your WordPress website. Use the shortcode or the block "3task Polls" to display polls anywhere.', '3task-polls' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpoll&tab=polls&action=new' ) ); ?>" class="tpoll-btn-primary">
                        &#x2795; <?php esc_html_e( 'Create Your First Poll', '3task-polls' ); ?>
                    </a>
                </div>
                <?php endif; ?>
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
                    <?php if ( current_user_can( 'manage_options' ) ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpoll&tab=settings' ) ); ?>" class="tpoll-quick-action">
                        <div class="tpoll-quick-action-icon">&#x2699;&#xFE0F;</div>
                        <div class="tpoll-quick-action-label"><?php esc_html_e( 'Settings', '3task-polls' ); ?></div>
                    </a>
                    <?php endif; ?>
                    <a href="https://wordpress.org/support/plugin/3task-polls/" target="_blank" rel="noopener noreferrer" class="tpoll-quick-action">
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
                <?php
                $tpoll_statuses = TPoll_Poll::get_statuses();
                $tpoll_types = TPoll_Poll::get_types();
                foreach ( $polls as $poll ) : ?>
                    <div class="tpoll-poll-card">
                        <div class="tpoll-poll-card-header">
                            <h4 class="tpoll-poll-card-title"><?php echo esc_html( $poll->title ); ?></h4>
                            <span class="tpoll-badge tpoll-badge-<?php echo esc_attr( $poll->status ); ?>"><?php echo esc_html( $tpoll_statuses[ $poll->status ] ?? $poll->status ); ?></span>
                        </div>
                        <div class="tpoll-poll-card-meta">
                            <span class="tpoll-badge"><?php echo esc_html( $tpoll_types[ $poll->type ] ?? $poll->type ); ?></span>
                            <code>[tpoll id="<?php echo absint( $poll->id ); ?>"]</code>
                        </div>
                        <p><?php echo esc_html( wp_trim_words( $poll->question, 15 ) ); ?></p>
                        <div class="tpoll-poll-card-actions">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=tpoll&tab=polls&action=edit&poll=' . $poll->id ) ); ?>">
                                <?php esc_html_e( 'Edit', '3task-polls' ); ?>
                            </a>
                            <button type="button" class="tpoll-duplicate button-link" data-poll-id="<?php echo absint( $poll->id ); ?>">
                                <?php esc_html_e( 'Duplicate', '3task-polls' ); ?>
                            </button>
                            <?php if ( current_user_can( 'delete_tpoll' ) ) : ?>
                            <button type="button" class="tpoll-delete-btn" data-poll-id="<?php echo absint( $poll->id ); ?>">
                                <?php esc_html_e( 'Delete', '3task-polls' ); ?>
                            </button>
                            <?php endif; ?>
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
        $global = get_option('tpoll_settings', array());
        $poll->settings = array(
            'vote_restriction' => $global['vote_restriction'] ?? 'ip',
            'show_results' => $global['show_results'] ?? 'after_vote',
            'allow_revote' => !empty($global['allow_revote']),
            'show_voters_count' => $global['show_voters_count'] ?? true,
            'randomize_answers' => !empty($global['randomize_answers']),
            'theme' => $global['theme'] ?? 'default',
            'dark_mode' => $global['dark_mode'] ?? 'no',
        );
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
            <?php foreach ( $polls as $poll_row ) :
                $poll_obj = TPoll_Poll::get( $poll_row->id );
                $results = $poll_obj ? TPoll_Vote::get_results( $poll_obj->id, $poll_obj ) : null;
                if ( ! $results || empty( $results['total_voters'] ) ) {
                    continue;
                }
            ?>
                <div class="tpoll-card tpoll-result-card">
                    <div class="tpoll-card-header">
                        <h3 class="tpoll-card-title"><?php echo esc_html( $poll_obj->title ); ?></h3>
                        <span class="tpoll-badge tpoll-badge-<?php echo esc_attr( $poll_obj->status ); ?>">
                            <?php $tpoll_statuses = TPoll_Poll::get_statuses(); echo esc_html( $tpoll_statuses[ $poll_obj->status ] ?? $poll_obj->status ); ?>
                        </span>
                    </div>
                    <p class="tpoll-text-muted"><?php echo esc_html( $poll_obj->question ); ?></p>

                    <?php if ( 'rating' === $poll_obj->type ) : ?>
                        <p><strong><?php
                        /* translators: %s: average rating */
                        printf( esc_html__( 'Average rating: %s / 5', '3task-polls' ), esc_html( number_format_i18n( (float) $results['average_rating'], 1 ) ) );
                        ?></strong></p>
                    <?php elseif ( 'voting' === $poll_obj->type ) : ?>
                        <p><strong><?php
                        /* translators: 1: thumbs up, 2: thumbs down */
                        printf( esc_html__( 'Thumbs up: %1$d, thumbs down: %2$d', '3task-polls' ), absint( $results['up'] ), absint( $results['down'] ) );
                        ?></strong></p>
                    <?php endif; ?>
                    <?php if ( ! in_array( $poll_obj->type, array( 'rating', 'voting' ), true ) && ! TPoll_Poll::is_choice_type( $poll_obj->type ) ) : ?>
                        <p><strong><?php
                        /* translators: %s: average value */
                        printf( esc_html__( 'Average: %s', '3task-polls' ), esc_html( number_format_i18n( (float) $results['average_value'], 1 ) ) );
                        ?></strong></p>
                    <?php endif; ?>
                    <?php if ( TPoll_Poll::is_choice_type( $poll_obj->type ) ) : ?>
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
                    <?php if ( 'multiple' === $poll_obj->type ) : ?>
                        <p class="description"><?php esc_html_e( 'Multiple choice: percentages are shares of voters, so they can add up to more than 100.', '3task-polls' ); ?></p>
                    <?php endif; ?>
                    <?php endif; ?>

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
            // wp-util provides wp.template() for new answer rows.
            array('jquery', 'wp-util'),
            TPOLL_VERSION,
            true
        );

        wp_localize_script('tpoll-admin', 'tpollAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tpoll_admin'),
            'choiceTypes' => TPoll_Poll::choice_types(),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this poll? This cannot be undone.', '3task-polls'),
                'confirmReset' => __('Are you sure you want to reset all votes for this poll?', '3task-polls'),
                'saved' => __('Poll saved successfully!', '3task-polls'),
                'error' => __('An error occurred. Please try again.', '3task-polls'),
                'addAnswer' => __('Add Answer', '3task-polls'),
                'removeAnswer' => __('Remove', '3task-polls'),
                'minAnswers' => __('A poll needs at least two answers.', '3task-polls'),
                'reactionsPreset' => array('👍', '❤️', '😂', '😮', '😢'),
                /* translators: %d: number of votes */
                'confirmRemoveVoted' => __('This answer has %d votes. If you remove it and save, these votes are deleted. Continue?', '3task-polls')
            )
        ));

        // Color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
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
        $input = is_array($input) ? $input : array();
        $sanitized = array();

        $sanitized['vote_restriction'] = self::pick($input['vote_restriction'] ?? '', array('ip', 'cookie', 'user'), 'ip');
        $sanitized['show_results'] = self::pick($input['show_results'] ?? '', array('after_vote', 'always', 'never'), 'after_vote');
        $sanitized['allow_revote'] = !empty($input['allow_revote']);
        // The defaults are stored empty, so designs keep their own colors unless the site owner picks one.
        $bar_color = (string) sanitize_hex_color($input['results_bar_color'] ?? '');
        $bar_bg = (string) sanitize_hex_color($input['results_bar_bg'] ?? '');
        $sanitized['results_bar_color'] = strtoupper($bar_color) === '#4F46E5' ? '' : $bar_color;
        $sanitized['results_bar_bg'] = strtoupper($bar_bg) === '#E5E7EB' ? '' : $bar_bg;
        $sanitized['button_text'] = sanitize_text_field($input['button_text'] ?? '');
        $sanitized['voted_text'] = sanitize_text_field($input['voted_text'] ?? '');
        $sanitized['closed_text'] = sanitize_text_field($input['closed_text'] ?? '');
        $sanitized['theme'] = self::pick($input['theme'] ?? '', array_keys(TPoll_Shortcode::themes()), 'default');
        $sanitized['dark_mode'] = self::pick($input['dark_mode'] ?? '', array('auto', 'yes', 'no'), 'no');
        $sanitized['animation'] = self::pick($input['animation'] ?? '', array('grow', 'none'), 'grow');
        $sanitized['show_voters_count'] = !empty($input['show_voters_count']);
        $sanitized['randomize_answers'] = !empty($input['randomize_answers']);
        $sanitized['retention_days'] = max(1, min(3650, absint($input['retention_days'] ?? 30)));
        $sanitized['delete_on_uninstall'] = !empty($input['delete_on_uninstall']);
        $sanitized['feedback_enabled'] = !empty($input['feedback_enabled']);
        $sanitized['feedback_style'] = self::pick($input['feedback_style'] ?? '', array('helpful', 'reactions'), 'helpful');
        $sanitized['feedback_question'] = sanitize_text_field($input['feedback_question'] ?? '');
        $public_types = get_post_types(array('public' => true));
        $sanitized['feedback_post_types'] = array_values(array_intersect(
            array_map('sanitize_key', (array) ($input['feedback_post_types'] ?? array('post'))),
            array_keys($public_types)
        ));
        if (empty($sanitized['feedback_post_types'])) {
            $sanitized['feedback_post_types'] = array('post');
        }

        return $sanitized;
    }

    /**
     * Return $value if it is allowed, otherwise $default.
     */
    private static function pick($value, $allowed, $default) {
        $value = sanitize_key((string) $value);
        return in_array($value, $allowed, true) ? $value : $default;
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
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Whitelisted in pick().
        $poll->type = self::pick(isset($_POST['type']) ? wp_unslash($_POST['type']) : '', array_keys(TPoll_Poll::get_types()), 'single');
        $poll->status = self::pick(isset($_POST['status']) ? wp_unslash($_POST['status']) : '', array_keys(TPoll_Poll::get_statuses()), 'draft');
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        // Dates
        $poll->start_date = !empty($_POST['start_date']) ? self::to_mysql_date(sanitize_text_field(wp_unslash($_POST['start_date']))) : null;
        $poll->end_date = !empty($_POST['end_date']) ? self::to_mysql_date(sanitize_text_field(wp_unslash($_POST['end_date']))) : null;

        if ($poll->start_date && $poll->end_date && $poll->end_date <= $poll->start_date) {
            wp_send_json_error(array('message' => __('The end date must be after the start date.', '3task-polls')));
        }

        // Settings
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Whitelisted in pick().
        $poll->settings = array(
            'vote_restriction' => self::pick(isset($_POST['vote_restriction']) ? wp_unslash($_POST['vote_restriction']) : '', array('ip', 'cookie', 'user'), 'ip'),
            'show_results' => self::pick(isset($_POST['show_results']) ? wp_unslash($_POST['show_results']) : '', array('after_vote', 'always', 'never'), 'after_vote'),
            'allow_revote' => !empty($_POST['allow_revote']),
            'max_choices' => isset($_POST['max_choices']) ? absint($_POST['max_choices']) : 0,
            'result_chart' => self::pick(isset($_POST['result_chart']) ? wp_unslash($_POST['result_chart']) : '', array('bars', 'donut'), 'bars'),
            'per_post' => (bool) $poll->get_setting('per_post', false),
            'randomize_answers' => !empty($_POST['randomize_answers']),
            'show_voters_count' => !empty($_POST['show_voters_count']),
            'theme' => self::pick(isset($_POST['theme']) ? wp_unslash($_POST['theme']) : '', array_keys(TPoll_Shortcode::themes()), 'default'),
            'dark_mode' => self::pick(isset($_POST['dark_mode']) ? wp_unslash($_POST['dark_mode']) : '', array('auto', 'yes', 'no'), 'no')
        );
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        /**
         * Filters the poll settings before saving. Add-ons sanitize and add their own fields here.
         *
         * @param array      $settings Sanitized settings.
         * @param array      $raw      Unslashed POST data (not sanitized).
         * @param TPoll_Poll $poll     Poll being saved.
         */
        $poll->settings = (array) apply_filters('tpoll_save_poll_settings', $poll->settings, wp_unslash($_POST), $poll); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passed to sanitizing filters, nonce checked above.

        // Styles
        $poll->styles = array(
            'bar_color' => isset($_POST['bar_color']) ? (string) sanitize_hex_color(wp_unslash($_POST['bar_color'])) : '',
        );

        // Answers
        $answers = array();
        if (isset($_POST['answers']) && is_array($_POST['answers'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in loop below.
            $raw_answers = wp_unslash($_POST['answers']);
            foreach ($raw_answers as $answer) {
                if (is_array($answer) && isset($answer['text']) && trim((string) $answer['text']) !== '') {
                    $answers[] = array(
                        'id' => isset($answer['id']) ? absint($answer['id']) : 0,
                        'text' => sanitize_text_field($answer['text']),
                        'image' => isset($answer['image']) ? esc_url_raw($answer['image']) : ''
                    );
                }
            }
        }

        if (TPoll_Poll::is_choice_type($poll->type) && count($answers) < 2) {
            wp_send_json_error(array('message' => __('A poll needs at least two answers.', '3task-polls')));
        }

        /**
         * Lets add-ons validate a poll before it is saved. Return a WP_Error to stop.
         *
         * @param true       $valid   True.
         * @param TPoll_Poll $poll    Poll with the new values.
         * @param array      $answers Sanitized answers.
         */
        $valid = apply_filters('tpoll_validate_poll', true, $poll, $answers);
        if (is_wp_error($valid)) {
            wp_send_json_error(array('message' => $valid->get_error_message()));
        }

        // Types without an answer list use one internal answer: the question itself.
        if (!TPoll_Poll::is_choice_type($poll->type)) {
            $existing = $poll->id ? $poll->get_answers() : array();
            $answers = array(array(
                'id' => !empty($existing) ? (int) $existing[0]->id : 0,
                'text' => $poll->question,
                'image' => ''
            ));
        }

        // Save poll
        $poll_id = $poll->save();

        // Check for WP_Error (poll limit reached)
        if (is_wp_error($poll_id)) {
            wp_send_json_error(array('message' => $poll_id->get_error_message()));
        }

        if (!$poll_id) {
            wp_send_json_error(array('message' => __('Error saving poll.', '3task-polls')));
        }

        $poll->save_answers($answers);

        wp_send_json_success(array(
            'message' => __('Poll saved successfully!', '3task-polls'),
            'poll_id' => $poll_id,
            'redirect' => admin_url('admin.php?page=tpoll&tab=polls&action=edit&poll=' . $poll_id . '&saved=1')
        ));
    }

    /**
     * Convert the datetime-local value of the editor to MySQL format (site time).
     */
    private static function to_mysql_date($value) {
        $ts = strtotime($value);
        return $ts ? gmdate('Y-m-d H:i:s', $ts) : null;
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

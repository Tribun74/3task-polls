<?php
/**
 * TPoll Polls List Table
 *
 * @package TPoll
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class TPoll_Polls_List extends WP_List_Table {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(array(
            'singular' => 'poll',
            'plural' => 'polls',
            'ajax' => false
        ));
    }

    /**
     * Get columns
     */
    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'title' => __('Title', '3task-polls'),
            'type' => __('Type', '3task-polls'),
            'status' => __('Status', '3task-polls'),
            'votes' => __('Votes', '3task-polls'),
            'shortcode' => __('Shortcode', '3task-polls'),
            'date' => __('Date', '3task-polls')
        );
    }

    /**
     * Get sortable columns
     */
    public function get_sortable_columns() {
        return array(
            'title' => array('title', false),
            'type' => array('type', false),
            'status' => array('status', false),
            'date' => array('created_at', true)
        );
    }

    /**
     * Prepare items
     */
    public function prepare_items() {
        $per_page = 20;
        $current_page = $this->get_pagenum();

        $args = array(
            'per_page' => $per_page,
            'page' => $current_page,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );

        // Verify nonce for filter/sort parameters from list table form.
        $tpoll_has_nonce = isset($_GET['tpoll_filter_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['tpoll_filter_nonce'])), 'tpoll_list_filter');

        if ($tpoll_has_nonce) {
            if (isset($_GET['orderby'])) {
                $args['orderby'] = sanitize_text_field(wp_unslash($_GET['orderby']));
            }
            if (isset($_GET['order'])) {
                $args['order'] = sanitize_text_field(wp_unslash($_GET['order']));
            }
            if (!empty($_GET['status'])) {
                $args['status'] = sanitize_text_field(wp_unslash($_GET['status']));
            }
            if (!empty($_GET['poll_type'])) {
                $args['type'] = sanitize_text_field(wp_unslash($_GET['poll_type']));
            }
            if (!empty($_GET['s'])) {
                $args['search'] = sanitize_text_field(wp_unslash($_GET['s']));
            }
        }

        $this->items = TPoll_Poll::get_all($args);
        $total_items = TPoll_Poll::get_count($args);

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ));

        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns()
        );
    }

    /**
     * Get bulk actions
     */
    public function get_bulk_actions() {
        return array(
            'delete' => __('Delete', '3task-polls'),
            'publish' => __('Publish', '3task-polls'),
            'close' => __('Close', '3task-polls')
        );
    }

    /**
     * Column: Checkbox
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="poll_ids[]" value="%d" />',
            $item->id
        );
    }

    /**
     * Column: Title
     */
    public function column_title($item) {
        $edit_url = admin_url('admin.php?page=tpoll&tab=polls&action=edit&poll=' . $item->id);
        $delete_url = wp_nonce_url(
            admin_url('admin.php?page=tpoll&tab=polls&action=delete&poll=' . $item->id),
            'tpoll_delete_' . $item->id
        );

        $actions = array(
            'edit' => sprintf('<a href="%s">%s</a>', esc_url($edit_url), __('Edit', '3task-polls')),
            'duplicate' => sprintf(
                '<a href="#" class="tpoll-duplicate" data-poll-id="%d">%s</a>',
                $item->id,
                __('Duplicate', '3task-polls')
            ),
            'delete' => sprintf(
                '<a href="%s" class="tpoll-delete-link" data-confirm="%s">%s</a>',
                esc_url($delete_url),
                esc_attr(__('Are you sure you want to delete this poll?', '3task-polls')),
                __('Delete', '3task-polls')
            )
        );

        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            esc_url($edit_url),
            esc_html($item->title),
            $this->row_actions($actions)
        );
    }

    /**
     * Column: Type
     */
    public function column_type($item) {
        $types = TPoll_Poll::get_types();
        $type_label = isset($types[$item->type]) ? $types[$item->type] : $item->type;

        $icons = array(
            'single' => '○',
            'multiple' => '☑',
            'rating' => '★',
            'voting' => '👍'
        );

        $icon = isset($icons[$item->type]) ? $icons[$item->type] : '';

        return sprintf(
            '<span class="tpoll-type tpoll-type-%s">%s %s</span>',
            esc_attr($item->type),
            $icon,
            esc_html($type_label)
        );
    }

    /**
     * Column: Status
     */
    public function column_status($item) {
        $statuses = TPoll_Poll::get_statuses();
        $status_label = isset($statuses[$item->status]) ? $statuses[$item->status] : $item->status;

        $status_classes = array(
            'draft' => 'status-draft',
            'published' => 'status-published',
            'closed' => 'status-closed',
            'scheduled' => 'status-scheduled'
        );

        $class = isset($status_classes[$item->status]) ? $status_classes[$item->status] : '';

        return sprintf(
            '<span class="tpoll-status %s">%s</span>',
            esc_attr($class),
            esc_html($status_label)
        );
    }

    /**
     * Column: Votes
     */
    public function column_votes($item) {
        $poll = TPoll_Poll::get($item->id);
        $total_votes = $poll ? $poll->get_total_votes() : 0;
        $total_voters = $poll ? $poll->get_total_voters() : 0;

        return sprintf(
            '<span class="tpoll-votes">%d</span><br><small>%s</small>',
            absint($total_votes),
            /* translators: %d: number of voters */
            sprintf(_n('%d voter', '%d voters', $total_voters, '3task-polls'), absint($total_voters))
        );
    }

    /**
     * Column: Shortcode
     */
    public function column_shortcode($item) {
        $shortcode = '[tpoll id="' . $item->id . '"]';

        return sprintf(
            '<code class="tpoll-shortcode-copy" title="%s">%s</code>',
            esc_attr__('Click to copy', '3task-polls'),
            esc_html($shortcode)
        );
    }

    /**
     * Column: Date
     */
    public function column_date($item) {
        $date = strtotime($item->created_at);

        return sprintf(
            '<span title="%s">%s</span>',
            esc_attr(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $date)),
            esc_html(human_time_diff($date, current_time('timestamp')) . ' ' . __('ago', '3task-polls'))
        );
    }

    /**
     * Column: Default
     */
    public function column_default($item, $column_name) {
        return isset($item->$column_name) ? esc_html($item->$column_name) : '';
    }

    /**
     * No items message
     */
    public function no_items() {
        esc_html_e('No polls found.', '3task-polls');
    }

    /**
     * Extra table nav (filters)
     */
    public function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }

        $current_status = '';
        $current_type = '';
        if (isset($_GET['tpoll_filter_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['tpoll_filter_nonce'])), 'tpoll_list_filter')) {
            $current_status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
            $current_type = isset($_GET['poll_type']) ? sanitize_text_field(wp_unslash($_GET['poll_type'])) : '';
        }

        ?>
        <div class="alignleft actions">
            <select name="status">
                <option value=""><?php esc_html_e('All Statuses', '3task-polls'); ?></option>
                <?php foreach (TPoll_Poll::get_statuses() as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($current_status, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="poll_type">
                <option value=""><?php esc_html_e('All Types', '3task-polls'); ?></option>
                <?php foreach (TPoll_Poll::get_types() as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($current_type, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="submit" class="button" value="<?php esc_attr_e('Filter', '3task-polls'); ?>">
        </div>
        <?php
    }

    /**
     * Get views (status links)
     */
    public function get_views() {
        $current = '';
        if (isset($_GET['tpoll_filter_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['tpoll_filter_nonce'])), 'tpoll_list_filter')) {
            $current = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        }

        $views = array();

        // All
        $all_count = TPoll_Poll::get_count();
        $views['all'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            admin_url('admin.php?page=tpoll&tab=polls'),
            $current === '' ? 'current' : '',
            __('All', '3task-polls'),
            $all_count
        );

        // By status
        foreach (TPoll_Poll::get_statuses() as $status => $label) {
            $count = TPoll_Poll::get_count(array('status' => $status));
            if ($count > 0) {
                $views[$status] = sprintf(
                    '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
                    admin_url('admin.php?page=tpoll&tab=polls&status=' . $status),
                    $current === $status ? 'current' : '',
                    $label,
                    $count
                );
            }
        }

        return $views;
    }
}

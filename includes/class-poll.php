<?php
/**
 * TPoll Poll Model
 *
 * @package TPoll
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPoll_Poll {

    /**
     * Poll ID
     */
    public $id = 0;

    /**
     * Poll title
     */
    public $title = '';

    /**
     * Poll question
     */
    public $question = '';

    /**
     * Poll type: single, multiple, rating, voting
     */
    public $type = 'single';

    /**
     * Poll status: draft, published, closed, scheduled
     */
    public $status = 'draft';

    /**
     * Poll settings (JSON)
     */
    public $settings = array();

    /**
     * Poll styles (JSON)
     */
    public $styles = array();

    /**
     * Start date
     */
    public $start_date = null;

    /**
     * End date
     */
    public $end_date = null;

    /**
     * Created at
     */
    public $created_at = '';

    /**
     * Updated at
     */
    public $updated_at = '';

    /**
     * Created by
     */
    public $created_by = 0;

    /**
     * Answers
     */
    public $answers = array();

    /**
     * Constructor
     */
    public function __construct($data = null) {
        if ($data) {
            $this->populate($data);
        }
    }

    /**
     * Populate from database row
     */
    private function populate($data) {
        $this->id = (int) $data->id;
        $this->title = $data->title;
        $this->question = $data->question;
        $this->type = $data->type;
        $this->status = $data->status;
        $this->settings = $data->settings ? json_decode($data->settings, true) : array();
        $this->styles = $data->styles ? json_decode($data->styles, true) : array();
        $this->start_date = $data->start_date;
        $this->end_date = $data->end_date;
        $this->created_at = $data->created_at;
        $this->updated_at = $data->updated_at;
        $this->created_by = (int) $data->created_by;
    }

    /**
     * Get poll by ID
     */
    public static function get($id) {
        global $wpdb;

        $table = $wpdb->prefix . 'tpoll_polls';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, table name is safe.
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (!$row) {
            return null;
        }

        $poll = new self($row);
        $poll->load_answers();

        return $poll;
    }

    /**
     * Get all polls
     */
    public static function get_all($args = array()) {
        global $wpdb;

        $defaults = array(
            'status' => '',
            'type' => '',
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'per_page' => 20,
            'page' => 1
        );

        $args = wp_parse_args($args, $defaults);
        $table = $wpdb->prefix . 'tpoll_polls';

        $where = array('1=1');
        $values = array();

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if (!empty($args['type'])) {
            $where[] = 'type = %s';
            $values[] = $args['type'];
        }

        if (!empty($args['search'])) {
            $where[] = '(title LIKE %s OR question LIKE %s)';
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search;
            $values[] = $search;
        }

        $where_clause = implode(' AND ', $where);

        // Sanitize orderby against strict whitelist - these are column names, not user data.
        $allowed_orderby = array('id', 'title', 'type', 'status', 'created_at', 'updated_at');
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $offset = ($args['page'] - 1) * $args['per_page'];
        $values[] = $args['per_page'];
        $values[] = $offset;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, $where_clause uses placeholders, $orderby/$order are whitelist-validated.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                ...$values
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $polls = array();
        foreach ($results as $row) {
            $polls[] = new self($row);
        }

        return $polls;
    }

    /**
     * Get total count
     */
    public static function get_count($args = array()) {
        global $wpdb;

        $table = $wpdb->prefix . 'tpoll_polls';

        $where = array('1=1');
        $values = array();

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if (!empty($args['type'])) {
            $where[] = 'type = %s';
            $values[] = $args['type'];
        }

        $where_clause = implode(' AND ', $where);

        if (!empty($values)) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, $where_clause uses placeholders.
            return (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where_clause}", ...$values)
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, no user input.
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE 1=1");
    }

    /**
     * Save poll
     */
    public function save() {
        global $wpdb;

        $table = $wpdb->prefix . 'tpoll_polls';

        $data = array(
            'title' => $this->title,
            'question' => $this->question,
            'type' => $this->type,
            'status' => $this->status,
            'settings' => wp_json_encode($this->settings),
            'styles' => wp_json_encode($this->styles),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'updated_at' => current_time('mysql')
        );

        $format = array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s');

        if ($this->id) {
            // Update
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
            $wpdb->update($table, $data, array('id' => $this->id), $format, array('%d'));
        } else {
            // Insert
            $data['created_at'] = current_time('mysql');
            $data['created_by'] = get_current_user_id();
            $format[] = '%s';
            $format[] = '%d';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table.
            $wpdb->insert($table, $data, $format);
            $this->id = $wpdb->insert_id;
        }

        return $this->id;
    }

    /**
     * Delete poll
     */
    public function delete() {
        global $wpdb;

        if (!$this->id) {
            return false;
        }

        // Delete answers
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
        $wpdb->delete(
            $wpdb->prefix . 'tpoll_answers',
            array('poll_id' => $this->id),
            array('%d')
        );

        // Delete votes
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
        $wpdb->delete(
            $wpdb->prefix . 'tpoll_votes',
            array('poll_id' => $this->id),
            array('%d')
        );

        // Delete poll
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
        return $wpdb->delete(
            $wpdb->prefix . 'tpoll_polls',
            array('id' => $this->id),
            array('%d')
        );
    }

    /**
     * Load answers
     */
    public function load_answers() {
        global $wpdb;

        $table = $wpdb->prefix . 'tpoll_answers';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, table name is safe.
        $this->answers = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE poll_id = %d ORDER BY sort_order ASC",
            $this->id
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        return $this->answers;
    }

    /**
     * Get answers
     */
    public function get_answers() {
        if (empty($this->answers)) {
            $this->load_answers();
        }
        return $this->answers;
    }

    /**
     * Save answers
     */
    public function save_answers($answers) {
        global $wpdb;

        $table = $wpdb->prefix . 'tpoll_answers';

        // Delete existing answers
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
        $wpdb->delete($table, array('poll_id' => $this->id), array('%d'));

        // Insert new answers
        foreach ($answers as $index => $answer) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table.
            $wpdb->insert(
                $table,
                array(
                    'poll_id' => $this->id,
                    'answer_text' => sanitize_text_field($answer['text']),
                    'answer_image' => isset($answer['image']) ? esc_url_raw($answer['image']) : null,
                    'sort_order' => $index
                ),
                array('%d', '%s', '%s', '%d')
            );
        }

        $this->load_answers();
    }

    /**
     * Get total votes
     */
    public function get_total_votes() {
        global $wpdb;

        $table = $wpdb->prefix . 'tpoll_votes';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, table name is safe.
        $tpoll_result = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE poll_id = %d",
            $this->id
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $tpoll_result;
    }

    /**
     * Get total voters (unique)
     */
    public function get_total_voters() {
        global $wpdb;

        $table = $wpdb->prefix . 'tpoll_votes';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, table name is safe.
        $tpoll_result = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT ip_hash) FROM $table WHERE poll_id = %d",
            $this->id
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $tpoll_result;
    }

    /**
     * Check if poll is active
     */
    public function is_active() {
        if ($this->status === 'draft' || $this->status === 'closed') {
            return false;
        }

        if (!in_array($this->status, array('published', 'scheduled'), true)) {
            return false;
        }

        $now = current_time('mysql');

        if ($this->status === 'scheduled' && empty($this->start_date)) {
            return false;
        }

        if (!empty($this->start_date) && $now < $this->start_date) {
            return false;
        }

        if (!empty($this->end_date) && $now > $this->end_date) {
            return false;
        }

        return true;
    }

    /**
     * Check if poll is closed
     */
    public function is_closed() {
        if ($this->status === 'closed') {
            return true;
        }

        if ($this->end_date && current_time('mysql') > $this->end_date) {
            return true;
        }

        return false;
    }

    /**
     * Get setting
     */
    public function get_setting($key, $default = null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    /**
     * Get style
     */
    public function get_style($key, $default = null) {
        return isset($this->styles[$key]) ? $this->styles[$key] : $default;
    }

    /**
     * Get poll types
     */
    public static function get_types() {
        return array(
            'single' => __('Single Choice', '3task-polls'),
            'multiple' => __('Multiple Choice', '3task-polls'),
            'rating' => __('Rating (Stars)', '3task-polls'),
            'voting' => __('Voting (Thumbs)', '3task-polls')
        );
    }

    /**
     * Get poll statuses
     */
    public static function get_statuses() {
        return array(
            'draft' => __('Draft', '3task-polls'),
            'published' => __('Published', '3task-polls'),
            'closed' => __('Closed', '3task-polls'),
            'scheduled' => __('Scheduled', '3task-polls')
        );
    }

    /**
     * Duplicate poll
     */
    public function duplicate() {
        $new_poll = new self();
        $new_poll->title = $this->title . ' ' . __('(Copy)', '3task-polls');
        $new_poll->question = $this->question;
        $new_poll->type = $this->type;
        $new_poll->status = 'draft';
        $new_poll->settings = $this->settings;
        $new_poll->styles = $this->styles;
        $new_poll->save();

        // Duplicate answers
        $answers = array();
        foreach ($this->get_answers() as $answer) {
            $answers[] = array(
                'text' => $answer->answer_text,
                'image' => $answer->answer_image
            );
        }
        $new_poll->save_answers($answers);

        return $new_poll;
    }
}

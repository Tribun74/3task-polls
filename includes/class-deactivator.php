<?php
/**
 * TPoll Deactivator
 *
 * @package TPoll
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPoll_Deactivator {

    /**
     * Deactivate plugin
     */
    public static function deactivate() {
        wp_clear_scheduled_hook('tpoll_daily_cleanup');
    }
}

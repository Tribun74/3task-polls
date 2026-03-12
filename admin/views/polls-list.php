<?php
/**
 * Polls List View
 *
 * @package TPoll
 */

if (!defined('ABSPATH')) {
    exit;
}

$tpoll_list_table = new TPoll_Polls_List();
$tpoll_list_table->prepare_items();
?>
<div class="wrap tpoll-wrap">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('TPoll', '3task-polls'); ?>
    </h1>

    <a href="<?php echo esc_url(admin_url('admin.php?page=tpoll&tab=polls&action=new')); ?>" class="page-title-action">
        <?php esc_html_e('Add New', '3task-polls'); ?>
    </a>

    <?php if (isset($_GET['deleted'], $_GET['tpoll_notice']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['tpoll_notice'])), 'tpoll_admin_notice')) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php
                $tpoll_deleted_count = absint($_GET['deleted']);
                printf(
                    /* translators: %d: number of deleted polls */
                    esc_html(_n('%d poll deleted.', '%d polls deleted.', $tpoll_deleted_count, '3task-polls')),
                    absint($tpoll_deleted_count)
                );
            ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'], $_GET['tpoll_notice']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['tpoll_notice'])), 'tpoll_admin_notice')) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php
                $tpoll_updated_count = absint($_GET['updated']);
                printf(
                    /* translators: %d: number of updated polls */
                    esc_html(_n('%d poll updated.', '%d polls updated.', $tpoll_updated_count, '3task-polls')),
                    absint($tpoll_updated_count)
                );
            ?></p>
        </div>
    <?php endif; ?>

    <hr class="wp-header-end">

    <?php $tpoll_list_table->views(); ?>

    <form method="get">
        <input type="hidden" name="page" value="tpoll">
        <input type="hidden" name="tab" value="polls">
        <?php wp_nonce_field('tpoll_list_filter', 'tpoll_filter_nonce'); ?>
        <?php
        $tpoll_list_table->search_box(__('Search Polls', '3task-polls'), 'poll');
        $tpoll_list_table->display();
        ?>
    </form>
</div>

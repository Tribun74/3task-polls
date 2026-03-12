/**
 * 3task Polls Admin JavaScript v1.3
 *
 * @package TPoll
 * @since 1.0.0
 */

jQuery(document).ready(function($) {

    // Plugin-specific initialization
    TPollAdmin.init();

    // Common framework
    TpollFramework.init();

});

/**
 * TPoll Admin Object
 */
var TPollAdmin = {

    /**
     * Initialize TPoll Admin
     */
    init: function() {
        this.initColorPicker();
        this.initAnswerManagement();
        this.bindEvents();
    },

    /**
     * Initialize color picker
     */
    initColorPicker: function() {
        if (jQuery.fn.wpColorPicker) {
            jQuery('.wp-color-picker, .tpoll-color-picker').wpColorPicker();
        }
    },

    /**
     * Initialize answer management
     */
    initAnswerManagement: function() {
        jQuery(document).on('click', '.add-answer-btn, #tpoll-add-answer', this.addAnswer);
        jQuery(document).on('click', '.remove-answer, .tpoll-remove-answer', this.removeAnswer);
    },

    /**
     * Bind events
     */
    bindEvents: function() {
        var $ = jQuery;

        // Shortcode copy - poll edit page
        $(document).on('click', '.tpoll-shortcode-display', this.copyShortcode);

        // Shortcode copy - polls list table
        $(document).on('click', '.tpoll-shortcode-copy', this.copyShortcode);

        // Poll type change
        $(document).on('change', 'input[name="type"], input[name="poll_type"]', this.handlePollTypeChange);

        // Delete poll - card grid (dashboard tab)
        $(document).on('click', '.tpoll-delete-btn', this.handleDeletePoll);

        // Delete poll - list table
        $(document).on('click', '.tpoll-delete-link', this.handleDeleteLink);

        // Duplicate poll
        $(document).on('click', '.tpoll-duplicate', this.handleDuplicatePoll);

        // Reset votes
        $(document).on('click', '.tpoll-reset-votes', this.handleResetVotes);

        // Save poll form
        $(document).on('submit', '#tpoll-edit-form', this.handleSavePoll);
    },

    /**
     * Add new answer
     */
    addAnswer: function(e) {
        e.preventDefault();
        var $ = jQuery;

        var $list = $('#tpoll-answers-list, .poll-answers-list');
        var count = $list.find('.tpoll-answer-row, .poll-answer-item').length;

        var tmpl = wp.template('tpoll-answer-row');
        if (typeof tmpl === 'function') {
            $list.append(tmpl({ index: count, num: count + 1 }));
        } else {
            // Fallback
            var html = '<div class="tpoll-answer-row" data-index="' + count + '">' +
                '<span class="tpoll-answer-handle dashicons dashicons-move"></span>' +
                '<input type="text" name="answers[' + count + '][text]" value="" placeholder="Answer ' + (count + 1) + '" class="tpoll-answer-input">' +
                '<button type="button" class="tpoll-remove-answer button-link-delete" title="Remove">' +
                '<span class="dashicons dashicons-trash"></span>' +
                '</button></div>';
            $list.append(html);
        }
    },

    /**
     * Remove answer
     */
    removeAnswer: function(e) {
        e.preventDefault();
        var $ = jQuery;

        var $row = $(this).closest('.tpoll-answer-row, .poll-answer-item');
        var $list = $row.parent();

        if ($list.children().length <= 2) {
            window.alert(tpollAdmin.strings.error || 'At least 2 answers are required.');
            return;
        }

        $row.fadeOut(300, function() {
            $(this).remove();
        });
    },

    /**
     * Copy shortcode to clipboard
     */
    copyShortcode: function(e) {
        e.preventDefault();
        var $ = jQuery;
        var $el = $(this);
        var text = $el.text().trim();

        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                $el.addClass('copied');
                setTimeout(function() {
                    $el.removeClass('copied');
                }, 2000);
            });
        } else {
            // Fallback
            var textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            $el.addClass('copied');
            setTimeout(function() {
                $el.removeClass('copied');
            }, 2000);
        }
    },

    /**
     * Handle poll type change
     */
    handlePollTypeChange: function() {
        var $ = jQuery;
        var type = $(this).val();

        // Update selected state
        $('.tpoll-type-option').removeClass('selected');
        $(this).closest('.tpoll-type-option').addClass('selected');

        // Show/hide type-specific options
        if (type === 'multiple') {
            $('.tpoll-multiple-only').removeClass('tpoll-hidden');
            $('.tpoll-rating-hint').addClass('tpoll-hidden');
        } else if (type === 'rating' || type === 'voting') {
            $('.tpoll-multiple-only').addClass('tpoll-hidden');
            $('.tpoll-rating-hint').removeClass('tpoll-hidden');
        } else {
            $('.tpoll-multiple-only').addClass('tpoll-hidden');
            $('.tpoll-rating-hint').addClass('tpoll-hidden');
        }
    },

    /**
     * Handle delete poll (card button)
     */
    handleDeletePoll: function(e) {
        e.preventDefault();
        var $ = jQuery;
        var pollId = $(this).data('poll-id');

        if (!window.confirm(tpollAdmin.strings.confirmDelete || 'Are you sure you want to delete this poll?')) {
            return;
        }

        $.ajax({
            url: tpollAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tpoll_delete_poll',
                poll_id: pollId,
                nonce: tpollAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    window.alert(response.data.message || tpollAdmin.strings.error);
                }
            }
        });
    },

    /**
     * Handle delete link (list table)
     */
    handleDeleteLink: function(e) {
        var confirmMsg = jQuery(this).data('confirm');
        if (!window.confirm(confirmMsg || 'Are you sure?')) {
            e.preventDefault();
        }
        // Let the link navigate normally if confirmed
    },

    /**
     * Handle duplicate poll
     */
    handleDuplicatePoll: function(e) {
        e.preventDefault();
        var $ = jQuery;
        var pollId = $(this).data('poll-id');

        $.ajax({
            url: tpollAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tpoll_duplicate_poll',
                poll_id: pollId,
                nonce: tpollAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    window.alert(response.data.message || tpollAdmin.strings.error);
                }
            }
        });
    },

    /**
     * Handle reset votes
     */
    handleResetVotes: function(e) {
        e.preventDefault();
        var $ = jQuery;
        var pollId = $(this).data('poll-id');

        if (!window.confirm(tpollAdmin.strings.confirmReset || 'Are you sure?')) {
            return;
        }

        $.ajax({
            url: tpollAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tpoll_reset_votes',
                poll_id: pollId,
                nonce: tpollAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    window.location.reload();
                }
            }
        });
    },

    /**
     * Handle save poll form
     */
    handleSavePoll: function(e) {
        e.preventDefault();
        var $ = jQuery;
        var $form = $(this);
        var $btn = $form.find('#tpoll-save');

        $btn.prop('disabled', true).addClass('tpoll-loading');

        $.ajax({
            url: tpollAdmin.ajaxUrl,
            type: 'POST',
            data: $form.serialize() + '&action=tpoll_save_poll&nonce=' + tpollAdmin.nonce,
            success: function(response) {
                if (response.success) {
                    if (response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        window.location.reload();
                    }
                } else {
                    window.alert(response.data.message || tpollAdmin.strings.error);
                    $btn.prop('disabled', false).removeClass('tpoll-loading');
                }
            },
            error: function() {
                window.alert(tpollAdmin.strings.error);
                $btn.prop('disabled', false).removeClass('tpoll-loading');
            }
        });
    }
};

/**
 * Common Framework
 */
var TpollFramework = {

    /**
     * Initialize
     */
    init: function() {
        this.initResponsiveNavigation();
        this.initNotificationSystem();
    },

    /**
     * Responsive navigation
     */
    initResponsiveNavigation: function() {
        jQuery(window).on('resize', function() {
            if (jQuery(window).width() <= 782) {
                jQuery('.tpoll-tabs').addClass('mobile-view');
            } else {
                jQuery('.tpoll-tabs').removeClass('mobile-view');
            }
        }).trigger('resize');
    },

    /**
     * Notification system
     */
    initNotificationSystem: function() {
        jQuery('.notice-success').delay(5000).fadeOut();

        jQuery(document).on('click', '.notice-dismiss', function() {
            jQuery(this).closest('.notice').fadeOut();
        });
    }
};

/**
 * Utility Functions
 */
var TPollUtils = {

    showLoading: function($element) {
        $element.addClass('tpoll-loading');
        $element.find('.dashicons').addClass('tpoll-spin');
    },

    hideLoading: function($element) {
        $element.removeClass('tpoll-loading');
        $element.find('.dashicons').removeClass('tpoll-spin');
    },

    showNotification: function(message, type) {
        type = type || 'info';

        var notification = jQuery(
            '<div class="tpoll-notice notice-' + type + '">' +
            '<p>' + message + '</p>' +
            '<button type="button" class="notice-dismiss"></button>' +
            '</div>'
        );

        jQuery('.tpoll-tab-content').prepend(notification);

        setTimeout(function() {
            notification.fadeOut();
        }, 5000);
    },

    formatNumber: function(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    },

    debounce: function(func, wait) {
        var timeout;
        return function() {
            var context = this;
            var args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }
};

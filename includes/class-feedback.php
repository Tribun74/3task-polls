<?php
/**
 * Feedback below posts: "Was this post helpful?" or reactions.
 *
 * One poll per style is created once and counted per post (context = post ID),
 * so there is no extra poll for every post.
 *
 * @package TPoll
 */

if (!defined('ABSPATH')) {
    exit;
}

class TPoll_Feedback {

    const OPTION = 'tpoll_feedback_polls';

    /**
     * Hooks.
     */
    public static function init() {
        add_filter('the_content', array(__CLASS__, 'append'), 20);
        add_action('update_option_tpoll_settings', array(__CLASS__, 'sync'), 10, 2);
        add_action('add_option_tpoll_settings', array(__CLASS__, 'sync_added'), 10, 2);
        add_filter('tpoll_admin_tabs', array(__CLASS__, 'tab'));
        add_action('tpoll_admin_tab_feedback', array(__CLASS__, 'render_tab'));
    }

    /**
     * Settings of the feature.
     *
     * @return array
     */
    public static function settings() {
        $s = get_option('tpoll_settings', array());
        return array(
            'enabled' => !empty($s['feedback_enabled']),
            'style' => (isset($s['feedback_style']) && $s['feedback_style'] === 'reactions') ? 'reactions' : 'helpful',
            'post_types' => !empty($s['feedback_post_types']) && is_array($s['feedback_post_types']) ? $s['feedback_post_types'] : array('post'),
            'question' => isset($s['feedback_question']) ? (string) $s['feedback_question'] : '',
        );
    }

    /**
     * Question shown below the post.
     *
     * @param string $style helpful or reactions.
     * @param string $custom Text from the settings.
     */
    public static function question($style, $custom = '') {
        if ('' !== trim($custom)) {
            return $custom;
        }
        return $style === 'reactions'
            ? __('How do you like this post?', '3task-polls')
            : __('Was this post helpful?', '3task-polls');
    }

    /**
     * Poll ID for a style, 0 if it does not exist (any more).
     *
     * @param string $style helpful or reactions.
     * @return int
     */
    public static function poll_id($style) {
        $ids = get_option(self::OPTION, array());
        $id = is_array($ids) && isset($ids[$style]) ? absint($ids[$style]) : 0;
        return ($id && TPoll_Poll::get($id)) ? $id : 0;
    }

    /**
     * Create or update the poll after the settings were saved.
     *
     * @param mixed $old Old value.
     * @param mixed $new New value.
     */
    public static function sync($old, $new) {
        if (!is_array($new) || empty($new['feedback_enabled'])) {
            return;
        }
        $style = (isset($new['feedback_style']) && $new['feedback_style'] === 'reactions') ? 'reactions' : 'helpful';
        $question = self::question($style, isset($new['feedback_question']) ? (string) $new['feedback_question'] : '');

        $id = self::poll_id($style);
        $poll = $id ? TPoll_Poll::get($id) : new TPoll_Poll();

        if (!$id) {
            $poll->title = $style === 'reactions' ? __('Reactions below posts', '3task-polls') : __('Feedback below posts', '3task-polls');
            $poll->type = $style === 'reactions' ? 'reactions' : 'voting';
            $poll->settings = array(
                'vote_restriction' => 'ip',
                'show_results' => $style === 'reactions' ? 'always' : 'after_vote',
                'show_voters_count' => false,
                'per_post' => true,
                'theme' => 'minimal',
                'dark_mode' => 'no',
            );
        }
        $poll->question = $question;
        $poll->status = 'published';
        $poll->save();

        if (!$id) {
            if ($style === 'reactions') {
                $answers = array();
                foreach (array('👍', '❤️', '😂', '😮', '😢') as $emoji) {
                    $answers[] = array('text' => $emoji);
                }
            } else {
                $answers = array(array('text' => $question));
            }
            $poll->save_answers($answers);

            $ids = get_option(self::OPTION, array());
            $ids = is_array($ids) ? $ids : array();
            $ids[$style] = (int) $poll->id;
            update_option(self::OPTION, $ids, false);
        }
    }

    /**
     * Same as sync() for the very first save of the settings.
     *
     * @param string $option Option name.
     * @param mixed  $value  Value.
     */
    public static function sync_added($option, $value) {
        self::sync(array(), $value);
    }

    /**
     * Append the feedback poll below the content of a single post.
     *
     * @param string $content Content.
     * @return string
     */
    public static function append($content) {
        $s = self::settings();
        // Only the post that was requested (works in classic and block themes), and not while
        // SEO plugins read the content for the description in the <head>.
        if (!$s['enabled'] || is_admin() || is_feed() || doing_action('wp_head') || !is_singular($s['post_types'])) {
            return $content;
        }

        $post_id = (int) get_the_ID();
        if ($post_id !== (int) get_queried_object_id()) {
            return $content;
        }
        $poll_id = self::poll_id($s['style']);

        /**
         * Filters whether the feedback poll is shown below this post.
         *
         * @param bool $show    True.
         * @param int  $post_id Post ID.
         */
        if (!$poll_id || !$post_id || !apply_filters('tpoll_feedback_show', true, $post_id)) {
            return $content;
        }

        $html = do_shortcode('[tpoll id="' . $poll_id . '" context="' . (int) $post_id . '"]');

        return $content . '<div class="tpoll-feedback">' . $html . '</div>';
    }

    /**
     * Admin tab, shown while the feature is on or has data.
     *
     * @param array $tabs Tabs.
     * @return array
     */
    public static function tab($tabs) {
        if (self::settings()['enabled'] || self::poll_id('helpful') || self::poll_id('reactions')) {
            $tabs['feedback'] = array(
                'title' => __('Feedback', '3task-polls'),
                'icon' => 'dashicons-thumbs-up',
            );
        }
        return $tabs;
    }

    /**
     * Per-post results.
     */
    public static function render_tab() {
        global $wpdb;

        if (!current_user_can('view_tpoll_results') && !current_user_can('manage_tpoll')) {
            return;
        }

        $s = self::settings();
        echo '<div class="tpoll-card"><div class="tpoll-card-header"><h2 class="tpoll-card-title">' . esc_html__('Feedback below posts', '3task-polls') . '</h2></div>';

        if (!$s['enabled']) {
            echo '<p>' . esc_html__('The feedback below posts is switched off. You can switch it on in the settings.', '3task-polls') . '</p>';
        }

        foreach (array('helpful', 'reactions') as $style) {
            $poll_id = self::poll_id($style);
            if (!$poll_id) {
                continue;
            }
            $poll = TPoll_Poll::get($poll_id);
            $answers = $poll->get_answers();

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, aggregated per post.
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT context_id, answer_id, vote_value, COUNT(*) AS c
                 FROM {$wpdb->prefix}tpoll_votes
                 WHERE poll_id = %d AND context_id > 0
                 GROUP BY context_id, answer_id, vote_value",
                $poll_id
            ));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

            $per_post = array();
            foreach ((array) $rows as $row) {
                $pid = (int) $row->context_id;
                if (!isset($per_post[$pid])) {
                    $per_post[$pid] = array('total' => 0, 'up' => 0, 'down' => 0, 'answers' => array());
                }
                $per_post[$pid]['total'] += (int) $row->c;
                if ($style === 'helpful') {
                    if ((int) $row->vote_value > 0) {
                        $per_post[$pid]['up'] += (int) $row->c;
                    } else {
                        $per_post[$pid]['down'] += (int) $row->c;
                    }
                } else {
                    $aid = (int) $row->answer_id;
                    $per_post[$pid]['answers'][$aid] = (isset($per_post[$pid]['answers'][$aid]) ? $per_post[$pid]['answers'][$aid] : 0) + (int) $row->c;
                }
            }
            uasort($per_post, function ($a, $b) {
                return $b['total'] - $a['total'];
            });
            $per_post = array_slice($per_post, 0, 100, true);

            echo '<h3>' . esc_html($style === 'reactions' ? __('Reactions', '3task-polls') : __('Was this post helpful?', '3task-polls')) . '</h3>';
            if (empty($per_post)) {
                echo '<p class="description">' . esc_html__('No votes yet.', '3task-polls') . '</p>';
                continue;
            }

            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Post', '3task-polls') . '</th>';
            if ($style === 'helpful') {
                echo '<th>' . esc_html__('Yes', '3task-polls') . '</th><th>' . esc_html__('No', '3task-polls') . '</th><th>' . esc_html__('Helpful', '3task-polls') . '</th>';
            } else {
                foreach ($answers as $answer) {
                    echo '<th>' . esc_html($answer->answer_text) . '</th>';
                }
            }
            echo '<th>' . esc_html__('Votes', '3task-polls') . '</th></tr></thead><tbody>';

            foreach ($per_post as $pid => $data) {
                $title = get_the_title($pid);
                echo '<tr><td><a href="' . esc_url((string) get_permalink($pid)) . '">' . esc_html('' !== $title ? $title : '#' . $pid) . '</a></td>';
                if ($style === 'helpful') {
                    $share = $data['total'] > 0 ? round($data['up'] / $data['total'] * 100) : 0;
                    echo '<td>' . absint($data['up']) . '</td><td>' . absint($data['down']) . '</td><td>' . esc_html(number_format_i18n($share) . ' %') . '</td>';
                } else {
                    foreach ($answers as $answer) {
                        echo '<td>' . absint(isset($data['answers'][(int) $answer->id]) ? $data['answers'][(int) $answer->id] : 0) . '</td>';
                    }
                }
                echo '<td>' . absint($data['total']) . '</td></tr>';
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }
}

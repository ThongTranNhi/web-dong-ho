<?php
if (!defined('ABSPATH')) exit;

final class Luxury_Auction_Admin {
    public static function init() {
        add_action('add_meta_boxes_watch_auction', [__CLASS__, 'add_meta_box']);
        add_action('admin_footer', [__CLASS__, 'confirmation_form']);
        add_action('admin_post_luxury_confirm_auction', [__CLASS__, 'handle_confirmation']);
        add_action('admin_notices', [__CLASS__, 'notice']);
    }

    private static function can_manage($auction_id) {
        return current_user_can('manage_woocommerce') && current_user_can('edit_post', $auction_id);
    }

    private static function highest_bid($auction_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'luxury_auction_bids';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, amount, created_at FROM {$table} WHERE auction_id = %d ORDER BY amount DESC, id ASC LIMIT 1",
            $auction_id
        ));
    }

    public static function confirm_result($auction_id) {
        if (!self::can_manage($auction_id)) return new WP_Error('forbidden', 'Bạn không có quyền xác nhận kết quả phiên.');
        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) return new WP_Error('database', 'Không thể bắt đầu lưu kết quả.');
        try {
            $locked = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'watch_auction' FOR UPDATE", $auction_id));
            if (!$locked || $wpdb->last_error) return new WP_Error('database', 'Không thể đọc phiên đấu giá.');
            clean_post_cache($auction_id);
            $existing = get_post_meta($auction_id, '_lw_auction_result', true);
            if ($existing) return $existing;
            if (get_post_status($auction_id) !== 'publish' || Luxury_Watches_Auctions::state($auction_id) !== 'ended') {
                return new WP_Error('not_ended', 'Chỉ xác nhận kết quả của phiên đã công bố và hết thời gian.');
            }
            $bid = self::highest_bid($auction_id);
            if ($wpdb->last_error) return new WP_Error('database', 'Không thể đọc lượt trả giá.');
            if (!$bid) return new WP_Error('no_bids', 'Phiên chưa có lượt trả giá để xác định người thắng.');
            $reserve = get_post_meta($auction_id, '_lw_reserve_price', true);
            if ($reserve !== '' && (!ctype_digit((string) $reserve) || (float) $reserve > PHP_INT_MAX)) {
                return new WP_Error('reserve', 'Cần kiểm tra lại cấu hình giá dự trữ.');
            }
            if ((int) $bid->amount < (int) $reserve) return new WP_Error('reserve', 'Giá cao nhất chưa đạt giá dự trữ.');
            if (!get_userdata($bid->user_id)) return new WP_Error('bidder', 'Tài khoản người trả giá không còn tồn tại.');
            $result = [
                'bid_id' => (int) $bid->id,
                'user_id' => (int) $bid->user_id,
                'amount' => (int) $bid->amount,
                'confirmed_by' => get_current_user_id(),
                'confirmed_at' => current_time('mysql'),
            ];
            if (!update_post_meta($auction_id, '_lw_auction_result', $result) || $wpdb->query('COMMIT') === false) {
                return new WP_Error('database', 'Không thể lưu kết quả. Vui lòng tải lại và kiểm tra trước khi thử lại.');
            }
            return $result;
        } finally {
            // Also releases the row lock on rejected and repeated confirmations.
            $wpdb->query('ROLLBACK');
            clean_post_cache($auction_id);
        }
    }

    public static function add_meta_box($post) {
        if (self::can_manage($post->ID)) {
            add_meta_box('luxury-auction-result', 'Kết quả và lịch sử trả giá', [__CLASS__, 'meta_box'], 'watch_auction', 'normal');
        }
    }

    public static function meta_box($post) {
        if (!self::can_manage($post->ID)) return;
        $result = get_post_meta($post->ID, '_lw_auction_result', true);
        if ($result) {
            $winner = get_userdata($result['user_id']);
            echo '<p><strong>Đã xác nhận người thắng</strong></p><p>' . esc_html($winner ? $winner->display_name : 'Tài khoản đã xóa') . ' · ' . wp_kses_post(wc_price($result['amount'])) . '</p>';
            echo '<p>Xác nhận lúc ' . esc_html($result['confirmed_at']) . ' bởi tài khoản #' . esc_html($result['confirmed_by']) . '.</p>';
            if ($winner) echo '<p>Email liên hệ thanh toán <a href="mailto:' . esc_attr($winner->user_email) . '">' . esc_html($winner->user_email) . '</a></p>';
        } else {
            $bid = self::highest_bid($post->ID);
            $reserve = absint(get_post_meta($post->ID, '_lw_reserve_price', true));
            echo '<p>Giá dự trữ ' . wp_kses_post(wc_price($reserve)) . '</p>';
            if ($bid) {
                echo '<p>Giá cao nhất ' . wp_kses_post(wc_price($bid->amount)) . ' · ' . ((int) $bid->amount >= $reserve ? 'Đã đạt giá dự trữ' : 'Chưa đạt giá dự trữ') . '</p>';
            }
            if ($bid && (int) $bid->amount >= $reserve && Luxury_Watches_Auctions::state($post->ID) === 'ended' && $post->post_status === 'publish') {
                echo '<p><button class="button button-primary" type="submit" form="lw-confirm-auction">Xác nhận người thắng</button></p>';
            } else {
                echo '<p>Có thể xác nhận khi phiên kết thúc và lượt trả cao nhất đạt giá dự trữ.</p>';
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'luxury_auction_bids';
        $bids = $wpdb->get_results($wpdb->prepare("SELECT user_id, amount, created_at FROM {$table} WHERE auction_id = %d ORDER BY id DESC LIMIT 20", $post->ID));
        if (!$bids) { echo '<p>Chưa có lượt trả giá.</p>'; return; }
        echo '<p>20 lượt trả giá gần nhất</p><table class="widefat striped"><thead><tr><th>Thành viên</th><th>Mức giá</th><th>Thời gian</th></tr></thead><tbody>';
        foreach ($bids as $bid) {
            $user = get_userdata($bid->user_id);
            echo '<tr><td>' . esc_html($user ? $user->display_name : 'Tài khoản đã xóa') . '</td><td>' . wp_kses_post(wc_price($bid->amount)) . '</td><td>' . esc_html($bid->created_at) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    public static function confirmation_form() {
        $screen = get_current_screen();
        global $post;
        if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'watch_auction' || !$post || !self::can_manage($post->ID)) return;
        // A separate form avoids submitting the editor's unsaved settings when confirming.
        echo '<form id="lw-confirm-auction" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="luxury_confirm_auction"><input type="hidden" name="auction_id" value="' . esc_attr($post->ID) . '">';
        wp_nonce_field('lw_confirm_auction_' . $post->ID, 'lw_confirm_nonce');
        echo '</form>';
    }

    public static function handle_confirmation() {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') wp_die('Yêu cầu không hợp lệ.', '', ['response' => 405]);
        $id = isset($_POST['auction_id']) ? absint($_POST['auction_id']) : 0;
        if (!self::can_manage($id)) wp_die('Bạn không có quyền xác nhận kết quả phiên.', '', ['response' => 403]);
        check_admin_referer('lw_confirm_auction_' . $id, 'lw_confirm_nonce');
        $result = self::confirm_result($id);
        $message = is_wp_error($result) ? $result->get_error_message() : 'Đã xác nhận người thắng. Bạn có thể liên hệ khách hàng để thanh toán.';
        set_transient('lw_auction_notice_' . get_current_user_id(), ['error' => is_wp_error($result), 'message' => $message], 60);
        wp_safe_redirect(get_edit_post_link($id, 'raw'));
        exit;
    }

    public static function notice() {
        if (!current_user_can('manage_woocommerce')) return;
        $key = 'lw_auction_notice_' . get_current_user_id();
        $notice = get_transient($key);
        if (!$notice) return;
        delete_transient($key);
        echo '<div class="notice ' . ($notice['error'] ? 'notice-error' : 'notice-success') . ' is-dismissible"><p>' . esc_html($notice['message']) . '</p></div>';
    }
}

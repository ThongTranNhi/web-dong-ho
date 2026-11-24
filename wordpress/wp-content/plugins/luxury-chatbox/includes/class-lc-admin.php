<?php
if (!defined('ABSPATH')) exit;

/** WP Admin → Chatbox: toggle, configuration status, limits, data lifecycle. */
final class LC_Admin {
    const SLUG = 'luxury-chatbox';
    const CAP = 'manage_options';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'register']);
        add_action('admin_post_lc_cleanup_now', [__CLASS__, 'cleanup_now']);
        add_action('admin_post_lc_purge_all', [__CLASS__, 'purge_all']);
    }

    public static function menu() {
        add_menu_page('Chatbox', 'Chatbox', self::CAP, self::SLUG, [__CLASS__, 'page'], 'dashicons-format-chat', 58);
    }

    public static function register() {
        register_setting('luxury_chatbox', LC_Settings::OPTION, [
            'type' => 'array',
            'sanitize_callback' => ['LC_Settings', 'sanitize'],
            'default' => LC_Settings::defaults(),
        ]);
    }

    private static function redirect($notice) {
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'lc_notice' => $notice], admin_url('admin.php')));
        exit;
    }

    public static function cleanup_now() {
        if (!current_user_can(self::CAP)) wp_die('Bạn không có quyền thực hiện thao tác này.', '', ['response' => 403]);
        check_admin_referer('lc_cleanup_now');
        $deleted = LC_Cleanup::run();
        self::redirect('cleaned-' . $deleted);
    }

    public static function purge_all() {
        if (!current_user_can(self::CAP)) wp_die('Bạn không có quyền thực hiện thao tác này.', '', ['response' => 403]);
        check_admin_referer('lc_purge_all');
        $confirm = isset($_POST['lc_confirm']) ? sanitize_text_field(wp_unslash($_POST['lc_confirm'])) : '';
        if ($confirm !== 'XOA') self::redirect('purge-unconfirmed');
        LC_Repository::purge_all();
        self::redirect('purged');
    }

    public static function page() {
        if (!current_user_can(self::CAP)) return;
        $s = LC_Settings::all();
        [$key, $source] = LC_Settings::api_key();
        $stats = LC_Repository::stats();
        $next = wp_next_scheduled(LC_Cleanup::HOOK);
        $last = get_option('luxury_chatbox_last_cleanup');
        $notice = isset($_GET['lc_notice']) ? sanitize_key(wp_unslash($_GET['lc_notice'])) : '';
        $name = LC_Settings::OPTION;
        $num = static function ($field, $label, $help) use ($s, $name) {
            [$min, $max] = LC_Settings::bounds()[$field];
            echo '<tr><th scope="row"><label for="lc-' . esc_attr($field) . '">' . esc_html($label) . '</label></th><td>';
            echo '<input type="number" class="small-text" id="lc-' . esc_attr($field) . '" name="' . esc_attr($name . '[' . $field . ']') . '" value="' . esc_attr($s[$field]) . '" min="' . esc_attr($min) . '" max="' . esc_attr($max) . '"> ';
            echo '<p class="description">' . esc_html($help) . '</p></td></tr>';
        };
        ?>
        <div class="wrap">
          <h1>Chatbox hỗ trợ khách hàng</h1>
          <?php if ($notice === 'purged') : ?><div class="notice notice-success"><p>Đã xóa toàn bộ hội thoại và tin nhắn.</p></div><?php endif; ?>
          <?php if ($notice === 'purge-unconfirmed') : ?><div class="notice notice-error"><p>Chưa xóa dữ liệu: cần nhập chính xác XOA để xác nhận.</p></div><?php endif; ?>
          <?php if (strpos($notice, 'cleaned-') === 0) : ?><div class="notice notice-success"><p>Đã dọn <?php echo esc_html((int) substr($notice, 8)); ?> hội thoại hết hạn.</p></div><?php endif; ?>

          <h2>Trạng thái</h2>
          <table class="widefat striped" style="max-width:760px">
            <tbody>
              <tr><td>Chatbot</td><td><?php echo $s['enabled'] ? '<strong style="color:#008a20">Đang bật</strong>' : '<strong style="color:#b32d2e">Đang tắt</strong>'; ?></td></tr>
              <tr><td>API key Gemini</td><td><?php echo $key !== '' ? 'Đã cấu hình (nguồn: ' . esc_html($source) . ')' : '<strong style="color:#b32d2e">Chưa cấu hình</strong>: thêm <code>define(\'LUXURY_CHATBOX_GEMINI_KEY\', \'...\');</code> vào wp-config.php hoặc biến môi trường <code>GEMINI_API_KEY</code>.'; ?></td></tr>
              <tr><td>Model</td><td><code><?php echo esc_html($s['model']); ?></code> · dự phòng khi gặp 503: <?php echo $s['fallback_model'] !== '' ? '<code>' . esc_html($s['fallback_model']) . '</code>' : 'chưa cấu hình'; ?> · chờ AI tối đa <?php echo esc_html(LC_Settings::max_ai_seconds()); ?> giây</td></tr>
              <tr><td>Plugin đấu giá</td><td><?php echo method_exists('Luxury_Watches_Auctions', 'snapshot') ? 'Đã kết nối' : 'Chưa kết nối (chatbot sẽ báo thiếu dữ liệu đấu giá)'; ?></td></tr>
              <tr><td>WooCommerce</td><td><?php echo function_exists('wc_get_products') ? 'Đã kết nối' : 'Chưa kích hoạt'; ?></td></tr>
              <tr><td>Dữ liệu lưu trữ</td><td><?php echo esc_html(number_format_i18n($stats['conversations'])); ?> hội thoại · <?php echo esc_html(number_format_i18n($stats['messages'])); ?> tin nhắn · phiên bản CSDL <?php echo esc_html((string) get_option(LC_Installer::DB_OPTION)); ?></td></tr>
              <tr><td>Dọn dữ liệu tự động</td><td><?php echo $next ? 'Lần tới: ' . esc_html(wp_date('H:i d/m/Y', $next)) : 'Chưa lên lịch'; ?><?php if (is_array($last)) echo ' · Lần trước: ' . esc_html(wp_date('H:i d/m/Y', $last['at'])) . ' (' . esc_html((int) $last['deleted']) . ' hội thoại)'; ?></td></tr>
            </tbody>
          </table>

          <form method="post" action="options.php">
            <?php settings_fields('luxury_chatbox'); ?>
            <h2>Cấu hình</h2>
            <table class="form-table" role="presentation">
              <tr><th scope="row">Bật chatbot</th><td><label><input type="checkbox" name="<?php echo esc_attr($name); ?>[enabled]" value="1" <?php checked($s['enabled'], 1); ?>> Cho phép khách sử dụng trợ lý</label></td></tr>
              <tr><th scope="row"><label for="lc-model">Model</label></th><td><input type="text" class="regular-text" id="lc-model" name="<?php echo esc_attr($name); ?>[model]" value="<?php echo esc_attr($s['model']); ?>"><p class="description">Mặc định <code>gemini-3.8-flash</code> (nhanh, chi phí thấp).</p></td></tr>
              <tr><th scope="row"><label for="lc-fallback-model">Model dự phòng</label></th><td><input type="text" class="regular-text" id="lc-fallback-model" name="<?php echo esc_attr($name); ?>[fallback_model]" value="<?php echo esc_attr($s['fallback_model']); ?>" placeholder="Để trống để tắt"><p class="description">Chỉ dùng khi model chính trả HTTP 503, tối đa một lần cho mỗi tin nhắn. Mỗi lần gọi đều được tính vào hạn mức. Khi bật, thời gian chờ tối đa của một tin nhắn gấp đôi thời gian chờ AI. Chỉ nhập model đã kiểm tra trả lời đúng định dạng JSON của chatbot.</p></td></tr>
              <?php
              $num('max_message_chars', 'Độ dài tối đa tin nhắn', 'Số ký tự tối đa mỗi tin nhắn của khách.');
              $num('history_messages', 'Số tin nhắn lịch sử gửi cho AI', 'Số tin gần nhất (cả khách và trợ lý) đưa vào ngữ cảnh. 0 = không gửi lịch sử.');
              $num('per_minute', 'Giới hạn mỗi phút', 'Số tin nhắn tối đa mỗi phiên khách/thành viên trong 60 giây.');
              $num('per_day', 'Giới hạn mỗi ngày', 'Số tin nhắn tối đa mỗi phiên khách/thành viên trong 24 giờ.');
              $num('global_per_day', 'Hạn mức toàn website mỗi ngày', 'Tổng số lượt gọi AI trong 24 giờ cho toàn website.');
              $num('timeout', 'Thời gian chờ AI (giây)', 'Quá thời gian này, khách nhận lỗi ai_timeout và có thể gửi lại.');
              $num('retention_days', 'Thời gian lưu hội thoại (ngày)', 'Hội thoại không hoạt động quá số ngày này sẽ bị xóa cùng tin nhắn.');
              ?>
              <tr><th scope="row">Khi gỡ plugin</th><td><label><input type="checkbox" name="<?php echo esc_attr($name); ?>[delete_data_on_uninstall]" value="1" <?php checked($s['delete_data_on_uninstall'], 1); ?>> Xóa bảng hội thoại, tin nhắn và cấu hình khi gỡ (Delete) plugin</label><p class="description">Mặc định giữ dữ liệu. Tắt (Deactivate) plugin không bao giờ xóa dữ liệu.</p></td></tr>
            </table>
            <?php submit_button('Lưu cấu hình'); ?>
          </form>

          <h2>Dữ liệu</h2>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:1em">
            <input type="hidden" name="action" value="lc_cleanup_now"><?php wp_nonce_field('lc_cleanup_now'); ?>
            <?php submit_button('Dọn hội thoại hết hạn ngay', 'secondary', 'submit', false); ?>
          </form>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="lc_purge_all"><?php wp_nonce_field('lc_purge_all'); ?>
            <p><label for="lc-confirm">Xóa <strong>toàn bộ</strong> hội thoại và tin nhắn. Nhập <code>XOA</code> để xác nhận:</label>
            <input type="text" id="lc-confirm" name="lc_confirm" class="small-text" autocomplete="off"></p>
            <?php submit_button('Xóa toàn bộ dữ liệu chat', 'delete', 'submit', false); ?>
          </form>
        </div>
        <?php
    }
}

<?php
if (!defined('ABSPATH')) exit;

final class Luxury_Auction_Dashboard {
    public static function init() {
        add_action('template_redirect', [__CLASS__, 'render'], 0);
    }

    public static function url() {
        return home_url('/quan-tri-dau-gia/');
    }

    public static function can_manage() {
        return current_user_can('manage_woocommerce') && current_user_can('edit_posts');
    }

    public static function create_auction(array $input, $submission) {
        if (!self::can_manage()) return new WP_Error('forbidden', 'Bạn không có quyền tạo phiên đấu giá.');
        if (!is_string($submission) || !preg_match('/\A[a-f0-9]{32}\z/', $submission)) return new WP_Error('invalid_submission', 'Phiên nhập liệu đã hết hiệu lực. Vui lòng tải lại trang.');
        $title = is_string($input['title'] ?? null) ? sanitize_text_field($input['title']) : '';
        if ($title === '') return new WP_Error('title_required', 'Vui lòng nhập tên phiên đấu giá.');
        $settings = Luxury_Watches_Auctions::validate_settings($input);
        if (is_wp_error($settings)) return $settings;
        if ($settings['_lw_lot_number'] === '') return new WP_Error('lot_required', 'Vui lòng nhập mã phiên.');
        $status = ($input['status'] ?? '') === 'publish' ? 'publish' : 'draft';
        if ($status === 'publish' && !current_user_can('publish_posts')) return new WP_Error('forbidden', 'Bạn không có quyền công bố phiên.');
        $product = wc_get_product($settings['_lw_product_id']);
        if ($product->get_status() !== 'publish') return new WP_Error('invalid_product', 'Sản phẩm liên kết cần được xuất bản trước.');
        $image_id = $product->get_image_id();
        $has_upload = isset($_FILES['auction_image']) && ($_FILES['auction_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($has_upload && !current_user_can('upload_files')) return new WP_Error('forbidden', 'Bạn không có quyền tải ảnh lên.');
        if ($has_upload && ($_FILES['auction_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return new WP_Error('upload_failed', 'Ảnh chưa tải lên đầy đủ. Vui lòng chọn lại ảnh.');
        if ($status === 'publish' && !$has_upload && (!$image_id || !wp_attachment_is_image($image_id))) return new WP_Error('image_required', 'Cần ảnh sản phẩm hoặc tải ảnh đại diện trước khi đăng phiên.');
        $end = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $settings['_lw_end_at'], wp_timezone());
        if ($status === 'publish' && $end->getTimestamp() <= time()) return new WP_Error('ended', 'Giờ kết thúc phải ở tương lai khi đăng phiên.');

        global $wpdb;
        // Serialize creation so submission tokens and lot codes cannot be duplicated.
        $lock = 'lwa_create_' . substr(hash('sha256', DB_NAME . ':' . $wpdb->prefix), 0, 48);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) return new WP_Error('busy', 'Có phiên đang được lưu. Vui lòng thử lại sau ít giây.');
        $post_id = 0;
        $uploaded_id = 0;
        $complete = false;
        try {
            $token = hash('sha256', get_current_user_id() . ':' . $submission);
            $existing = get_posts(['post_type' => 'watch_auction', 'post_status' => ['draft', 'publish', 'pending', 'private'], 'meta_key' => '_lwa_submission', 'meta_value' => $token, 'numberposts' => 1]);
            if ($existing) {
                return get_post_meta($existing[0]->ID, '_lwa_creation_complete', true) ? $existing[0]->ID : new WP_Error('incomplete', 'Phiên đang lưu hoặc chưa hoàn tất. Kiểm tra danh sách phiên trước khi gửi lại.');
            }
            $duplicate = get_posts(['post_type' => 'watch_auction', 'post_status' => ['draft', 'publish', 'pending', 'private'], 'meta_key' => '_lw_lot_number', 'meta_value' => $settings['_lw_lot_number'], 'numberposts' => 1, 'fields' => 'ids']);
            if ($duplicate) return new WP_Error('duplicate_lot', 'Mã phiên đã tồn tại. Vui lòng dùng mã khác.');
            $post_id = wp_insert_post([
                'post_type' => 'watch_auction', 'post_status' => 'draft',
                'post_title' => $title,
                'post_content' => wp_kses_post(is_string($input['description'] ?? null) ? $input['description'] : ''),
                'post_author' => get_current_user_id(),
                'meta_input' => $settings + ['_lwa_submission' => $token],
            ], true);
            if (is_wp_error($post_id)) return $post_id;
            // Verify metadata before a draft can become public.
            foreach ($settings as $key => $value) {
                if ((string) get_post_meta($post_id, $key, true) !== (string) $value) return new WP_Error('storage_error', 'Chưa lưu đầy đủ cấu hình phiên. Vui lòng thử lại.');
            }
            if ($has_upload) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $uploaded_id = media_handle_upload('auction_image', $post_id, [], ['test_form' => false, 'mimes' => ['jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp']]);
                if (is_wp_error($uploaded_id)) return new WP_Error('upload_failed', 'Không thể tải ảnh. Vui lòng dùng ảnh JPG, PNG hoặc WebP hợp lệ.');
                $image_id = $uploaded_id;
            }
            if ($image_id && !set_post_thumbnail($post_id, $image_id)) return new WP_Error('image_failed', 'Không thể lưu ảnh đại diện.');
            if ($status === 'publish') {
                $updated = wp_update_post(['ID' => $post_id, 'post_status' => 'publish'], true);
                if (is_wp_error($updated)) return $updated;
            }
            if (!update_post_meta($post_id, '_lwa_creation_complete', 1)) return new WP_Error('storage_error', 'Chưa thể hoàn tất phiên.');
            $complete = true;
            return (int) $post_id;
        } finally {
            if (!$complete && $post_id && !is_wp_error($post_id)) wp_delete_post($post_id, true);
            if (!$complete && $uploaded_id && !is_wp_error($uploaded_id)) wp_delete_attachment($uploaded_id, true);
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }

    public static function render() {
        $requested = untrailingslashit(wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        if ($requested !== untrailingslashit(wp_parse_url(self::url(), PHP_URL_PATH))) return;
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow', true);
        if (!is_user_logged_in()) { auth_redirect(); exit; }
        if (!self::can_manage()) wp_die('Trang này dành cho quản trị viên cửa hàng.', 'Không có quyền truy cập', ['response' => 403]);
        status_header(200);
        $view = ($_GET['view'] ?? '') === 'new' ? 'new' : 'list';
        $error = '';
        $values = [];
        $submission = bin2hex(random_bytes(16));
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            check_admin_referer('lwa_dashboard_create', 'lwa_nonce');
            $values = is_array($_POST['auction'] ?? null) ? wp_unslash($_POST['auction']) : [];
            $submission = is_string($_POST['submission'] ?? null) ? sanitize_text_field(wp_unslash($_POST['submission'])) : '';
            $result = self::create_auction($values, $submission);
            if (is_wp_error($result)) { $error = $result->get_error_message(); $view = 'new'; }
            else { wp_safe_redirect(add_query_arg('created', $result, self::url())); exit; }
        }
        $query = new WP_Query(['post_type' => 'watch_auction', 'post_status' => ['publish', 'draft', 'pending', 'private'], 'posts_per_page' => 20, 'paged' => max(1, absint($_GET['paged'] ?? 1))]);
        $products = function_exists('wc_get_products') ? wc_get_products(['status' => 'publish', 'limit' => -1, 'orderby' => 'name', 'order' => 'ASC']) : [];
        $base = plugin_dir_url(dirname(__FILE__)) . 'assets/';
        require dirname(__DIR__) . '/templates/auction-dashboard.php';
        exit;
    }
}

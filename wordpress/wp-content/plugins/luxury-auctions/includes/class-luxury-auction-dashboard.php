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

    /** Product categories offered in the form: gender is a separate choice. */
    public static function collection_terms() {
        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name']);
        if (is_wp_error($terms)) return [];
        return array_values(array_filter($terms, static function ($term) {
            return !in_array($term->slug, ['uncategorized', 'dong-ho-nam', 'dong-ho-nu'], true);
        }));
    }

    private static function amount($value, $required) {
        $value = is_scalar($value) ? trim((string) $value) : '';
        if ($value === '') return $required ? false : null;
        return preg_match('/\A[0-9]{1,13}\z/', $value) ? (int) $value : false;
    }

    /**
     * Creates a WooCommerce simple product from the dashboard form.
     * Same safeguards as auctions: capability checks, one product per
     * submission token, a creation lock, and full rollback on failure.
     *
     * @return int|WP_Error product ID
     */
    public static function create_product(array $input, $submission) {
        if (!self::can_manage() || !current_user_can('edit_products')) return new WP_Error('forbidden', 'Bạn không có quyền thêm sản phẩm.');
        if (!function_exists('wc_get_product')) return new WP_Error('woocommerce_missing', 'WooCommerce chưa được kích hoạt.');
        if (!is_string($submission) || !preg_match('/\A[a-f0-9]{32}\z/', $submission)) return new WP_Error('invalid_submission', 'Phiên nhập liệu đã hết hiệu lực. Vui lòng tải lại trang.');

        $name = is_string($input['name'] ?? null) ? sanitize_text_field($input['name']) : '';
        if ($name === '') return new WP_Error('name_required', 'Vui lòng nhập tên sản phẩm.');
        if (mb_strlen($name) > 200) return new WP_Error('name_too_long', 'Tên sản phẩm tối đa 200 ký tự.');
        $sku = is_string($input['sku'] ?? null) ? trim(sanitize_text_field($input['sku'])) : '';
        if ($sku !== '' && !preg_match('/\A[A-Za-z0-9._\-]{1,64}\z/', $sku)) return new WP_Error('invalid_sku', 'SKU chỉ gồm chữ, số, dấu chấm, gạch ngang hoặc gạch dưới (tối đa 64 ký tự).');
        $regular = self::amount($input['regular_price'] ?? '', true);
        if (!$regular) return new WP_Error('invalid_price', 'Giá niêm yết phải là số nguyên lớn hơn 0 (VND).');
        $sale = self::amount($input['sale_price'] ?? '', false);
        if ($sale === false || ($sale !== null && ($sale <= 0 || $sale >= $regular))) return new WP_Error('invalid_sale_price', 'Giá khuyến mãi phải lớn hơn 0 và thấp hơn giá niêm yết, hoặc để trống.');
        $stock = self::amount($input['stock'] ?? '', true);
        if ($stock === false || $stock > 100000) return new WP_Error('invalid_stock', 'Số lượng tồn kho phải là số nguyên từ 0 đến 100.000.');

        $category_ids = [];
        $gender = $input['gender'] ?? '';
        if (in_array($gender, ['dong-ho-nam', 'dong-ho-nu'], true)) {
            $term = get_term_by('slug', $gender, 'product_cat');
            if ($term) $category_ids[] = (int) $term->term_id;
        }
        $allowed = wp_list_pluck(self::collection_terms(), 'term_id');
        foreach ((array) ($input['collections'] ?? []) as $term_id) {
            if (is_scalar($term_id) && in_array((int) $term_id, $allowed, true)) $category_ids[] = (int) $term_id;
        }
        $category_ids = array_values(array_unique($category_ids));

        $status = ($input['status'] ?? '') === 'publish' ? 'publish' : 'draft';
        if ($status === 'publish' && !current_user_can('publish_products')) return new WP_Error('forbidden', 'Bạn không có quyền đăng sản phẩm.');
        $has_upload = isset($_FILES['product_image']) && ($_FILES['product_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($has_upload && !current_user_can('upload_files')) return new WP_Error('forbidden', 'Bạn không có quyền tải ảnh lên.');
        if ($has_upload && ($_FILES['product_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return new WP_Error('upload_failed', 'Ảnh chưa tải lên đầy đủ. Vui lòng chọn lại ảnh.');
        if ($status === 'publish' && !$has_upload) return new WP_Error('image_required', 'Cần ảnh sản phẩm trước khi đăng. Bạn có thể lưu nháp nếu chưa có ảnh.');

        global $wpdb;
        $lock = 'lwa_product_' . substr(hash('sha256', DB_NAME . ':' . $wpdb->prefix), 0, 48);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) return new WP_Error('busy', 'Có sản phẩm đang được lưu. Vui lòng thử lại sau ít giây.');
        $product_id = 0;
        $uploaded_id = 0;
        $complete = false;
        try {
            $token = hash('sha256', get_current_user_id() . ':product:' . $submission);
            $existing = get_posts(['post_type' => 'product', 'post_status' => ['draft', 'publish', 'pending', 'private'], 'meta_key' => '_lwa_product_submission', 'meta_value' => $token, 'numberposts' => 1, 'fields' => 'ids']);
            if ($existing) {
                return get_post_meta($existing[0], '_lwa_creation_complete', true) ? (int) $existing[0] : new WP_Error('incomplete', 'Sản phẩm đang lưu hoặc chưa hoàn tất. Kiểm tra danh sách sản phẩm trước khi gửi lại.');
            }
            if ($sku !== '' && wc_get_product_id_by_sku($sku)) return new WP_Error('duplicate_sku', 'SKU đã được dùng cho sản phẩm khác.');

            $product = new WC_Product_Simple();
            try {
                $product->set_name($name);
                if ($sku !== '') $product->set_sku($sku);
                $product->set_regular_price((string) $regular);
                $product->set_sale_price($sale !== null ? (string) $sale : '');
                $product->set_manage_stock(true);
                $product->set_stock_quantity($stock);
                $product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
                $product->set_catalog_visibility('visible');
                $product->set_short_description(wp_kses_post(is_string($input['short_description'] ?? null) ? $input['short_description'] : ''));
                $product->set_description(wp_kses_post(is_string($input['description'] ?? null) ? $input['description'] : ''));
                $product->set_category_ids($category_ids);
                $product->set_status('draft');
                $product_id = $product->save();
            } catch (WC_Data_Exception $e) {
                return new WP_Error('invalid_product', 'Dữ liệu sản phẩm chưa hợp lệ: ' . $e->getMessage());
            }
            if (!$product_id) return new WP_Error('storage_error', 'Chưa thể lưu sản phẩm. Vui lòng thử lại.');
            update_post_meta($product_id, '_lwa_product_submission', $token);

            if ($has_upload) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $uploaded_id = media_handle_upload('product_image', $product_id, [], ['test_form' => false, 'mimes' => ['jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp']]);
                if (is_wp_error($uploaded_id)) return new WP_Error('upload_failed', 'Không thể tải ảnh. Vui lòng dùng ảnh JPG, PNG hoặc WebP hợp lệ.');
                update_post_meta($uploaded_id, '_wp_attachment_image_alt', $name);
                $product->set_image_id($uploaded_id);
            }
            if ($status === 'publish') $product->set_status('publish');
            $product->save();
            // Verify what customers will see before reporting success.
            $saved = wc_get_product($product_id);
            if (!$saved || (int) $saved->get_regular_price() !== $regular || (int) $saved->get_stock_quantity() !== $stock || $saved->get_status() !== $status) {
                return new WP_Error('storage_error', 'Chưa lưu đầy đủ thông tin sản phẩm. Vui lòng thử lại.');
            }
            if (!update_post_meta($product_id, '_lwa_creation_complete', 1)) return new WP_Error('storage_error', 'Chưa thể hoàn tất sản phẩm.');
            $complete = true;
            return (int) $product_id;
        } finally {
            if (!$complete && $product_id) wp_delete_post($product_id, true);
            if (!$complete && $uploaded_id && !is_wp_error($uploaded_id)) wp_delete_attachment($uploaded_id, true);
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }

    /** Auctions (any status except trash) that use this product. */
    public static function linked_auctions($product_id) {
        return get_posts(['post_type' => 'watch_auction', 'post_status' => ['publish', 'draft', 'pending', 'private', 'future'], 'meta_key' => '_lw_product_id', 'meta_value' => (string) absint($product_id), 'numberposts' => 20]);
    }

    private static function managed_product($product_id, $capability) {
        if (!self::can_manage()) return new WP_Error('forbidden', 'Bạn không có quyền quản lý sản phẩm.');
        $product_id = absint($product_id);
        if (!$product_id || get_post_type($product_id) !== 'product') return new WP_Error('not_found', 'Không tìm thấy sản phẩm.');
        if (!current_user_can($capability, $product_id)) return new WP_Error('forbidden', 'Bạn không có quyền với sản phẩm này.');
        return $product_id;
    }

    /**
     * "Xóa" moves the product to the trash so it can be restored.
     * Products used by an auction are protected: removing them would break the auction.
     */
    public static function trash_product($product_id) {
        $product_id = self::managed_product($product_id, 'delete_post');
        if (is_wp_error($product_id)) return $product_id;
        if (get_post_status($product_id) === 'trash') return new WP_Error('already_trashed', 'Sản phẩm đã nằm trong thùng rác.');
        $auctions = self::linked_auctions($product_id);
        if ($auctions) {
            $names = implode(', ', array_map(static function ($a) { return '“' . $a->post_title . '”'; }, array_slice($auctions, 0, 3)));
            return new WP_Error('in_auction', sprintf('Không thể xóa vì sản phẩm đang dùng trong phiên đấu giá %s. Hãy xóa hoặc đổi sản phẩm của phiên trước.', $names));
        }
        if (!wp_trash_post($product_id)) return new WP_Error('trash_failed', 'Chưa thể xóa sản phẩm. Vui lòng thử lại.');
        return $product_id;
    }

    /** Undo: back to the status it had before it was trashed (published or draft). */
    public static function restore_product($product_id) {
        $product_id = self::managed_product($product_id, 'delete_post');
        if (is_wp_error($product_id)) return $product_id;
        if (get_post_status($product_id) !== 'trash') return new WP_Error('not_trashed', 'Sản phẩm không nằm trong thùng rác.');
        add_filter('wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3);
        $restored = wp_untrash_post($product_id);
        remove_filter('wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10);
        return $restored ? $product_id : new WP_Error('restore_failed', 'Chưa thể khôi phục sản phẩm.');
    }

    /** Products for the list view, searchable by name or SKU. */
    public static function product_list($search, $page, $per_page = 20) {
        $args = ['post_type' => 'product', 'post_status' => ['publish', 'draft', 'pending', 'private'], 'posts_per_page' => $per_page, 'paged' => $page, 'orderby' => 'date', 'order' => 'DESC'];
        if ($search !== '') {
            $by_name = get_posts(['post_type' => 'product', 'post_status' => $args['post_status'], 's' => $search, 'fields' => 'ids', 'numberposts' => 500]);
            $by_sku = get_posts(['post_type' => 'product', 'post_status' => $args['post_status'], 'fields' => 'ids', 'numberposts' => 500, 'meta_query' => [['key' => '_sku', 'value' => $search, 'compare' => 'LIKE']]]);
            $args['post__in'] = array_values(array_unique(array_merge($by_name, $by_sku))) ?: [0];
        }
        return new WP_Query($args);
    }

    public static function render() {
        $requested = untrailingslashit(wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        if ($requested !== untrailingslashit(wp_parse_url(self::url(), PHP_URL_PATH))) return;
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow', true);
        if (!is_user_logged_in()) { auth_redirect(); exit; }
        if (!self::can_manage()) wp_die('Trang này dành cho quản trị viên cửa hàng.', 'Không có quyền truy cập', ['response' => 403]);
        status_header(200);
        $view = in_array($_GET['view'] ?? '', ['new', 'new-product', 'products'], true) ? $_GET['view'] : 'list';
        $error = '';
        $values = [];
        $product_values = [];
        $submission = bin2hex(random_bytes(16));
        // "Tạo phiên đấu giá cho sản phẩm này" preselects the new product.
        if ($view === 'new' && ($preselect = wc_get_product(absint($_GET['lwa_product'] ?? 0))) && $preselect->get_status() === 'publish') {
            $values = ['_lw_product_id' => (string) $preselect->get_id(), 'title' => $preselect->get_name(), '_lw_retail_price' => (string) (int) $preselect->get_regular_price()];
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $submission = is_string($_POST['submission'] ?? null) ? sanitize_text_field(wp_unslash($_POST['submission'])) : '';
            $form = $_POST['lwa_form'] ?? '';
            if (in_array($form, ['trash_product', 'restore_product'], true)) {
                $target = absint($_POST['lwa_product_id'] ?? 0);
                check_admin_referer('lwa_' . $form . '_' . $target, 'lwa_nonce');
                $result = $form === 'trash_product' ? self::trash_product($target) : self::restore_product($target);
                $back = ['view' => 'products'];
                foreach (['lwa_q', 'lwa_page'] as $keep) if (!empty($_POST[$keep])) $back[$keep] = sanitize_text_field(wp_unslash($_POST[$keep]));
                if (is_wp_error($result)) { $error = $result->get_error_message(); $view = 'products'; }
                else { wp_safe_redirect(add_query_arg($back + [$form === 'trash_product' ? 'trashed' : 'restored' => $result], self::url())); exit; }
            } elseif ($form === 'product') {
                check_admin_referer('lwa_dashboard_product', 'lwa_nonce');
                $product_values = is_array($_POST['lwa_product'] ?? null) ? wp_unslash($_POST['lwa_product']) : [];
                $result = self::create_product($product_values, $submission);
                if (is_wp_error($result)) { $error = $result->get_error_message(); $view = 'new-product'; }
                else { wp_safe_redirect(add_query_arg(['view' => 'new-product', 'product_created' => $result], self::url())); exit; }
            } else {
                check_admin_referer('lwa_dashboard_create', 'lwa_nonce');
                $values = is_array($_POST['auction'] ?? null) ? wp_unslash($_POST['auction']) : [];
                $result = self::create_auction($values, $submission);
                if (is_wp_error($result)) { $error = $result->get_error_message(); $view = 'new'; }
                else { wp_safe_redirect(add_query_arg('created', $result, self::url())); exit; }
            }
        }
        $collections = self::collection_terms();
        $product_search = isset($_GET['lwa_q']) ? sanitize_text_field(wp_unslash($_GET['lwa_q'])) : '';
        $product_page = max(1, absint($_GET['lwa_page'] ?? 1));
        $product_query = $view === 'products' ? self::product_list($product_search, $product_page) : null;
        $query = new WP_Query(['post_type' => 'watch_auction', 'post_status' => ['publish', 'draft', 'pending', 'private'], 'posts_per_page' => 20, 'paged' => max(1, absint($_GET['paged'] ?? 1))]);
        $products = function_exists('wc_get_products') ? wc_get_products(['status' => 'publish', 'limit' => -1, 'orderby' => 'name', 'order' => 'ASC']) : [];
        $base = plugin_dir_url(dirname(__FILE__)) . 'assets/';
        require dirname(__DIR__) . '/templates/auction-dashboard.php';
        exit;
    }
}

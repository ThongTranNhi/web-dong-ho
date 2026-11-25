<?php
/**
 * Plugin Name: Luxury Watches Auctions
 * Description: Phiên đấu giá đồng hồ với lịch mở/đóng, bước giá, lịch sử và kiểm soát trả giá đồng thời.
 * Version: 2.7.0
 * Author: Luxury Watches
 * Text Domain: luxury-auctions
 */

if (!defined('ABSPATH')) exit;

final class Luxury_Watches_Auctions {
    const VERSION = '2.7.0';

    public static function init() {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('save_post_watch_auction', [__CLASS__, 'save_meta']);
        add_shortcode('luxury_auctions', [__CLASS__, 'auctions_shortcode']);
        add_filter('the_content', [__CLASS__, 'auction_content']);
        add_action('admin_post_luxury_place_bid', [__CLASS__, 'place_bid']);
        add_action('admin_post_nopriv_luxury_place_bid', [__CLASS__, 'require_login']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        add_filter('rest_pre_serve_request', [__CLASS__, 'serve_cors_headers'], 10, 4);
    }

    public static function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'luxury_auction_bids';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            auction_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            amount bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            ip_hash char(64) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY auction_amount (auction_id, amount),
            KEY auction_created (auction_id, created_at),
            KEY user_created (user_id, created_at)
        ) {$charset};");
        self::register_post_type();
        flush_rewrite_rules();
    }

    public static function register_post_type() {
        register_post_type('watch_auction', [
            'labels' => [
                'name' => 'Đấu giá đồng hồ',
                'singular_name' => 'Phiên đấu giá',
                'add_new_item' => 'Thêm phiên đấu giá',
                'edit_item' => 'Chỉnh sửa phiên đấu giá',
                'menu_name' => 'Đấu giá',
            ],
            'public' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-hammer',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'has_archive' => true,
            'rewrite' => ['slug' => 'phien-dau-gia'],
        ]);
    }

    public static function add_meta_box() {
        add_meta_box('luxury-auction-settings', 'Thiết lập phiên đấu giá', [__CLASS__, 'meta_box'], 'watch_auction', 'normal', 'high');
    }

    public static function meta_box($post) {
        wp_nonce_field('luxury_auction_save', 'luxury_auction_nonce');
        $fields = [
            '_lw_product_id' => ['Sản phẩm WooCommerce liên kết (ID)', 'number'],
            '_lw_lot_number' => ['Mã phiên', 'text'],
            '_lw_gender' => ['Phân loại', 'text'],
            '_lw_condition' => ['Tình trạng', 'text'],
            '_lw_retail_price' => ['Giá niêm yết (VND)', 'number'],
            '_lw_start_price' => ['Giá khởi điểm (VND)', 'number'],
            '_lw_bid_increment' => ['Bước giá tối thiểu (VND)', 'number'],
            '_lw_reserve_price' => ['Giá dự trữ (VND)', 'number'],
            '_lw_start_at' => ['Thời gian bắt đầu', 'datetime-local'],
            '_lw_end_at' => ['Thời gian kết thúc', 'datetime-local'],
        ];
        echo '<div class="lw-admin-grid">';
        foreach ($fields as $key => [$label, $type]) {
            $value = get_post_meta($post->ID, $key, true);
            echo '<p><label for="' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label><br>';
            echo '<input class="widefat" type="' . esc_attr($type) . '" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '"' . ($type === 'number' ? ' min="0" step="1"' : '') . '></p>';
        }
        echo '</div><p>Thời gian sử dụng múi giờ website. Phiên chỉ nhận giá từ thành viên đã đăng nhập.</p>';
    }

    public static function save_meta($post_id) {
        if (!isset($_POST['luxury_auction_nonce']) || !is_string($_POST['luxury_auction_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['luxury_auction_nonce'])), 'luxury_auction_save')) return;
        if (!current_user_can('edit_post', $post_id) || wp_is_post_revision($post_id)) return;
        $settings = self::validate_settings(wp_unslash($_POST), $post_id);
        if (is_wp_error($settings)) { self::settings_error($settings->get_error_message()); return; }
        self::store_settings($post_id, $settings);
    }

    public static function validate_settings(array $input, $post_id = 0) {
        $numeric = ['_lw_product_id', '_lw_retail_price', '_lw_start_price', '_lw_bid_increment', '_lw_reserve_price'];
        $text = ['_lw_lot_number', '_lw_gender', '_lw_condition', '_lw_start_at', '_lw_end_at'];
        $settings = [];
        foreach (array_merge($numeric, $text) as $key) {
            $value = $input[$key] ?? ($post_id ? get_post_meta($post_id, $key, true) : '');
            if (!is_scalar($value)) return new WP_Error('invalid_settings', 'Thiết lập phiên chứa dữ liệu không hợp lệ.');
            $settings[$key] = sanitize_text_field((string) $value);
        }
        foreach ($numeric as $key) {
            $value = $settings[$key];
            if ($value === '' && in_array($key, ['_lw_retail_price', '_lw_reserve_price'], true)) $value = '0';
            if ($value !== '0' && self::parse_amount($value) === false) {
                return new WP_Error('invalid_number', 'Giá và ID sản phẩm phải là số nguyên không âm hợp lệ.');
            }
            $settings[$key] = (int) $value;
        }
        $start = self::timestamp($settings['_lw_start_at']);
        $end = self::timestamp($settings['_lw_end_at']);
        if (!$start || !$end || $end <= $start || $settings['_lw_start_price'] <= 0 || $settings['_lw_bid_increment'] <= 0) {
            return new WP_Error('invalid_schedule', 'Cần giá khởi điểm và bước giá lớn hơn 0, giờ kết thúc phải sau giờ bắt đầu.');
        }
        if (!function_exists('wc_get_product') || !wc_get_product($settings['_lw_product_id'])) {
            return new WP_Error('invalid_product', 'Cần chọn sản phẩm WooCommerce đang tồn tại.');
        }
        return $settings;
    }

    private static function store_settings($post_id, array $settings) {
        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) { self::settings_error('Không thể lưu thiết lập phiên.'); return; }
        try {
            $locked = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID = %d FOR UPDATE", $post_id));
            if (!$locked || $wpdb->last_error) { self::settings_error('Không thể khóa phiên để lưu thiết lập.'); return; }
            clean_post_cache($post_id);
            $summary = self::bid_summary($post_id);
            if ($wpdb->last_error) { self::settings_error('Không thể kiểm tra lịch sử trả giá.'); return; }
            $protected = ['_lw_product_id', '_lw_start_price', '_lw_bid_increment', '_lw_reserve_price', '_lw_start_at', '_lw_end_at'];
            if ($summary['count'] > 0 || get_post_meta($post_id, '_lw_auction_result', true)) {
                foreach ($protected as $key) {
                    if ((string) $settings[$key] !== (string) get_post_meta($post_id, $key, true)) {
                        self::settings_error('Phiên đã có lượt trả giá hoặc kết quả. Sản phẩm, quy tắc giá và lịch phiên đã được khóa.');
                        return;
                    }
                }
            }
            foreach ($settings as $key => $value) {
                if ((string) get_post_meta($post_id, $key, true) === (string) $value) continue;
                if (!update_post_meta($post_id, $key, $value)) { self::settings_error('Không thể lưu thiết lập phiên.'); return; }
            }
            if ($wpdb->query('COMMIT') === false) self::settings_error('Không thể hoàn tất lưu thiết lập phiên.');
        } finally {
            $wpdb->query('ROLLBACK');
            clean_post_cache($post_id);
        }
    }

    private static function settings_error($message) {
        set_transient('lw_auction_notice_' . get_current_user_id(), [
            'error' => true,
            'message' => 'Thiết lập phiên chưa được lưu. ' . $message,
        ], 60);
    }

    private static function timestamp($value) {
        if (!is_string($value) || !preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}\z/', $value)) return 0;
        $date = date_create_from_format('!Y-m-d\TH:i', $value, wp_timezone());
        return $date && $date->format('Y-m-d\TH:i') === $value ? $date->getTimestamp() : 0;
    }

    private static function parse_amount($value) {
        if (!is_string($value) || !preg_match('/\A[0-9]+\z/', $value)) return false;
        $digits = ltrim($value, '0');
        $maximum = (string) PHP_INT_MAX;
        if ($digits === '' || strlen($digits) > strlen($maximum)) return false;
        if (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) > 0) return false;
        return (int) $digits;
    }

    public static function state($auction_id) {
        if (get_post_meta($auction_id, '_lw_auction_result', true)) return 'ended';
        $now = current_datetime()->getTimestamp();
        $start = self::timestamp(get_post_meta($auction_id, '_lw_start_at', true));
        $end = self::timestamp(get_post_meta($auction_id, '_lw_end_at', true));
        if (!$start || !$end || $end <= $start) return 'invalid';
        if ($start && $now < $start) return 'upcoming';
        if ($end && $now >= $end) return 'ended';
        return 'live';
    }

    private static function bid_summary($auction_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'luxury_auction_bids';
        $row = $wpdb->get_row($wpdb->prepare("SELECT COUNT(*) AS total, MAX(amount) AS highest FROM {$table} WHERE auction_id = %d", $auction_id));
        return ['count' => $row ? (int) $row->total : 0, 'highest' => ($row && $row->highest !== null) ? (int) $row->highest : 0];
    }

    private static function money($amount) {
        return function_exists('wc_price') ? wc_price((int) $amount) : number_format_i18n((int) $amount, 0) . ' ₫';
    }

    private static function image_url($auction_id, $size = 'large') {
        $image = get_the_post_thumbnail_url($auction_id, $size);
        if ($image) return $image;
        $product_id = absint(get_post_meta($auction_id, '_lw_product_id', true));
        if ($product_id && function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            if ($product && $product->get_image_id()) return wp_get_attachment_image_url($product->get_image_id(), $size);
        }
        return function_exists('wc_placeholder_img_src') ? wc_placeholder_img_src($size) : '';
    }

    private static function status_label($state) {
        return ['live' => 'Đang diễn ra', 'upcoming' => 'Sắp mở', 'ended' => 'Đã kết thúc', 'invalid' => 'Đang cập nhật'][$state] ?? 'Đang cập nhật';
    }

    public static function register_rest_routes() {
        register_rest_route('luxury/v1', '/health', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'api_health'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('luxury/v1', '/products', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'api_products'],
            'permission_callback' => '__return_true',
            'args' => [
                'page' => ['default' => 1, 'sanitize_callback' => 'absint'],
                'per_page' => ['default' => 12, 'sanitize_callback' => 'absint'],
                'search' => ['default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                'category' => ['default' => '', 'sanitize_callback' => 'sanitize_title'],
            ],
        ]);
        register_rest_route('luxury/v1', '/auctions', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'api_auctions'],
            'permission_callback' => '__return_true',
            'args' => [
                'page' => ['default' => 1, 'sanitize_callback' => 'absint'],
                'per_page' => ['default' => 24, 'sanitize_callback' => 'absint'],
                'state' => ['default' => '', 'sanitize_callback' => 'sanitize_key'],
            ],
        ]);
        register_rest_route('luxury/v1', '/auctions/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'api_auction'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('luxury/v1', '/auctions/(?P<id>\d+)/bids', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'api_place_bid'],
            'permission_callback' => [__CLASS__, 'api_can_bid'],
            'args' => [
                'amount' => ['required' => true, 'sanitize_callback' => 'absint'],
            ],
        ]);
    }

    public static function serve_cors_headers($served, $result, $request, $server) {
        $origin = get_http_origin();
        $allowed = defined('LW_API_ALLOWED_ORIGINS') && is_array(LW_API_ALLOWED_ORIGINS) ? LW_API_ALLOWED_ORIGINS : [];
        if ($origin && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . esc_url_raw($origin));
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Vary: Origin');
        }
        return $served;
    }

    public static function api_health() {
        return rest_ensure_response([
            'status' => 'ok',
            'service' => 'luxury-watches-api',
            'version' => self::VERSION,
            'wordpress' => get_bloginfo('version'),
            'timestamp' => current_time('c'),
        ]);
    }

    private static function product_response($product) {
        $categories = [];
        foreach ($product->get_category_ids() as $category_id) {
            $term = get_term($category_id, 'product_cat');
            if ($term && !is_wp_error($term)) $categories[] = ['id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug];
        }
        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'slug' => $product->get_slug(),
            'sku' => $product->get_sku(),
            'price' => (float) $product->get_price(),
            'regular_price' => (float) $product->get_regular_price(),
            'currency' => get_woocommerce_currency(),
            'permalink' => get_permalink($product->get_id()),
            'image' => $product->get_image_id() ? wp_get_attachment_image_url($product->get_image_id(), 'large') : wc_placeholder_img_src('large'),
            'stock_status' => $product->get_stock_status(),
            'categories' => $categories,
        ];
    }

    public static function api_products(WP_REST_Request $request) {
        if (!function_exists('wc_get_products')) return new WP_Error('woocommerce_missing', 'WooCommerce chưa được kích hoạt.', ['status' => 503]);
        $page = max(1, (int) $request->get_param('page'));
        $per_page = min(48, max(1, (int) $request->get_param('per_page')));
        $args = ['status' => 'publish', 'limit' => $per_page, 'page' => $page, 'paginate' => true, 'orderby' => 'date', 'order' => 'DESC'];
        $search = $request->get_param('search');
        if (is_string($search) && trim($search) !== '') $args['s'] = sanitize_text_field($search);
        $category = $request->get_param('category');
        if (is_string($category) && trim($category) !== '') $args['category'] = [sanitize_title($category)];
        $results = wc_get_products($args);
        $data = array_map([__CLASS__, 'product_response'], $results->products);
        $response = rest_ensure_response($data);
        $response->header('X-WP-Total', (string) $results->total);
        $response->header('X-WP-TotalPages', (string) $results->max_num_pages);
        return $response;
    }

    private static function auction_response($auction_id) {
        $summary = self::bid_summary($auction_id);
        $start_price = absint(get_post_meta($auction_id, '_lw_start_price', true));
        $increment = max(1, absint(get_post_meta($auction_id, '_lw_bid_increment', true)));
        $state = self::state($auction_id);
        $start = self::timestamp(get_post_meta($auction_id, '_lw_start_at', true));
        $end = self::timestamp(get_post_meta($auction_id, '_lw_end_at', true));
        return [
            'id' => (int) $auction_id,
            'title' => get_the_title($auction_id),
            'slug' => get_post_field('post_name', $auction_id),
            'permalink' => get_permalink($auction_id),
            'state' => $state,
            'state_label' => self::status_label($state),
            'image' => self::image_url($auction_id),
            'start_price' => $start_price,
            'current_bid' => max($start_price, $summary['highest']),
            'minimum_bid' => max($start_price, $summary['highest'] > 0 ? $summary['highest'] + $increment : $start_price),
            'bid_increment' => $increment,
            'bid_count' => $summary['count'],
            'starts_at' => $start ? wp_date(DATE_ATOM, $start) : null,
            'ends_at' => $end ? wp_date(DATE_ATOM, $end) : null,
        ];
    }

    /**
     * Read-only snapshot of a published auction for other plugins (chatbox).
     * Uses the same state and minimum-bid rules as the REST API and bid handler.
     */
    public static function snapshot($auction_id) {
        $auction_id = absint($auction_id);
        if (!$auction_id || get_post_type($auction_id) !== 'watch_auction' || get_post_status($auction_id) !== 'publish') return null;
        return self::auction_response($auction_id);
    }

    public static function api_auctions(WP_REST_Request $request) {
        $page = max(1, (int) $request->get_param('page'));
        $per_page = min(48, max(1, (int) $request->get_param('per_page')));
        $query = new WP_Query(['post_type' => 'watch_auction', 'post_status' => 'publish', 'posts_per_page' => $per_page, 'paged' => $page, 'meta_key' => '_lw_end_at', 'orderby' => ['meta_value' => 'ASC', 'date' => 'DESC']]);
        $data = [];
        foreach ($query->posts as $post) {
            $item = self::auction_response($post->ID);
            $filter = $request->get_param('state');
            if (!is_string($filter)) $filter = '';
            if ($filter && $filter !== $item['state']) continue;
            $data[] = $item;
        }
        $response = rest_ensure_response($data);
        $response->header('X-WP-Total', (string) $query->found_posts);
        $response->header('X-WP-TotalPages', (string) $query->max_num_pages);
        return $response;
    }

    public static function api_auction(WP_REST_Request $request) {
        $id = absint($request['id']);
        if (!$id || get_post_type($id) !== 'watch_auction' || get_post_status($id) !== 'publish') return new WP_Error('auction_not_found', 'Không tìm thấy phiên đấu giá.', ['status' => 404]);
        return rest_ensure_response(self::auction_response($id));
    }

    public static function api_can_bid() {
        if (!is_user_logged_in()) return new WP_Error('rest_not_logged_in', 'Bạn cần đăng nhập để đặt giá.', ['status' => 401]);
        return true;
    }

    public static function api_place_bid(WP_REST_Request $request) {
        $auction_id = absint($request['id']);
        $amount = absint($request->get_param('amount'));
        if (!$auction_id || get_post_type($auction_id) !== 'watch_auction' || get_post_status($auction_id) !== 'publish') return new WP_Error('auction_not_found', 'Không tìm thấy phiên đấu giá.', ['status' => 404]);
        if (self::state($auction_id) !== 'live') return new WP_Error('auction_closed', 'Phiên đấu giá không còn nhận giá.', ['status' => 409]);
        $user_id = get_current_user_id();
        $rate_key = 'lw_bid_' . md5($auction_id . ':' . $user_id);
        if (get_transient($rate_key)) return new WP_Error('rate_limited', 'Bạn thao tác quá nhanh, vui lòng thử lại sau vài giây.', ['status' => 429]);
        set_transient($rate_key, 1, 3);
        $start_price = absint(get_post_meta($auction_id, '_lw_start_price', true));
        $increment = max(1, absint(get_post_meta($auction_id, '_lw_bid_increment', true)));
        if ($amount <= 0) return new WP_Error('invalid_amount', 'Mức giá không hợp lệ.', ['status' => 400]);
        global $wpdb;
        $table = $wpdb->prefix . 'luxury_auction_bids';
        $wpdb->query('START TRANSACTION');
        $locked = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID = %d FOR UPDATE", $auction_id));
        if (!$locked || self::state($auction_id) !== 'live') { $wpdb->query('ROLLBACK'); return new WP_Error('auction_closed', 'Phiên đấu giá đã đóng.', ['status' => 409]); }
        $summary = self::bid_summary($auction_id);
        $minimum = $summary['highest'] > 0 ? $summary['highest'] + $increment : $start_price;
        if ($amount < $minimum || (($amount - $start_price) % $increment) !== 0) { $wpdb->query('ROLLBACK'); return new WP_Error('invalid_increment', 'Mức giá chưa đạt bước giá tối thiểu.', ['status' => 422, 'minimum_bid' => $minimum]); }
        $inserted = $wpdb->insert($table, ['auction_id' => $auction_id, 'user_id' => $user_id, 'amount' => $amount, 'created_at' => current_time('mysql'), 'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . wp_salt('nonce'))], ['%d', '%d', '%d', '%s', '%s']);
        $wpdb->query($inserted ? 'COMMIT' : 'ROLLBACK');
        if (!$inserted) return new WP_Error('bid_save_failed', 'Không thể lưu lượt trả giá.', ['status' => 500]);
        return rest_ensure_response(['accepted' => true, 'auction' => self::auction_response($auction_id)]);
    }

    public static function auction_card($auction_id) {
        $start_price = absint(get_post_meta($auction_id, '_lw_start_price', true));
        $retail = absint(get_post_meta($auction_id, '_lw_retail_price', true));
        $increment = max(1, absint(get_post_meta($auction_id, '_lw_bid_increment', true)));
        $summary = self::bid_summary($auction_id);
        $current = max($start_price, $summary['highest']);
        $state = self::state($auction_id);
        $start = self::timestamp(get_post_meta($auction_id, '_lw_start_at', true));
        $end = self::timestamp(get_post_meta($auction_id, '_lw_end_at', true));
        $lot = get_post_meta($auction_id, '_lw_lot_number', true);
        $gender = get_post_meta($auction_id, '_lw_gender', true);
        $condition = get_post_meta($auction_id, '_lw_condition', true);
        $countdown_target = $state === 'upcoming' ? $start : $end;
        $title = get_the_title($auction_id);
        ob_start(); ?>
        <article class="lw-auction-card" 
          data-auction-state="<?php echo esc_attr($state); ?>"
          data-auction-title="<?php echo esc_attr(mb_strtolower($title, 'UTF-8')); ?>"
          data-auction-lot="<?php echo esc_attr(mb_strtolower($lot, 'UTF-8')); ?>"
          data-auction-price="<?php echo esc_attr($current); ?>"
          data-auction-bids="<?php echo esc_attr($summary['count']); ?>"
          data-auction-end="<?php echo esc_attr($countdown_target ?: 0); ?>">
          <a class="lw-auction-card__image" href="<?php echo esc_url(get_permalink($auction_id)); ?>">
            <img src="<?php echo esc_url(self::image_url($auction_id)); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy" decoding="async">
            <span class="lw-auction-status lw-auction-status--<?php echo esc_attr($state); ?>"><i aria-hidden="true"></i><?php echo esc_html(self::status_label($state)); ?></span>
            <small class="lw-lot-badge"><?php echo esc_html($lot ? $lot : 'LOT ' . sprintf('%03d', $auction_id)); ?></small>
          </a>
          <div class="lw-auction-card__body">
            <div class="lw-auction-meta-row">
              <span class="lw-auction-category"><?php echo esc_html($gender ?: 'Haute Horlogerie'); ?></span>
              <span class="lw-auction-certified">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Kiểm định 100%
              </span>
            </div>
            <h3 class="lw-auction-title"><a href="<?php echo esc_url(get_permalink($auction_id)); ?>"><?php echo esc_html($title); ?></a></h3>
            <div class="lw-auction-specs">
              <span><?php echo esc_html($condition ?: 'Đã kiểm định chuyên gia • Fullbox'); ?></span>
            </div>
            <div class="lw-auction-card__footer">
              <div class="lw-auction-card__prices">
                <div class="lw-price-col">
                  <span class="lw-price-label">Giá hiện tại</span>
                  <strong class="lw-current-price"><?php echo wp_kses_post(self::money($current)); ?></strong>
                </div>
                <div class="lw-price-col lw-price-col--retail">
                  <span class="lw-price-label"><?php echo $retail ? 'Giá ước tính' : 'Giá khởi điểm'; ?></span>
                  <span class="lw-retail-price"><?php echo wp_kses_post(self::money($retail ?: $start_price)); ?></span>
                </div>
              </div>
              <div class="lw-auction-card__facts">
                <span><i class="lw-dot-sep"></i><?php echo esc_html(number_format_i18n($summary['count'])); ?> lượt trả</span>
                <span>Bước <?php echo wp_kses_post(self::money($increment)); ?></span>
              </div>
              <?php if ($countdown_target) : ?>
                <div class="lw-countdown" data-end="<?php echo esc_attr(wp_date(DATE_ATOM, $countdown_target)); ?>" data-state="<?php echo esc_attr($state); ?>">
                  <div class="lw-countdown-icon">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  </div>
                  <div class="lw-countdown-content">
                    <span class="lw-countdown-label"><?php echo $state === 'upcoming' ? 'Mở sau' : ($state === 'ended' ? 'Đã kết thúc' : 'Thời gian còn lại'); ?></span>
                    <strong data-countdown-output><?php echo esc_html(wp_date('H:i · d/m/Y', $countdown_target)); ?></strong>
                  </div>
                </div>
              <?php endif; ?>
              <a class="lw-auction-link" href="<?php echo esc_url(get_permalink($auction_id)); ?>">
                <span><?php echo $state === 'live' ? 'THAM GIA TRẢ GIÁ' : ($state === 'upcoming' ? 'XEM TRƯỚC PHIÊN' : 'CHI TIẾT PHIÊN'); ?></span>
                <span class="lw-cta-arrow" aria-hidden="true">→</span>
              </a>
            </div>
          </div>
        </article>
        <?php return ob_get_clean();
    }

    public static function auctions_shortcode() {
        $query = new WP_Query([
            'post_type' => 'watch_auction',
            'post_status' => 'publish',
            'posts_per_page' => 48,
            'meta_key' => '_lw_end_at',
            'orderby' => ['meta_value' => 'ASC', 'date' => 'DESC'],
        ]);

        $overview = function_exists('lw_auction_overview') ? lw_auction_overview() : ['live' => [], 'upcoming' => [], 'ended' => []];
        $total_count = count($overview['live']) + count($overview['upcoming']) + count($overview['ended']);
        if ($total_count === 0 && $query->found_posts > 0) {
            $total_count = $query->found_posts;
        }
        $live_count = count($overview['live']);
        $upcoming_count = count($overview['upcoming']);
        $ended_count = count($overview['ended']);

        ob_start(); ?>
        <div class="lw-auction-wrapper">
          <section class="lw-auction-market" aria-labelledby="lw-auction-market-title">
            <header class="lw-auction-market__header">
              <div class="lw-header-content">
                <span class="lw-header-kicker">HAUTE HORLOGERIE AUCTIONS</span>
                <h1 id="lw-auction-market-title">Sàn Đấu Giá Tuyển Chọn</h1>
                <p>Những cỗ máy thời gian độc bản được tuyển chọn với giá, bước giá và thời hạn công khai. Mỗi lượt trả giá được kiểm soát và ghi nhận bảo mật theo thời gian thực.</p>
              </div>
              <div class="lw-auction-live-mark"><i aria-hidden="true"></i>Khớp lệnh thời gian thực</div>
            </header>

            <!-- A. ENTERPRISE FILTER & TOOLBAR -->
            <div class="lw-auction-toolbar">
              <div class="lw-auction-filters" role="group" aria-label="Lọc phiên đấu giá">
                <button type="button" class="is-active" data-auction-filter="all" aria-pressed="true">
                  Tất cả <span class="lw-tab-count"><?php echo esc_html($total_count); ?></span>
                </button>
                <button type="button" data-auction-filter="live" aria-pressed="false">
                  Đang diễn ra <span class="lw-tab-count"><?php echo esc_html($live_count); ?></span>
                </button>
                <button type="button" data-auction-filter="upcoming" aria-pressed="false">
                  Sắp mở <span class="lw-tab-count"><?php echo esc_html($upcoming_count); ?></span>
                </button>
                <button type="button" data-auction-filter="ended" aria-pressed="false">
                  Đã kết thúc <span class="lw-tab-count"><?php echo esc_html($ended_count); ?></span>
                </button>
              </div>

              <div class="lw-auction-tools">
                <div class="lw-auction-search-wrap">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                  <input type="text" id="lwAuctionSearch" class="lw-auction-search" placeholder="Tìm theo tên hoặc LOT..." aria-label="Tìm kiếm">
                </div>
                <div class="lw-auction-sort-wrap">
                  <select id="lwAuctionSort" class="lw-auction-sort" aria-label="Sắp xếp phiên">
                    <option value="default">Sắp xếp: Mặc định</option>
                    <option value="end-soon">Sắp kết thúc</option>
                    <option value="price-asc">Giá thấp đến cao</option>
                    <option value="price-desc">Giá cao đến thấp</option>
                    <option value="bids-desc">Sôi động nhất (Lượt trả)</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- B. AUCTION GRID -->
            <?php if ($query->have_posts()) : ?>
              <div class="lw-auction-grid" id="lwAuctionGrid">
                <?php while ($query->have_posts()) { $query->the_post(); echo self::auction_card(get_the_ID()); } wp_reset_postdata(); ?>
              </div>
              <div class="lw-auction-filter-empty" hidden>Không tìm thấy phiên đấu giá phù hợp với bộ lọc hoặc từ khóa.</div>
            <?php else : ?>
              <div class="lw-auction-empty"><h3>Chưa có phiên đấu giá</h3><p>Các phiên mới sẽ được công bố tại đây.</p></div>
            <?php endif; ?>

            <!-- C. ENTERPRISE TRUST & METRICS BAR -->
            <div class="lw-auction-trust-bar">
              <div class="lw-trust-item">
                <div class="lw-trust-icon">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <div class="lw-trust-text">
                  <strong>100% Kiểm định chuyên gia</strong>
                  <span>Chứng nhận nguồn gốc minh bạch &amp; chứng thư độc quyền</span>
                </div>
              </div>
              <div class="lw-trust-item">
                <div class="lw-trust-icon">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <div class="lw-trust-text">
                  <strong>Bảo chứng giao dịch</strong>
                  <span>Thanh toán ký quỹ an toàn chuẩn ngân hàng</span>
                </div>
              </div>
              <div class="lw-trust-item">
                <div class="lw-trust-icon">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="1.8"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <div class="lw-trust-text">
                  <strong>Cập nhật thời gian thực</strong>
                  <span>Hệ thống khớp lệnh tức thì &amp; chống nghẽn mạng</span>
                </div>
              </div>
              <div class="lw-trust-item">
                <div class="lw-trust-icon">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="1.8"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <div class="lw-trust-text">
                  <strong>Bảo mật danh tính</strong>
                  <span>Riêng tư tuyệt đối cho nhà sưu tầm cao cấp</span>
                </div>
              </div>
            </div>

            <!-- D. 3-STEP PROCESS (FULL-WIDTH) -->
            <div class="lw-auction-workflow">
              <div class="lw-wf-inner">
                <div class="lw-wf-left">
                  <span class="lw-wf-kicker">QUY TRÌNH MINH BẠCH</span>
                  <h2 class="lw-wf-heading">Ba bước<br>tham gia<br>đấu giá</h2>
                  <p class="lw-wf-desc">Minh bạch, nhanh chóng và an toàn — mỗi lượt trả giá được bảo chứng và xác thực tự động trên hệ thống máy chủ độc lập.</p>
                  <a href="<?php echo esc_url( wc_get_page_permalink('myaccount') ); ?>" class="lw-wf-cta">
                    ĐĂNG KÝ NGAY
                    <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </a>
                </div>
                <div class="lw-wf-right">
                  <div class="lw-wf-item" data-n="01">
                    <div class="lw-wf-item__top">
                      <span class="lw-wf-num">01</span>
                      <div class="lw-wf-rule"></div>
                    </div>
                    <div class="lw-wf-item__body">
                      <svg class="lw-wf-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                      <strong>Đăng nhập thành viên</strong>
                      <p>Tài khoản xác định người trả giá hợp lệ và bảo vệ toàn bộ lịch sử phiên của quý khách.</p>
                    </div>
                  </div>
                  <div class="lw-wf-item" data-n="02">
                    <div class="lw-wf-item__top">
                      <span class="lw-wf-num">02</span>
                      <div class="lw-wf-rule"></div>
                    </div>
                    <div class="lw-wf-item__body">
                      <svg class="lw-wf-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                      <strong>Chọn mức giá hợp lệ</strong>
                      <p>Hệ thống tự động kiểm tra bước giá tối thiểu và trạng thái phiên theo thời gian thực.</p>
                    </div>
                  </div>
                  <div class="lw-wf-item" data-n="03">
                    <div class="lw-wf-item__top">
                      <span class="lw-wf-num">03</span>
                      <div class="lw-wf-rule"></div>
                    </div>
                    <div class="lw-wf-item__body">
                      <svg class="lw-wf-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                      <strong>Theo dõi kết quả</strong>
                      <p>Mức giá cao nhất và lịch sử lượt trả được cập nhật tức thì sau mỗi lượt thành công.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </section>
        </div>
        <?php return ob_get_clean();
    }

    private static function bidder_name($user_id) {
        if ((int) $user_id === get_current_user_id()) return 'Bạn';
        $user = get_userdata($user_id);
        $name = $user ? trim($user->display_name) : '';
        if ($name === '') return 'Thành viên';
        $first = function_exists('mb_substr') ? mb_substr($name, 0, 1, 'UTF-8') : substr($name, 0, 1);
        return $first . '•••';
    }

    private static function recent_bids($auction_id, $limit = 8) {
        global $wpdb;
        $table = $wpdb->prefix . 'luxury_auction_bids';
        return $wpdb->get_results($wpdb->prepare("SELECT user_id, amount, created_at FROM {$table} WHERE auction_id = %d ORDER BY amount DESC, created_at ASC LIMIT %d", $auction_id, $limit));
    }

    public static function auction_content($content) {
        if (!is_singular('watch_auction') || !in_the_loop() || !is_main_query()) return $content;
        $id = get_the_ID();
        $state = self::state($id);
        $start_price = absint(get_post_meta($id, '_lw_start_price', true));
        $retail = absint(get_post_meta($id, '_lw_retail_price', true));
        $increment = max(1, absint(get_post_meta($id, '_lw_bid_increment', true)));
        $reserve = absint(get_post_meta($id, '_lw_reserve_price', true));
        $summary = self::bid_summary($id);
        $current = max($start_price, $summary['highest']);
        $minimum = $summary['highest'] > 0 ? $summary['highest'] + $increment : $start_price;
        $start = self::timestamp(get_post_meta($id, '_lw_start_at', true));
        $end = self::timestamp(get_post_meta($id, '_lw_end_at', true));
        $notice = isset($_GET['bid_status']) ? sanitize_key(wp_unslash($_GET['bid_status'])) : '';
        $messages = [
            'accepted' => ['success', 'Lượt trả giá đã được ghi nhận. Bạn đang theo dõi phiên với mức giá mới nhất.'],
            'low' => ['warning', 'Giá vừa thay đổi hoặc chưa đúng bước giá. Hãy dùng mức tối thiểu đang hiển thị.'],
            'closed' => ['warning', 'Phiên chưa sẵn sàng nhận giá hoặc đã kết thúc.'],
            'rate' => ['warning', 'Bạn thao tác quá nhanh. Vui lòng chờ vài giây rồi thử lại.'],
            'error' => ['error', 'Không thể lưu lượt trả giá. Dữ liệu của bạn chưa bị ghi nhận; hãy thử lại.'],
        ];
        $bids = self::recent_bids($id);
        ob_start(); ?>
        <section class="lw-auction-detail" data-auction-state="<?php echo esc_attr($state); ?>">
          <?php if (isset($messages[$notice])) : ?><div class="lw-auction-notice lw-auction-notice--<?php echo esc_attr($messages[$notice][0]); ?>" role="status"><?php echo esc_html($messages[$notice][1]); ?></div><?php endif; ?>
          <div class="lw-auction-detail__top">
            <div><span class="lw-auction-status lw-auction-status--<?php echo esc_attr($state); ?>"><i aria-hidden="true"></i><?php echo esc_html(self::status_label($state)); ?></span><p class="lw-auction-detail__label">Giá hiện tại</p><p class="lw-auction-detail__price"><?php echo wp_kses_post(self::money($current)); ?></p><p class="lw-auction-detail__activity"><?php echo esc_html(number_format_i18n($summary['count'])); ?> lượt trả giá đã ghi nhận</p></div>
            <?php if ($end || $start) : $target = $state === 'upcoming' ? $start : $end; ?><div class="lw-auction-detail__clock"><span><?php echo $state === 'upcoming' ? 'Phiên mở sau' : ($state === 'ended' ? 'Phiên kết thúc lúc' : 'Thời gian còn lại'); ?></span><strong class="lw-countdown" data-end="<?php echo esc_attr(wp_date(DATE_ATOM, $target)); ?>" data-state="<?php echo esc_attr($state); ?>"><span data-countdown-output><?php echo esc_html(wp_date('H:i · d/m/Y', $target)); ?></span></strong></div><?php endif; ?>
          </div>
          <dl class="lw-auction-metrics"><div><dt>Giá khởi điểm</dt><dd><?php echo wp_kses_post(self::money($start_price)); ?></dd></div><div><dt>Bước giá</dt><dd><?php echo wp_kses_post(self::money($increment)); ?></dd></div><?php if ($retail) : ?><div><dt>Giá niêm yết</dt><dd><?php echo wp_kses_post(self::money($retail)); ?></dd></div><?php endif; ?><div><dt>Trạng thái dự trữ</dt><dd><?php echo (!$reserve || $current >= $reserve) ? 'Đã đạt' : 'Chưa đạt'; ?></dd></div></dl>
          <div class="lw-auction-detail__columns">
            <div class="lw-auction-bid-panel"><h2>Đặt mức giá của bạn</h2>
              <?php if ($state === 'live' && is_user_logged_in()) : ?>
                <form class="lw-bid-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" data-bid-form><input type="hidden" name="action" value="luxury_place_bid"><input type="hidden" name="auction_id" value="<?php echo esc_attr($id); ?>"><?php wp_nonce_field('luxury_bid_' . $id, 'luxury_bid_nonce'); ?><label for="luxury-bid-amount">Mức trả giá (VND)</label><div class="lw-bid-form__row"><input id="luxury-bid-amount" name="amount" type="number" inputmode="numeric" min="<?php echo esc_attr($minimum); ?>" step="<?php echo esc_attr($increment); ?>" value="<?php echo esc_attr($minimum); ?>" required aria-describedby="luxury-bid-hint"><button type="submit">Xác nhận trả giá</button></div><div class="lw-bid-presets" aria-label="Mức giá đề xuất"><?php foreach ([1, 2, 3] as $multiple) : $suggestion = $minimum + ($increment * ($multiple - 1)); ?><button type="button" data-bid-value="<?php echo esc_attr($suggestion); ?>">+<?php echo esc_html(number_format_i18n($increment * $multiple)); ?></button><?php endforeach; ?></div><p id="luxury-bid-hint">Tối thiểu <?php echo wp_kses_post(self::money($minimum)); ?>. Hệ thống sẽ kiểm tra lại giá ngay trước khi ghi nhận.</p></form>
              <?php elseif ($state === 'live') : ?><p>Đăng nhập để xác nhận danh tính người trả giá và lưu lịch sử phiên.</p><a class="lw-auction-login" href="<?php echo esc_url(wp_login_url(get_permalink($id))); ?>">Đăng nhập để trả giá</a>
              <?php elseif ($state === 'upcoming') : ?><p>Phiên chưa mở. Hãy quay lại vào <?php echo esc_html(wp_date('H:i, d/m/Y', $start)); ?>.</p>
              <?php elseif ($state === 'invalid') : ?><p>Thông tin thời gian của phiên đang được cập nhật. Vui lòng quay lại sau.</p>
              <?php else : ?><p>Phiên đã kết thúc. Giá cao nhất được ghi nhận là <strong><?php echo wp_kses_post(self::money($current)); ?></strong>.</p><?php endif; ?>
              <div class="lw-auction-assurance"><strong>Quy tắc an toàn</strong><p>Máy chủ khóa phiên trong lúc ghi giá, chặn gửi lặp và từ chối mọi giá đến sau giờ đóng.</p></div>
            </div>
            <div class="lw-auction-history"><div class="lw-auction-history__head"><h2>Lịch sử trả giá</h2><span>Mới nhất theo giá</span></div>
              <?php if ($bids) : ?><ol><?php foreach ($bids as $index => $bid) : ?><li><span class="lw-auction-rank"><?php echo esc_html($index + 1); ?></span><div><strong><?php echo esc_html(self::bidder_name($bid->user_id)); ?></strong><time datetime="<?php echo esc_attr(mysql2date(DATE_ATOM, $bid->created_at)); ?>"><?php echo esc_html(mysql2date('H:i · d/m', $bid->created_at)); ?></time></div><b><?php echo wp_kses_post(self::money($bid->amount)); ?></b></li><?php endforeach; ?></ol><?php else : ?><div class="lw-auction-history__empty"><strong>Chưa có lượt trả giá</strong><p>Hãy là người đầu tiên mở nhịp cho phiên đấu giá này.</p></div><?php endif; ?>
            </div>
          </div>
        </section>
        <?php return $content . ob_get_clean();
    }

    public static function require_login() {
        $auction_id = isset($_POST['auction_id']) ? absint($_POST['auction_id']) : 0;
        wp_safe_redirect(wp_login_url($auction_id ? get_permalink($auction_id) : home_url('/')));
        exit;
    }

    private static function redirect_status($auction_id, $status) {
        wp_safe_redirect(add_query_arg('bid_status', sanitize_key($status), get_permalink($auction_id)));
        exit;
    }

    public static function place_bid() {
        if (!is_user_logged_in()) self::require_login();
        $auction_id = isset($_POST['auction_id']) ? absint($_POST['auction_id']) : 0;
        $nonce = isset($_POST['luxury_bid_nonce']) ? sanitize_text_field(wp_unslash($_POST['luxury_bid_nonce'])) : '';
        if (!$auction_id || !wp_verify_nonce($nonce, 'luxury_bid_' . $auction_id) || get_post_type($auction_id) !== 'watch_auction' || get_post_status($auction_id) !== 'publish') wp_die('Yêu cầu trả giá không hợp lệ.', 'Không thể trả giá', ['response' => 400]);
        if (self::state($auction_id) !== 'live') self::redirect_status($auction_id, 'closed');
        if (get_post_meta($auction_id, '_lw_auction_result', true)) self::redirect_status($auction_id, 'closed');
        $user_id = get_current_user_id();
        $rate_key = 'lw_bid_' . md5($auction_id . ':' . $user_id);
        if (get_transient($rate_key)) self::redirect_status($auction_id, 'rate');
        set_transient($rate_key, 1, 3);
        $amount = self::parse_amount(isset($_POST['amount']) ? wp_unslash($_POST['amount']) : '');
        if ($amount === false) self::redirect_status($auction_id, 'low');
        global $wpdb;
        $table = $wpdb->prefix . 'luxury_auction_bids';
        if ($wpdb->query('START TRANSACTION') === false) self::redirect_status($auction_id, 'error');
        $locked = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID = %d FOR UPDATE", $auction_id));
        if ($wpdb->last_error) { $wpdb->query('ROLLBACK'); self::redirect_status($auction_id, 'error'); }
        clean_post_cache($auction_id);
        if (!$locked || get_post_status($auction_id) !== 'publish' || self::state($auction_id) !== 'live' || get_post_meta($auction_id, '_lw_auction_result', true)) {
            $wpdb->query('ROLLBACK');
            self::redirect_status($auction_id, 'closed');
        }
        $start_price = self::parse_amount(get_post_meta($auction_id, '_lw_start_price', true));
        $increment = self::parse_amount(get_post_meta($auction_id, '_lw_bid_increment', true));
        if ($start_price === false || $increment === false) {
            $wpdb->query('ROLLBACK');
            self::redirect_status($auction_id, 'closed');
        }
        $summary = self::bid_summary($auction_id);
        if ($wpdb->last_error) { $wpdb->query('ROLLBACK'); self::redirect_status($auction_id, 'error'); }
        $recent_bid = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE auction_id = %d AND user_id = %d AND created_at > %s LIMIT 1",
            $auction_id, $user_id, current_datetime()->modify('-3 seconds')->format('Y-m-d H:i:s')
        ));
        if ($wpdb->last_error) { $wpdb->query('ROLLBACK'); self::redirect_status($auction_id, 'error'); }
        if ($recent_bid) { $wpdb->query('ROLLBACK'); self::redirect_status($auction_id, 'rate'); }
        if ($summary['highest'] > PHP_INT_MAX - $increment) {
            $wpdb->query('ROLLBACK');
            self::redirect_status($auction_id, 'low');
        }
        $minimum = $summary['highest'] > 0 ? $summary['highest'] + $increment : $start_price;
        if ($amount < $minimum || (($amount - $start_price) % $increment) !== 0) { $wpdb->query('ROLLBACK'); self::redirect_status($auction_id, 'low'); }
        $inserted = $wpdb->insert($table, ['auction_id' => $auction_id, 'user_id' => $user_id, 'amount' => $amount, 'created_at' => current_time('mysql'), 'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . wp_salt('nonce'))], ['%d', '%d', '%d', '%s', '%s']);
        if (!$inserted || $wpdb->query('COMMIT') === false) {
            $wpdb->query('ROLLBACK');
            self::redirect_status($auction_id, 'error');
        }
        self::redirect_status($auction_id, 'accepted');
    }

    public static function assets() {
        wp_enqueue_style('lw-auction-fonts', 'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;0,800;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap', [], null);
        wp_enqueue_style('luxury-auctions', plugin_dir_url(__FILE__) . 'assets/auctions.css', ['lw-auction-fonts'], self::VERSION);
        wp_enqueue_script('luxury-auctions', plugin_dir_url(__FILE__) . 'assets/auctions.js', [], self::VERSION, true);
    }
}

register_activation_hook(__FILE__, ['Luxury_Watches_Auctions', 'activate']);
Luxury_Watches_Auctions::init();
require_once __DIR__ . '/includes/class-luxury-auction-admin.php';
Luxury_Auction_Admin::init();
require_once __DIR__ . '/includes/class-luxury-auction-dashboard.php';
Luxury_Auction_Dashboard::init();

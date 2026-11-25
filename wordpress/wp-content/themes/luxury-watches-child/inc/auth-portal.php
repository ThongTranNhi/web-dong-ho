<?php
/**
 * Luxury Watches – cổng đăng nhập / đăng ký.
 *
 * Toàn bộ xác thực vẫn do WordPress + WooCommerce xử lý (WC_Form_Handler::process_login,
 * process_registration, wp_signon). File này chỉ:
 *  - dựng khung giao diện toàn màn hình cho trang Tài khoản khi khách chưa đăng nhập;
 *  - validate dữ liệu phía server qua các filter chính thức của WooCommerce;
 *  - ghi nhận lỗi theo từng trường để hiển thị ngay dưới input liên quan;
 *  - giữ lại trang trước đó để chuyển hướng sau khi đăng nhập.
 *
 * @package Luxury_Watches_Atelier
 */

if (!defined('ABSPATH')) {
    exit;
}

/* -------------------------------------------------------------------------
 * Trạng thái lỗi theo trường trong một request
 * ---------------------------------------------------------------------- */

function lw_auth_add_error(string $field, string $message): void
{
    if (!isset($GLOBALS['lw_auth_errors']) || !is_array($GLOBALS['lw_auth_errors'])) {
        $GLOBALS['lw_auth_errors'] = [];
    }
    if (!isset($GLOBALS['lw_auth_errors'][$field])) {
        $GLOBALS['lw_auth_errors'][$field] = $message;
    }
}

function lw_auth_get_error(string $field): string
{
    return isset($GLOBALS['lw_auth_errors'][$field]) ? (string) $GLOBALS['lw_auth_errors'][$field] : '';
}

function lw_auth_has_field_errors(): bool
{
    return !empty($GLOBALS['lw_auth_errors']);
}

/* -------------------------------------------------------------------------
 * Nhận diện màn hình
 * ---------------------------------------------------------------------- */

/** Trang Tài khoản đang hiển thị form đăng nhập / đăng ký / quên mật khẩu. */
function lw_auth_is_portal_view(): bool
{
    if (!function_exists('is_account_page') || !is_account_page()) {
        return false;
    }
    return !is_user_logged_in() || is_wc_endpoint_url('lost-password');
}

function lw_auth_registration_enabled(): bool
{
    return 'yes' === get_option('woocommerce_enable_myaccount_registration');
}

/** Đang ở chế độ đăng ký: ?action=register hoặc vừa gửi form đăng ký bị lỗi. */
function lw_auth_is_register_mode(): bool
{
    if (!lw_auth_registration_enabled()) {
        return false;
    }
    // phpcs:disable WordPress.Security.NonceVerification -- chỉ đọc để chọn giao diện.
    if (isset($_POST['register']) || isset($_POST['lw_phone_register'])) {
        return true;
    }
    return isset($_GET['action']) && 'register' === sanitize_key(wp_unslash($_GET['action']));
    // phpcs:enable
}

/** Cách đăng ký đang chọn: 'phone' (mặc định) hoặc 'email'. */
function lw_auth_register_method(): string
{
    // phpcs:disable WordPress.Security.NonceVerification -- chỉ đọc để chọn tab.
    if (isset($_POST['register'])) {
        return 'email';
    }
    if (isset($_POST['lw_phone_register'])) {
        return 'phone';
    }
    return (isset($_GET['method']) && 'email' === sanitize_key(wp_unslash($_GET['method']))) ? 'email' : 'phone';
    // phpcs:enable
}

function lw_auth_account_url(): string
{
    return function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');
}

/* -------------------------------------------------------------------------
 * Chuyển hướng về trang trước đó
 * ---------------------------------------------------------------------- */

/**
 * Địa chỉ sẽ quay về sau khi đăng nhập. Ưu tiên: giá trị form đã gửi →
 * ?redirect_to= → trang người dùng vừa rời đi. Chỉ chấp nhận URL nội bộ
 * (wp_validate_redirect) và bỏ qua chính trang tài khoản / wp-login.
 */
function lw_auth_redirect_target(): string
{
    // phpcs:disable WordPress.Security.NonceVerification
    if (!empty($_POST['redirect']) && is_string($_POST['redirect'])) {
        $candidate = wp_unslash($_POST['redirect']);
    } elseif (!empty($_GET['redirect_to']) && is_string($_GET['redirect_to'])) {
        $candidate = wp_unslash($_GET['redirect_to']);
    } else {
        $candidate = (string) wp_get_referer();
    }
    // phpcs:enable

    $candidate = wp_validate_redirect(esc_url_raw(trim($candidate)), '');
    if ('' === $candidate) {
        return '';
    }

    $path    = (string) wp_parse_url($candidate, PHP_URL_PATH);
    $account = (string) wp_parse_url(lw_auth_account_url(), PHP_URL_PATH);
    if (('' !== $account && 0 === strpos(trailingslashit($path), trailingslashit($account)))
        || false !== strpos($path, 'wp-login.php')) {
        return '';
    }

    return $candidate;
}

/**
 * WooCommerce Blocks tự in thêm <input name="redirect"> từ ?redirect_to= chưa lọc,
 * trùng tên và ghi đè giá trị đã kiểm tra ở form-login.php. Gỡ nó trước khi chạy.
 */
add_action('woocommerce_login_form_end', function () {
    global $wp_filter;
    if (empty($wp_filter['woocommerce_login_form_end']->callbacks[10])) {
        return;
    }
    foreach ($wp_filter['woocommerce_login_form_end']->callbacks[10] as $callback) {
        $fn = $callback['function'];
        if (is_array($fn) && isset($fn[1]) && 'redirect_to_field' === $fn[1]) {
            remove_action('woocommerce_login_form_end', $fn, 10);
        }
    }
}, 1);

/** URL chuyển giữa đăng nhập ↔ đăng ký, mang theo trang cần quay về. */
function lw_auth_switch_url(string $mode, array $extra = []): string
{
    $args     = 'register' === $mode ? array_merge(['action' => 'register'], $extra) : [];
    $redirect = lw_auth_redirect_target();
    if ('' !== $redirect) {
        $args['redirect_to'] = rawurlencode($redirect);
    }
    return add_query_arg($args, lw_auth_account_url());
}

/* -------------------------------------------------------------------------
 * Khung giao diện toàn màn hình + tài nguyên
 * ---------------------------------------------------------------------- */

add_filter('template_include', function ($template) {
    if (lw_auth_is_portal_view()) {
        $portal = get_stylesheet_directory() . '/templates/auth-portal.php';
        if (file_exists($portal)) {
            return $portal;
        }
    }
    return $template;
}, 99);

add_action('wp_enqueue_scripts', function () {
    if (!lw_auth_is_portal_view()) {
        return;
    }

    // Trang đăng nhập có khung riêng: gỡ CSS/JS giao diện cửa hàng để không chồng style.
    $styles = [
        'storefront-style', 'storefront-woocommerce-style', 'storefront-child-style', 'storefront-parent',
        'storefront-icons', 'storefront-gutenberg-blocks', 'storefront-woocommerce-brands-style',
        'luxury-watches', 'luxury-watches-motion', 'luxury-watches-nav', 'luxury-watches-preview',
        'watchstore-style', 'lw-storefront-refresh', 'luxury-auctions', 'lw-auction-fonts',
        'brands-styles', 'select2', 'wc-blocks-style',
    ];
    foreach ($styles as $handle) {
        wp_dequeue_style($handle);
    }
    foreach (['luxury-watches-motion', 'luxury-watches-nav', 'luxury-watches-preview', 'storefront-navigation', 'storefront-header-cart', 'storefront-handheld-footer-bar'] as $handle) {
        wp_dequeue_script($handle);
    }

    $dir = get_stylesheet_directory();
    $uri = get_stylesheet_directory_uri();

    wp_enqueue_style('lw-auth-fonts', 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&display=swap', [], null);
    wp_enqueue_style('lw-auth-portal', $uri . '/assets/auth-portal.css', ['lw-auth-fonts'], filemtime($dir . '/assets/auth-portal.css'));
    wp_enqueue_script('lw-auth-portal', $uri . '/assets/auth-portal.js', [], filemtime($dir . '/assets/auth-portal.js'), true);
    wp_localize_script('lw-auth-portal', 'lwAuthI18n', [
        // Khóa theo id của input.
        'required'    => [
            'username'     => 'Vui lòng nhập email hoặc tên đăng nhập.',
            'password'     => 'Vui lòng nhập mật khẩu.',
            'reg_username' => 'Vui lòng nhập tên đăng nhập.',
            'reg_email'    => 'Vui lòng nhập địa chỉ email.',
            'reg_password' => 'Vui lòng tạo mật khẩu.',
            'reg_phone'    => 'Vui lòng nhập số điện thoại.',
            'reg_phone_password' => 'Vui lòng tạo mật khẩu.',
            'user_login'   => 'Vui lòng nhập email hoặc tên đăng nhập.',
        ],
        'invalidEmail' => 'Địa chỉ email chưa đúng định dạng.',
        'invalidPhone' => 'Số điện thoại chưa đúng định dạng (ví dụ: 0912 345 678).',
        'shortPassword' => 'Mật khẩu cần tối thiểu 8 ký tự.',
        'googleClientId' => function_exists('lw_google_client_id') ? lw_google_client_id() : '',
        'googleError'  => 'Không thể mở đăng nhập Google. Vui lòng thử lại hoặc dùng cách khác.',
        'offline'      => 'Không có kết nối mạng. Vui lòng kiểm tra kết nối và thử lại.',
        'network'      => 'Không thể kết nối tới máy chủ. Vui lòng kiểm tra mạng và thử lại.',
        'server'       => 'Hệ thống đang gặp sự cố. Vui lòng thử lại sau ít phút.',
        'timeout'      => 'Máy chủ phản hồi quá lâu. Vui lòng thử lại.',
        'redirecting'  => 'Đang chuyển hướng…',
        'showPassword' => 'Hiện mật khẩu',
        'hidePassword' => 'Ẩn mật khẩu',
    ]);
}, 999);

// Toast "đã thêm vào giỏ" của theme không dùng trên trang đăng nhập (CSS của nó đã bị gỡ).
add_action('wp', function () {
    if (lw_auth_is_portal_view()) {
        remove_action('wp_footer', 'lw_render_add_to_cart_toast_notification');
    }
});

add_filter('body_class', function ($classes) {
    if (lw_auth_is_portal_view()) {
        $classes   = array_diff($classes, ['right-sidebar', 'left-sidebar', 'storefront-full-width-content', 'lw-full-width']);
        $classes[] = 'lw-auth-body';
    }
    return $classes;
}, 100);

/* -------------------------------------------------------------------------
 * Validate + sanitize phía server (qua filter của WooCommerce)
 * ---------------------------------------------------------------------- */

/** Nonce hết hạn: WooCommerce bỏ qua im lặng, ta báo lỗi rõ ràng. Chạy trước WC (ưu tiên 20). */
add_action('wp_loaded', function () {
    // phpcs:disable WordPress.Security.NonceVerification -- đang kiểm tra chính nonce.
    if (isset($_POST['login'], $_POST['username'])) {
        $nonce = isset($_POST['woocommerce-login-nonce']) ? sanitize_text_field(wp_unslash($_POST['woocommerce-login-nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'woocommerce-login')) {
            lw_auth_add_error('general', 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang và đăng nhập lại.');
        }
    }
    if (isset($_POST['register'], $_POST['email'])) {
        $nonce = isset($_POST['woocommerce-register-nonce']) ? sanitize_text_field(wp_unslash($_POST['woocommerce-register-nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'woocommerce-register')) {
            lw_auth_add_error('general', 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.');
        }
    }
    // phpcs:enable
}, 19);

add_filter('woocommerce_process_login_errors', function ($errors, $username, $password) {
    $username = sanitize_text_field((string) $username);

    if ('' === $username) {
        $msg = 'Vui lòng nhập email hoặc tên đăng nhập.';
        lw_auth_add_error('username', $msg);
        $errors->add('lw_empty_username', $msg);
    } elseif (strlen($username) > 254) {
        $msg = 'Email hoặc tên đăng nhập quá dài.';
        lw_auth_add_error('username', $msg);
        $errors->add('lw_long_username', $msg);
    } elseif (false !== strpos($username, '@') && !is_email($username)) {
        $msg = 'Địa chỉ email chưa đúng định dạng.';
        lw_auth_add_error('username', $msg);
        $errors->add('lw_invalid_email', $msg);
    }

    if ('' === (string) $password) {
        $msg = 'Vui lòng nhập mật khẩu.';
        lw_auth_add_error('password', $msg);
        $errors->add('lw_empty_password', $msg);
    }

    return $errors;
}, 10, 3);

add_filter('woocommerce_login_credentials', function ($creds) {
    // Mật khẩu giữ nguyên (mọi ký tự đều hợp lệ); chỉ làm sạch định danh.
    $creds['user_login'] = sanitize_text_field((string) $creds['user_login']);
    $creds['remember']   = !empty($creds['remember']);
    return $creds;
});

/** Sai thông tin đăng nhập: không tiết lộ là sai email hay sai mật khẩu. */
add_action('wp_login_failed', function ($username, $error = null) {
    // phpcs:ignore WordPress.Security.NonceVerification -- WC đã xác minh nonce trước khi gọi wp_signon.
    if (!isset($_POST['login'])) {
        return;
    }
    $code = is_wp_error($error) ? $error->get_error_code() : '';
    if (in_array($code, ['invalid_username', 'invalid_email', 'incorrect_password', 'authentication_failed'], true)) {
        lw_auth_add_error('credentials', '1');
        lw_auth_add_error('password', 'Email/tên đăng nhập hoặc mật khẩu không chính xác.');
        return;
    }
    $message = is_wp_error($error) ? wp_strip_all_tags($error->get_error_message()) : '';
    lw_auth_add_error('general', '' !== $message ? $message : 'Không thể đăng nhập lúc này. Vui lòng thử lại.');
}, 10, 2);

add_filter('woocommerce_process_registration_errors', function ($errors, $username, $password, $email) {
    $email = sanitize_email((string) $email);

    if ('no' === get_option('woocommerce_registration_generate_username')) {
        $username = sanitize_user((string) $username, true);
        if ('' === $username) {
            $msg = 'Vui lòng nhập tên đăng nhập.';
            lw_auth_add_error('reg_username', $msg);
            $errors->add('lw_reg_username', $msg);
        } elseif (username_exists($username)) {
            $msg = 'Tên đăng nhập này đã được sử dụng.';
            lw_auth_add_error('reg_username', $msg);
            $errors->add('lw_reg_username_exists', $msg);
        }
    }

    if ('' === $email) {
        $msg = 'Vui lòng nhập địa chỉ email hợp lệ.';
        lw_auth_add_error('reg_email', $msg);
        $errors->add('lw_reg_email', $msg);
    } elseif (!is_email($email)) {
        $msg = 'Địa chỉ email chưa đúng định dạng.';
        lw_auth_add_error('reg_email', $msg);
        $errors->add('lw_reg_email_invalid', $msg);
    } elseif (email_exists($email)) {
        $msg = 'Email này đã có tài khoản. Vui lòng đăng nhập.';
        lw_auth_add_error('reg_email', $msg);
        $errors->add('lw_reg_email_exists', $msg);
    }

    if ('no' === get_option('woocommerce_registration_generate_password')) {
        if ('' === (string) $password) {
            $msg = 'Vui lòng tạo mật khẩu.';
            lw_auth_add_error('reg_password', $msg);
            $errors->add('lw_reg_password', $msg);
        } elseif (strlen((string) $password) < 8) {
            $msg = 'Mật khẩu cần tối thiểu 8 ký tự.';
            lw_auth_add_error('reg_password', $msg);
            $errors->add('lw_reg_password_short', $msg);
        }
    }

    return $errors;
}, 10, 4);

/** Việt hóa đoạn chính sách ở form đăng ký – chỉ khi quản trị chưa tự sửa nội dung. */
add_filter('woocommerce_get_privacy_policy_text', function ($text, $type) {
    $default = sprintf(
        /* translators: %s privacy policy page name and link */
        __('Your personal data will be used to support your experience throughout this website, to manage access to your account, and for other purposes described in our %s.', 'woocommerce'),
        '[privacy_policy]'
    );
    if ('registration' !== $type || trim((string) $text) !== trim($default)) {
        return $text;
    }
    $page_id = (int) get_option('wp_page_for_privacy_policy');
    $policy  = ($page_id && 'publish' === get_post_status($page_id))
        ? '<a href="' . esc_url(get_permalink($page_id)) . '" target="_blank" rel="noopener">chính sách bảo mật</a>'
        : 'chính sách bảo mật';
    return 'Thông tin cá nhân của bạn được dùng để quản lý tài khoản, hỗ trợ trải nghiệm mua sắm và cho các mục đích khác được mô tả trong ' . $policy . ' của chúng tôi.';
}, 10, 2);

/* -------------------------------------------------------------------------
 * Liên kết đăng nhập ngoài site (vd. nút đăng nhập ở phiên đấu giá) dùng trang mới.
 * Khu vực quản trị giữ wp-login.php gốc.
 * ---------------------------------------------------------------------- */

add_filter('login_url', function ($login_url, $redirect, $force_reauth) {
    if (is_admin() || $force_reauth || !function_exists('wc_get_page_permalink')) {
        return $login_url;
    }
    $url = lw_auth_account_url();
    if (!empty($redirect)) {
        $url = add_query_arg('redirect_to', rawurlencode($redirect), $url);
    }
    return $url;
}, 10, 3);

/* -------------------------------------------------------------------------
 * Notice của WooCommerce: lấy ra để tự hiển thị trong form
 * ---------------------------------------------------------------------- */

/**
 * Lấy và xóa notice đang chờ. Nếu đã có lỗi theo trường, bỏ notice lỗi trùng lặp.
 *
 * @return array{error: string[], success: string[]}
 */
function lw_auth_take_notices(): array
{
    $out = ['error' => [], 'success' => []];
    if (!function_exists('wc_get_notices')) {
        return $out;
    }
    $notices = wc_get_notices();
    wc_clear_notices();

    $strip = static function ($text) {
        $text = preg_replace('#<strong>\s*(Error|Lỗi)\s*:\s*</strong>\s*#i', '', (string) $text);
        return trim((string) $text);
    };

    if (!lw_auth_has_field_errors()) {
        foreach ($notices['error'] ?? [] as $notice) {
            $out['error'][] = $strip($notice['notice'] ?? '');
        }
    }
    foreach (array_merge($notices['success'] ?? [], $notices['notice'] ?? []) as $notice) {
        $out['success'][] = $strip($notice['notice'] ?? '');
    }
    $general = lw_auth_get_error('general');
    if ('' !== $general) {
        array_unshift($out['error'], $general);
    }
    $out['error'] = array_values(array_unique(array_filter($out['error'])));
    return $out;
}

/* -------------------------------------------------------------------------
 * Thành phần giao diện dùng chung
 * ---------------------------------------------------------------------- */

/**
 * In một trường nhập liệu kèm nhãn, nút hiện/ẩn mật khẩu và vùng báo lỗi.
 *
 * @param array $args id, name, label, type, autocomplete, value, error, inputmode, hint.
 */
function lw_auth_field(array $args): void
{
    $a = wp_parse_args($args, [
        'id'           => '',
        'name'         => '',
        'label'        => '',
        'type'         => 'text',
        'autocomplete' => 'off',
        'value'        => '',
        'error'        => '',
        'invalid'      => false,
        'inputmode'    => '',
        'hint'         => '',
    ]);
    $is_password = 'password' === $a['type'];
    $invalid     = $a['invalid'] || '' !== $a['error'];
    $error_id    = $a['id'] . '-error';
    $hint_id     = $a['id'] . '-hint';
    $described   = trim(('' !== $a['hint'] ? $hint_id . ' ' : '') . $error_id);
    ?>
    <div class="lw-field<?php echo $invalid ? ' is-invalid' : ''; ?>" data-lw-field="<?php echo esc_attr($a['name']); ?>">
        <label class="lw-field__label" for="<?php echo esc_attr($a['id']); ?>"><?php echo esc_html($a['label']); ?></label>
        <div class="lw-field__control">
            <input
                class="lw-field__input input-text"
                type="<?php echo esc_attr($a['type']); ?>"
                id="<?php echo esc_attr($a['id']); ?>"
                name="<?php echo esc_attr($a['name']); ?>"
                autocomplete="<?php echo esc_attr($a['autocomplete']); ?>"
                <?php if ('' !== $a['inputmode']) : ?>inputmode="<?php echo esc_attr($a['inputmode']); ?>"<?php endif; ?>
                <?php if (!$is_password) : ?>value="<?php echo esc_attr($a['value']); ?>"<?php endif; ?>
                autocapitalize="none"
                spellcheck="false"
                required
                aria-required="true"
                aria-invalid="<?php echo $invalid ? 'true' : 'false'; ?>"
                aria-describedby="<?php echo esc_attr($described); ?>"
            />
            <?php if ($is_password) : ?>
                <button type="button" class="lw-field__toggle" data-lw-toggle-password aria-controls="<?php echo esc_attr($a['id']); ?>" aria-pressed="false" aria-label="Hiện mật khẩu">
                    <svg class="lw-icon-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="lw-icon-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.6 5.1A10.4 10.4 0 0 1 12 5c6.4 0 10 7 10 7a17.6 17.6 0 0 1-2.6 3.5"/><path d="M6.6 6.6C3.7 8.4 2 12 2 12s3.6 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="m2 2 20 20"/></svg>
                </button>
            <?php endif; ?>
        </div>
        <?php if ('' !== $a['hint']) : ?>
            <p class="lw-field__hint" id="<?php echo esc_attr($hint_id); ?>"><?php echo esc_html($a['hint']); ?></p>
        <?php endif; ?>
        <p class="lw-field__error" id="<?php echo esc_attr($error_id); ?>" data-lw-error<?php echo '' === $a['error'] ? ' hidden' : ''; ?>>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            <span data-lw-error-text><?php echo esc_html($a['error']); ?></span>
        </p>
    </div>
    <?php
}

/** Hộp thông báo chung trong form (không phải popup). */
function lw_auth_render_alerts(array $notices): void
{
    ?>
    <div class="lw-alert lw-alert--error" role="alert" data-lw-alert<?php echo empty($notices['error']) ? ' hidden' : ''; ?>>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
        <div data-lw-alert-text><?php echo wp_kses_post(implode('<br>', $notices['error'])); ?></div>
    </div>
    <?php if (!empty($notices['success'])) : ?>
        <div class="lw-alert lw-alert--success" role="status">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg>
            <div><?php echo wp_kses_post(implode('<br>', $notices['success'])); ?></div>
        </div>
    <?php endif;
}

<?php
/**
 * Luxury Watches – đăng nhập / đăng ký bằng Google (Google Identity Services).
 *
 * Luồng: nút Google chính thức trả về ID token (JWT) → form ẩn gửi về trang Tài khoản
 * kèm WordPress nonce → server xác minh token với Google (chữ ký, hạn dùng, aud, iss,
 * email đã xác minh) → khớp tài khoản theo Google ID / email, hoặc tạo khách hàng
 * WooCommerce mới → đặt cookie đăng nhập WordPress.
 *
 * Cấu hình Client ID (không phải bí mật, nhưng tách khỏi code) theo thứ tự:
 *   define('LW_GOOGLE_CLIENT_ID', '...') trong wp-config.php
 *   → biến môi trường GOOGLE_CLIENT_ID
 *   → dòng GOOGLE_CLIENT_ID=... trong file .env ở thư mục gốc dự án.
 * Chưa cấu hình thì nút Google không hiển thị.
 *
 * @package Luxury_Watches_Atelier
 */

if (!defined('ABSPATH')) {
    exit;
}

/* -------------------------------------------------------------------------
 * Cấu hình
 * ---------------------------------------------------------------------- */

/** Đọc một biến từ file .env của dự án (thư mục chứa wordpress/). */
function lw_env_value(string $name): string
{
    static $values = null;
    if (null === $values) {
        $values = [];
        $path   = dirname(untrailingslashit(ABSPATH)) . DIRECTORY_SEPARATOR . '.env';
        if (is_readable($path) && filesize($path) < 65536) {
            foreach ((array) file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim((string) $line);
                if ('' === $line || '#' === $line[0]) {
                    continue;
                }
                if (0 === strpos($line, 'export ')) {
                    $line = trim(substr($line, 7));
                }
                if (!preg_match('/\A([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)\z/', $line, $m)) {
                    continue;
                }
                $value = $m[2];
                if (preg_match('/\A"(.*)"\z/', $value, $q) || preg_match("/\\A'(.*)'\\z/", $value, $q)) {
                    $value = $q[1];
                } else {
                    $value = trim((string) preg_replace('/\s+#.*\z/', '', $value));
                }
                $values[$m[1]] = $value;
            }
        }
    }
    return isset($values[$name]) ? (string) $values[$name] : '';
}

function lw_google_client_id(): string
{
    if (defined('LW_GOOGLE_CLIENT_ID') && is_string(LW_GOOGLE_CLIENT_ID) && '' !== LW_GOOGLE_CLIENT_ID) {
        $id = LW_GOOGLE_CLIENT_ID;
    } elseif (is_string(getenv('GOOGLE_CLIENT_ID')) && '' !== getenv('GOOGLE_CLIENT_ID')) {
        $id = (string) getenv('GOOGLE_CLIENT_ID');
    } else {
        $id = lw_env_value('GOOGLE_CLIENT_ID');
    }
    $id = trim($id);
    return preg_match('/^[\w.-]+\.apps\.googleusercontent\.com$/', $id) ? $id : '';
}

function lw_google_enabled(): bool
{
    return '' !== lw_google_client_id();
}

/* -------------------------------------------------------------------------
 * Tài nguyên
 * ---------------------------------------------------------------------- */

add_action('wp_enqueue_scripts', function () {
    if (!function_exists('lw_auth_is_portal_view') || !lw_auth_is_portal_view() || !lw_google_enabled() || is_user_logged_in()) {
        return;
    }
    wp_enqueue_script('lw-google-gsi', 'https://accounts.google.com/gsi/client', [], null, ['in_footer' => true, 'strategy' => 'async']);
}, 1000);

/* -------------------------------------------------------------------------
 * Xác minh ID token
 * ---------------------------------------------------------------------- */

/**
 * Xác minh ID token qua endpoint tokeninfo của Google (Google kiểm tra chữ ký và hạn dùng),
 * sau đó tự kiểm tra aud / iss / exp / email_verified.
 *
 * @return array|WP_Error Claims của token.
 */
function lw_google_verify_id_token(string $token)
{
    $client_id = lw_google_client_id();
    if ('' === $client_id) {
        return new WP_Error('lw_google_disabled', 'Đăng nhập bằng Google chưa được cấu hình.');
    }
    if (!preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $token) || strlen($token) > 4096) {
        return new WP_Error('lw_google_malformed', 'Phản hồi từ Google không hợp lệ.');
    }

    $response = wp_remote_get(
        add_query_arg('id_token', rawurlencode($token), 'https://oauth2.googleapis.com/tokeninfo'),
        ['timeout' => 10]
    );
    if (is_wp_error($response)) {
        return new WP_Error('lw_google_network', 'Không thể kết nối tới Google. Vui lòng thử lại.');
    }
    if (200 !== (int) wp_remote_retrieve_response_code($response)) {
        return new WP_Error('lw_google_invalid', 'Phiên đăng nhập Google không hợp lệ hoặc đã hết hạn. Vui lòng thử lại.');
    }

    $claims = json_decode((string) wp_remote_retrieve_body($response), true);
    if (!is_array($claims)) {
        return new WP_Error('lw_google_invalid', 'Phản hồi từ Google không hợp lệ.');
    }

    $issuer_ok   = in_array($claims['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true);
    $audience_ok = hash_equals($client_id, (string) ($claims['aud'] ?? ''));
    $fresh       = (int) ($claims['exp'] ?? 0) > time();
    $verified    = in_array($claims['email_verified'] ?? false, [true, 'true'], true);
    $email       = sanitize_email((string) ($claims['email'] ?? ''));
    $sub         = preg_replace('/\D/', '', (string) ($claims['sub'] ?? ''));

    if (!$issuer_ok || !$audience_ok || !$fresh || '' === $sub) {
        return new WP_Error('lw_google_invalid', 'Phiên đăng nhập Google không hợp lệ hoặc đã hết hạn. Vui lòng thử lại.');
    }
    if (!$verified || !is_email($email)) {
        return new WP_Error('lw_google_unverified', 'Email Google của bạn chưa được xác minh.');
    }

    $claims['email'] = $email;
    $claims['sub']   = $sub;
    return $claims;
}

/* -------------------------------------------------------------------------
 * Khớp hoặc tạo tài khoản
 * ---------------------------------------------------------------------- */

/** @return WP_User|WP_Error */
function lw_google_resolve_user(array $claims)
{
    $sub   = (string) $claims['sub'];
    $email = (string) $claims['email'];

    // 1. Đã liên kết Google trước đó.
    $linked = get_users(['meta_key' => 'lw_google_sub', 'meta_value' => $sub, 'number' => 1]);
    if (!empty($linked)) {
        return $linked[0];
    }

    // 2. Đã có tài khoản cùng email (Google đã xác minh quyền sở hữu email) → liên kết.
    $existing = get_user_by('email', $email);
    if ($existing instanceof WP_User) {
        if (user_can($existing, 'manage_options') || user_can($existing, 'edit_others_posts')) {
            return new WP_Error('lw_google_privileged', 'Tài khoản quản trị cần đăng nhập bằng mật khẩu.');
        }
        update_user_meta($existing->ID, 'lw_google_sub', $sub);
        return $existing;
    }

    // 3. Tài khoản mới.
    if (!function_exists('lw_auth_registration_enabled') || !lw_auth_registration_enabled() || !function_exists('wc_create_new_customer')) {
        return new WP_Error('lw_google_no_account', 'Chưa có tài khoản nào dùng email này.');
    }

    $first = sanitize_text_field((string) ($claims['given_name'] ?? ''));
    $last  = sanitize_text_field((string) ($claims['family_name'] ?? ''));
    $name  = sanitize_text_field((string) ($claims['name'] ?? trim($first . ' ' . $last)));

    $username = function_exists('wc_create_new_customer_username')
        ? wc_create_new_customer_username($email, ['first_name' => $first, 'last_name' => $last])
        : sanitize_user(current(explode('@', $email)), true);
    // Khi WooCommerce tự sinh mật khẩu, khách nhận email đặt mật khẩu nếu muốn đăng nhập không qua Google.
    $password = 'yes' === get_option('woocommerce_registration_generate_password') ? '' : wp_generate_password(32, true, true);

    $user_id = wc_create_new_customer($email, $username, $password, [
        'first_name'   => $first,
        'last_name'    => $last,
        'display_name' => '' !== $name ? $name : $username,
    ]);
    if (is_wp_error($user_id)) {
        return new WP_Error('lw_google_create_failed', wp_strip_all_tags($user_id->get_error_message()));
    }

    update_user_meta($user_id, 'lw_google_sub', $sub);
    update_user_meta($user_id, 'lw_registered_via', 'google');
    if ('' !== $first) {
        update_user_meta($user_id, 'billing_first_name', $first);
    }
    if ('' !== $last) {
        update_user_meta($user_id, 'billing_last_name', $last);
    }

    return get_user_by('id', $user_id);
}

/* -------------------------------------------------------------------------
 * Xử lý form ẩn (chạy cùng lúc với WC_Form_Handler, trước khi render trang)
 * ---------------------------------------------------------------------- */

add_action('wp_loaded', function () {
    // phpcs:ignore WordPress.Security.NonceVerification -- nonce được kiểm tra ngay bên dưới.
    if (!isset($_POST['lw_google_credential']) || is_user_logged_in()) {
        return;
    }

    $nonce = isset($_POST['lw-google-nonce']) ? sanitize_text_field(wp_unslash($_POST['lw-google-nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'lw-google-auth')) {
        lw_auth_add_error('general', 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.');
        return;
    }

    $token  = sanitize_text_field(wp_unslash((string) $_POST['lw_google_credential']));
    $claims = lw_google_verify_id_token($token);
    if (is_wp_error($claims)) {
        lw_auth_add_error('general', $claims->get_error_message());
        return;
    }

    $user = lw_google_resolve_user($claims);
    if (is_wp_error($user)) {
        lw_auth_add_error('general', $user->get_error_message());
        return;
    }

    if (function_exists('wc_set_customer_auth_cookie')) {
        wc_set_customer_auth_cookie($user->ID);
    } else {
        wp_set_auth_cookie($user->ID, true);
    }
    do_action('wp_login', $user->user_login, $user);

    $fallback = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/');
    $redirect = function_exists('lw_auth_redirect_target') ? lw_auth_redirect_target() : '';
    $redirect = apply_filters('woocommerce_login_redirect', '' !== $redirect ? $redirect : $fallback, $user);
    wp_safe_redirect(wp_validate_redirect($redirect, $fallback));
    exit;
}, 20);

/* -------------------------------------------------------------------------
 * Giao diện
 * ---------------------------------------------------------------------- */

/**
 * Khối "hoặc tiếp tục với Google" kèm form ẩn mang ID token về server.
 *
 * @param string $context 'signin' hoặc 'signup'.
 */
function lw_google_render_block(string $context = 'signin'): void
{
    if (!lw_google_enabled()) {
        return;
    }
    $redirect = function_exists('lw_auth_redirect_target') ? lw_auth_redirect_target() : '';
    ?>
    <div class="lw-social" data-lw-google data-context="<?php echo esc_attr('signup' === $context ? 'signup' : 'signin'); ?>">
        <div class="lw-divider" role="separator"><span>hoặc</span></div>
        <div class="lw-google-slot">
            <div class="lw-google-button" data-lw-google-button aria-label="<?php echo esc_attr('signup' === $context ? 'Đăng ký bằng Google' : 'Đăng nhập bằng Google'); ?>"></div>
            <noscript><p class="lw-form__note">Bật JavaScript để dùng tài khoản Google.</p></noscript>
        </div>
        <form method="post" class="lw-google-form" data-lw-auth-form="google" data-lw-google-form
              data-loading-text="Đang xác thực với Google…"
              data-success-text="Xác thực thành công.">
            <input type="hidden" name="lw_google_credential" value="" data-lw-google-credential />
            <?php wp_nonce_field('lw-google-auth', 'lw-google-nonce', false); ?>
            <?php if ('' !== $redirect) : ?>
                <input type="hidden" name="redirect" value="<?php echo esc_attr($redirect); ?>" />
            <?php endif; ?>
            <div class="lw-alert lw-alert--error" role="alert" data-lw-alert hidden>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                <div data-lw-alert-text></div>
            </div>
            <p class="lw-form__status" role="status" aria-live="polite" data-lw-status></p>
        </form>
    </div>
    <?php
}

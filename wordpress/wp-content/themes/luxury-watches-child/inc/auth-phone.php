<?php
/**
 * Luxury Watches – đăng ký / đăng nhập bằng số điện thoại.
 *
 * Tài khoản vẫn là user WordPress (vai trò customer của WooCommerce):
 * user_login = số điện thoại đã chuẩn hóa (0xxxxxxxxx), billing_phone = cùng số.
 * Chưa có xác minh OTP (theo quyết định hiện tại) – có thể bổ sung sau.
 *
 * @package Luxury_Watches_Atelier
 */

if (!defined('ABSPATH')) {
    exit;
}

/* -------------------------------------------------------------------------
 * Chuẩn hóa số điện thoại Việt Nam
 * ---------------------------------------------------------------------- */

/**
 * Trả về số dạng 0xxxxxxxxx (di động VN: đầu 03/05/07/08/09) hoặc '' nếu không hợp lệ.
 * Chấp nhận khoảng trắng, dấu chấm, gạch ngang, ngoặc và tiền tố +84 / 84.
 */
function lw_phone_normalize(string $raw): string
{
    $digits = preg_replace('/[\s.\-()]/', '', trim($raw));
    if (preg_match('/^(?:\+?84)(\d{9})$/', (string) $digits, $m)) {
        $digits = '0' . $m[1];
    }
    return preg_match('/^0[35789]\d{8}$/', (string) $digits) ? (string) $digits : '';
}

/** Chuỗi nhập trông giống số điện thoại (không phải email / tên đăng nhập). */
function lw_phone_looks_like(string $raw): bool
{
    return (bool) preg_match('/^\+?[\d\s.\-()]{9,18}$/', trim($raw));
}

/** Các cách một số có thể đã được lưu trong billing_phone. */
function lw_phone_variants(string $phone): array
{
    $tail = substr($phone, 1);
    return array_values(array_unique([
        $phone,
        '+84' . $tail,
        '84' . $tail,
        substr($phone, 0, 4) . ' ' . substr($phone, 4, 3) . ' ' . substr($phone, 7),
    ]));
}

/**
 * Tìm user theo số điện thoại: trước tiên theo user_login, sau đó theo billing_phone.
 * Chỉ trả về khi khớp đúng một tài khoản để tránh đăng nhập nhầm.
 */
function lw_phone_find_user(string $phone): ?WP_User
{
    if ('' === $phone) {
        return null;
    }
    $user = get_user_by('login', $phone);
    if ($user instanceof WP_User) {
        return $user;
    }
    $matches = get_users([
        'meta_key'     => 'billing_phone',
        'meta_value'   => lw_phone_variants($phone),
        'meta_compare' => 'IN',
        'number'       => 2,
        'fields'       => 'all',
    ]);
    return 1 === count($matches) ? $matches[0] : null;
}

/* -------------------------------------------------------------------------
 * Đăng nhập bằng số điện thoại (qua form đăng nhập WooCommerce sẵn có)
 * ---------------------------------------------------------------------- */

add_filter('woocommerce_process_login_errors', function ($errors, $username) {
    $username = sanitize_text_field((string) $username);
    if ('' !== $username && false === strpos($username, '@') && lw_phone_looks_like($username)
        && '' === lw_phone_normalize($username) && !get_user_by('login', $username)) {
        $msg = 'Số điện thoại chưa đúng định dạng (ví dụ: 0912 345 678).';
        lw_auth_add_error('username', $msg);
        $errors->add('lw_invalid_phone', $msg);
    }
    return $errors;
}, 11, 2);

add_filter('woocommerce_login_credentials', function ($creds) {
    $login = (string) $creds['user_login'];
    if (false !== strpos($login, '@') || !lw_phone_looks_like($login)) {
        return $creds;
    }
    $user = lw_phone_find_user(lw_phone_normalize($login));
    if ($user) {
        $creds['user_login'] = $user->user_login;
    }
    return $creds;
}, 20);

/* -------------------------------------------------------------------------
 * Đăng ký bằng số điện thoại + mật khẩu
 * ---------------------------------------------------------------------- */

function lw_phone_register_requested(): bool
{
    // phpcs:ignore WordPress.Security.NonceVerification -- chỉ kiểm tra có gửi form hay không.
    return isset($_POST['lw_phone_register']);
}

add_action('wp_loaded', function () {
    if (!lw_phone_register_requested() || is_user_logged_in() || !function_exists('lw_auth_registration_enabled') || !lw_auth_registration_enabled()) {
        return;
    }

    $nonce = isset($_POST['lw-phone-register-nonce']) ? sanitize_text_field(wp_unslash($_POST['lw-phone-register-nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'lw-phone-register')) {
        lw_auth_add_error('general', 'Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.');
        return;
    }

    $raw      = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    // Mật khẩu không sanitize: mọi ký tự đều hợp lệ, WordPress sẽ băm.
    $password = isset($_POST['phone_password']) && is_string($_POST['phone_password']) ? wp_unslash($_POST['phone_password']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $phone    = lw_phone_normalize($raw);

    if ('' === $raw) {
        lw_auth_add_error('reg_phone', 'Vui lòng nhập số điện thoại.');
    } elseif ('' === $phone) {
        lw_auth_add_error('reg_phone', 'Số điện thoại chưa đúng định dạng (ví dụ: 0912 345 678).');
    } elseif (lw_phone_find_user($phone) || username_exists($phone)) {
        lw_auth_add_error('reg_phone', 'Số điện thoại này đã được đăng ký. Vui lòng đăng nhập.');
    }

    if ('' === $password) {
        lw_auth_add_error('reg_phone_password', 'Vui lòng tạo mật khẩu.');
    } elseif (strlen($password) < 8) {
        lw_auth_add_error('reg_phone_password', 'Mật khẩu cần tối thiểu 8 ký tự.');
    } elseif (strlen($password) > 128) {
        lw_auth_add_error('reg_phone_password', 'Mật khẩu tối đa 128 ký tự.');
    }

    if (lw_auth_has_field_errors()) {
        return;
    }

    $userdata = [
        'user_login'   => $phone,
        'user_pass'    => $password,
        'user_email'   => '',
        'display_name' => $phone,
        'nickname'     => $phone,
        'role'         => 'customer',
    ];
    $user_id = wp_insert_user(wp_slash($userdata));
    if (is_wp_error($user_id)) {
        lw_auth_add_error('general', 'Không thể tạo tài khoản lúc này. Vui lòng thử lại.');
        return;
    }

    update_user_meta($user_id, 'billing_phone', $phone);
    update_user_meta($user_id, 'lw_registered_via', 'phone');

    // Cho các plugin/tính năng WooCommerce biết có khách hàng mới.
    do_action('woocommerce_created_customer', $user_id, $userdata, false);

    if (function_exists('wc_set_customer_auth_cookie')) {
        wc_set_customer_auth_cookie($user_id);
    } else {
        wp_set_auth_cookie($user_id, true);
    }
    $user = get_user_by('id', $user_id);
    do_action('wp_login', $user->user_login, $user);

    $redirect = function_exists('lw_auth_redirect_target') ? lw_auth_redirect_target() : '';
    $fallback = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/');
    $redirect = apply_filters('woocommerce_registration_redirect', '' !== $redirect ? $redirect : $fallback);
    wp_safe_redirect(wp_validate_redirect($redirect, $fallback));
    exit;
}, 20);

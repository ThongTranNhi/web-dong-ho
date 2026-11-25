<?php
/**
 * Luxury Watches – phương thức thanh toán.
 *
 * Shop dùng hai cổng: SePay (chuyển khoản quét mã VietQR, tự xác nhận) và COD.
 * Cấu hình từng cổng nằm trong WooCommerce → Cài đặt → Thanh toán.
 *
 * @package Luxury_Watches_Atelier
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SePay đã có tài khoản nhận tiền chưa: kết nối tài khoản SePay (OAuth, chọn tài khoản
 * ngân hàng) hoặc điền tay ngân hàng + số tài khoản.
 */
function lw_sepay_is_configured(): bool
{
    $settings = get_option('woocommerce_sepay_settings', []);
    if (!is_array($settings)) {
        return false;
    }
    if (!empty($settings['bank_account'])) {
        return true;
    }
    return !empty($settings['bank_select']) && !empty($settings['bank_account_number']) && !empty($settings['bank_account_holder']);
}

/**
 * Chưa có tài khoản nhận tiền thì ẩn SePay ở trang thanh toán (cả Checkout block lẫn
 * checkout cổ điển), để khách không nhận mã QR trống. Trang quản trị không bị ảnh hưởng.
 */
add_filter('woocommerce_available_payment_gateways', function ($gateways) {
    if (is_admin() && !wp_doing_ajax()) {
        return $gateways;
    }
    if (isset($gateways['sepay']) && !lw_sepay_is_configured()) {
        unset($gateways['sepay']);
    }
    return $gateways;
});

/** Nhắc quản trị viên điền tài khoản SePay khi cổng đã bật nhưng chưa cấu hình. */
add_action('admin_notices', function () {
    if (!current_user_can('manage_woocommerce') || lw_sepay_is_configured()) {
        return;
    }
    $settings = get_option('woocommerce_sepay_settings', []);
    if (!is_array($settings) || ($settings['enabled'] ?? 'no') !== 'yes') {
        return;
    }
    $url = admin_url('admin.php?page=wc-settings&tab=checkout&section=sepay');
    printf(
        '<div class="notice notice-warning"><p><strong>Thanh toán QR (SePay) đang bị ẩn ở trang thanh toán</strong> vì chưa có tài khoản nhận tiền. <a href="%s">Kết nối SePay hoặc điền ngân hàng, số tài khoản, chủ tài khoản</a>.</p></div>',
        esc_url($url)
    );
});

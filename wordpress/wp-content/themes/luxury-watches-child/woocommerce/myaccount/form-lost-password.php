<?php
/**
 * Form quên mật khẩu – Luxury Watches.
 *
 * Ghi đè woocommerce/templates/myaccount/form-lost-password.php, giữ nguyên
 * tên trường, nonce và hook để WC_Form_Handler::process_lost_password xử lý.
 *
 * @package Luxury_Watches_Atelier
 * @version 9.2.0
 */

defined('ABSPATH') || exit;

$lw_notices = function_exists('lw_auth_take_notices') ? lw_auth_take_notices() : ['error' => [], 'success' => []];

do_action('woocommerce_before_lost_password_form');
?>

<header class="lw-auth__head">
    <h1 class="lw-auth__title">Khôi phục mật khẩu</h1>
    <p class="lw-auth__subtitle">Nhập email hoặc tên đăng nhập. Chúng tôi sẽ gửi đường dẫn đặt lại mật khẩu tới email của bạn.</p>
</header>

<form method="post" class="woocommerce-ResetPassword lost_reset_password lw-form" novalidate
      data-lw-auth-form="lost-password"
      data-lw-native-submit
      data-loading-text="Đang gửi…">

    <?php lw_auth_render_alerts($lw_notices); ?>

    <?php
    lw_auth_field([
        'id'           => 'user_login',
        'name'         => 'user_login',
        'label'        => 'Email hoặc tên đăng nhập',
        'autocomplete' => 'username',
        'inputmode'    => 'email',
    ]);
    ?>

    <?php do_action('woocommerce_lostpassword_form'); ?>

    <input type="hidden" name="wc_reset_password" value="true" />
    <?php wp_nonce_field('lost_password', 'woocommerce-lost-password-nonce'); ?>

    <button type="submit" class="lw-button" value="Đặt lại mật khẩu" data-lw-submit>
        <span class="lw-button__spinner" aria-hidden="true"></span>
        <span class="lw-button__label" data-lw-submit-label>Gửi đường dẫn đặt lại</span>
    </button>
    <p class="lw-form__status" role="status" aria-live="polite" data-lw-status></p>
</form>

<p class="lw-auth__switch">Đã nhớ mật khẩu? <a class="lw-link lw-link--strong" href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>">Đăng nhập</a></p>

<a class="lw-auth__back" href="<?php echo esc_url(home_url('/')); ?>">
    <span aria-hidden="true">←</span> Quay về trang chủ
</a>

<?php
do_action('woocommerce_after_lost_password_form');

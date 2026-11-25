<?php
/**
 * Form đăng nhập / đăng ký – Luxury Watches.
 *
 * Ghi đè woocommerce/templates/myaccount/form-login.php. Form đăng nhập và đăng ký
 * bằng email giữ nguyên tên trường, nonce và hook của WooCommerce để WC_Form_Handler
 * xử lý. Đăng ký bằng số điện thoại (inc/auth-phone.php) và Google (inc/auth-google.php)
 * tạo user WordPress chuẩn.
 *
 * @package Luxury_Watches_Atelier
 * @version 9.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$lw_register   = function_exists('lw_auth_is_register_mode') && lw_auth_is_register_mode();
$lw_can_signup = function_exists('lw_auth_registration_enabled') && lw_auth_registration_enabled();
$lw_redirect   = function_exists('lw_auth_redirect_target') ? lw_auth_redirect_target() : '';
$lw_notices    = function_exists('lw_auth_take_notices') ? lw_auth_take_notices() : ['error' => [], 'success' => []];
$lw_has_google = function_exists('lw_google_render_block');

// Notice đã được lấy ra ở trên; hook mặc định sẽ không in trùng lặp.
do_action('woocommerce_before_customer_login_form');
?>

<?php if (!$lw_register) : ?>

    <header class="lw-auth__head">
        <h1 class="lw-auth__title">Chào mừng trở lại</h1>
        <p class="lw-auth__subtitle">Đăng nhập để tiếp tục hành trình cùng Luxury Watches.</p>
    </header>

    <form class="woocommerce-form woocommerce-form-login login lw-form" method="post" novalidate
          data-lw-auth-form="login"
          data-loading-text="Đang đăng nhập…"
          data-success-text="Đăng nhập thành công">

        <?php lw_auth_render_alerts($lw_notices); ?>

        <?php do_action('woocommerce_login_form_start'); ?>

        <?php
        // phpcs:ignore WordPress.Security.NonceVerification -- chỉ hiển thị lại giá trị đã nhập.
        $lw_username = (!empty($_POST['username']) && is_string($_POST['username'])) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';
        lw_auth_field([
            'id'           => 'username',
            'name'         => 'username',
            'label'        => 'Email hoặc số điện thoại',
            'autocomplete' => 'username',
            'value'        => $lw_username,
            'error'        => lw_auth_get_error('username'),
            'invalid'      => '' !== lw_auth_get_error('credentials'),
        ]);
        lw_auth_field([
            'id'           => 'password',
            'name'         => 'password',
            'label'        => 'Mật khẩu',
            'type'         => 'password',
            'autocomplete' => 'current-password',
            'error'        => lw_auth_get_error('password'),
        ]);
        ?>

        <?php do_action('woocommerce_login_form'); ?>

        <div class="lw-form__row">
            <label class="lw-check woocommerce-form-login__rememberme" for="rememberme">
                <input class="lw-check__input woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" <?php checked(!empty($_POST['rememberme'])); // phpcs:ignore WordPress.Security.NonceVerification ?> />
                <span class="lw-check__box" aria-hidden="true"></span>
                <span class="lw-check__label">Ghi nhớ đăng nhập</span>
            </label>
            <a class="lw-link" href="<?php echo esc_url(wp_lostpassword_url()); ?>">Quên mật khẩu?</a>
        </div>

        <?php wp_nonce_field('woocommerce-login', 'woocommerce-login-nonce'); ?>
        <?php if ('' !== $lw_redirect) : ?>
            <input type="hidden" name="redirect" value="<?php echo esc_attr($lw_redirect); ?>" />
        <?php endif; ?>

        <button type="submit" class="lw-button woocommerce-form-login__submit" name="login" value="Đăng nhập" data-lw-submit>
            <span class="lw-button__spinner" aria-hidden="true"></span>
            <svg class="lw-button__check" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
            <span class="lw-button__label" data-lw-submit-label>Đăng nhập</span>
        </button>
        <p class="lw-form__status" role="status" aria-live="polite" data-lw-status></p>

        <?php do_action('woocommerce_login_form_end'); ?>
    </form>

    <?php if ($lw_has_google) { lw_google_render_block('signin'); } ?>

    <?php if ($lw_can_signup) : ?>
        <p class="lw-auth__switch">Bạn chưa có tài khoản? <a class="lw-link lw-link--strong" href="<?php echo esc_url(lw_auth_switch_url('register')); ?>">Đăng ký ngay</a></p>
    <?php endif; ?>

<?php else : ?>

    <?php
    $lw_method       = lw_auth_register_method();
    $lw_phone_active = 'phone' === $lw_method;
    ?>

    <header class="lw-auth__head">
        <h1 class="lw-auth__title">Tạo tài khoản</h1>
        <p class="lw-auth__subtitle">Trở thành thành viên để theo dõi đơn hàng và tham gia các phiên đấu giá độc quyền.</p>
    </header>

    <div class="lw-tabs" role="tablist" aria-label="Cách đăng ký" data-lw-tabs>
        <a class="lw-tabs__tab" role="tab" id="lw-tab-phone" href="<?php echo esc_url(lw_auth_switch_url('register', ['method' => 'phone'])); ?>"
           aria-controls="lw-panel-phone" aria-selected="<?php echo $lw_phone_active ? 'true' : 'false'; ?>" tabindex="<?php echo $lw_phone_active ? '0' : '-1'; ?>" data-lw-tab="phone">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M11 18h2"/></svg>
            Số điện thoại
        </a>
        <a class="lw-tabs__tab" role="tab" id="lw-tab-email" href="<?php echo esc_url(lw_auth_switch_url('register', ['method' => 'email'])); ?>"
           aria-controls="lw-panel-email" aria-selected="<?php echo $lw_phone_active ? 'false' : 'true'; ?>" tabindex="<?php echo $lw_phone_active ? '-1' : '0'; ?>" data-lw-tab="email">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2.5"/><path d="m3 6 9 7 9-7"/></svg>
            Email
        </a>
    </div>

    <!-- Đăng ký bằng số điện thoại + mật khẩu -->
    <div class="lw-tabs__panel" role="tabpanel" id="lw-panel-phone" aria-labelledby="lw-tab-phone"<?php echo $lw_phone_active ? '' : ' hidden'; ?>>
        <form method="post" class="lw-form" novalidate
              data-lw-auth-form="register-phone"
              data-loading-text="Đang tạo tài khoản…"
              data-success-text="Tạo tài khoản thành công">

            <?php lw_auth_render_alerts($lw_phone_active ? $lw_notices : ['error' => [], 'success' => []]); ?>

            <?php
            // phpcs:ignore WordPress.Security.NonceVerification -- chỉ hiển thị lại giá trị đã nhập.
            $lw_phone_value = (!empty($_POST['phone']) && is_string($_POST['phone'])) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
            lw_auth_field([
                'id'           => 'reg_phone',
                'name'         => 'phone',
                'label'        => 'Số điện thoại',
                'type'         => 'tel',
                'autocomplete' => 'tel-national',
                'inputmode'    => 'tel',
                'value'        => $lw_phone_value,
                'error'        => lw_auth_get_error('reg_phone'),
            ]);
            lw_auth_field([
                'id'           => 'reg_phone_password',
                'name'         => 'phone_password',
                'label'        => 'Mật khẩu',
                'type'         => 'password',
                'autocomplete' => 'new-password',
                'hint'         => 'Tối thiểu 8 ký tự.',
                'error'        => lw_auth_get_error('reg_phone_password'),
            ]);
            ?>

            <p class="lw-form__note">Bạn có thể bổ sung email trong mục Tài khoản để nhận hóa đơn và khôi phục mật khẩu.</p>
            <div class="lw-form__legal"><?php if (function_exists('wc_registration_privacy_policy_text')) { wc_registration_privacy_policy_text(); } ?></div>

            <?php wp_nonce_field('lw-phone-register', 'lw-phone-register-nonce'); ?>
            <?php if ('' !== $lw_redirect) : ?>
                <input type="hidden" name="redirect" value="<?php echo esc_attr($lw_redirect); ?>" />
            <?php endif; ?>

            <button type="submit" class="lw-button" name="lw_phone_register" value="1" data-lw-submit>
                <span class="lw-button__spinner" aria-hidden="true"></span>
                <svg class="lw-button__check" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
                <span class="lw-button__label" data-lw-submit-label>Tạo tài khoản</span>
            </button>
            <p class="lw-form__status" role="status" aria-live="polite" data-lw-status></p>
        </form>
    </div>

    <!-- Đăng ký bằng email (WooCommerce) -->
    <div class="lw-tabs__panel" role="tabpanel" id="lw-panel-email" aria-labelledby="lw-tab-email"<?php echo $lw_phone_active ? ' hidden' : ''; ?>>
        <form method="post" class="woocommerce-form woocommerce-form-register register lw-form" novalidate
              <?php do_action('woocommerce_register_form_tag'); ?>
              data-lw-auth-form="register"
              data-loading-text="Đang tạo tài khoản…"
              data-success-text="Tạo tài khoản thành công">

            <?php lw_auth_render_alerts($lw_phone_active ? ['error' => [], 'success' => []] : $lw_notices); ?>

            <?php do_action('woocommerce_register_form_start'); ?>

            <?php
            // phpcs:disable WordPress.Security.NonceVerification -- chỉ hiển thị lại giá trị đã nhập.
            if ('no' === get_option('woocommerce_registration_generate_username')) {
                lw_auth_field([
                    'id'           => 'reg_username',
                    'name'         => 'username',
                    'label'        => 'Tên đăng nhập',
                    'autocomplete' => 'username',
                    'value'        => (!empty($_POST['username']) && is_string($_POST['username'])) ? sanitize_user(wp_unslash($_POST['username'])) : '',
                    'error'        => lw_auth_get_error('reg_username'),
                ]);
            }
            lw_auth_field([
                'id'           => 'reg_email',
                'name'         => 'email',
                'label'        => 'Địa chỉ email',
                'type'         => 'email',
                'autocomplete' => 'email',
                'inputmode'    => 'email',
                'value'        => (!empty($_POST['email']) && is_string($_POST['email'])) ? sanitize_email(wp_unslash($_POST['email'])) : '',
                'error'        => lw_auth_get_error('reg_email'),
            ]);
            if ('no' === get_option('woocommerce_registration_generate_password')) {
                lw_auth_field([
                    'id'           => 'reg_password',
                    'name'         => 'password',
                    'label'        => 'Mật khẩu',
                    'type'         => 'password',
                    'autocomplete' => 'new-password',
                    'hint'         => 'Tối thiểu 8 ký tự.',
                    'error'        => lw_auth_get_error('reg_password'),
                ]);
            } else {
                echo '<p class="lw-form__note">Chúng tôi sẽ gửi đường dẫn tạo mật khẩu tới email của bạn.</p>';
            }
            // phpcs:enable
            ?>

            <div class="lw-form__legal"><?php do_action('woocommerce_register_form'); ?></div>

            <?php wp_nonce_field('woocommerce-register', 'woocommerce-register-nonce'); ?>
            <?php if ('' !== $lw_redirect) : ?>
                <input type="hidden" name="redirect" value="<?php echo esc_attr($lw_redirect); ?>" />
            <?php endif; ?>

            <button type="submit" class="lw-button woocommerce-form-register__submit" name="register" value="Đăng ký" data-lw-submit>
                <span class="lw-button__spinner" aria-hidden="true"></span>
                <svg class="lw-button__check" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
                <span class="lw-button__label" data-lw-submit-label>Tạo tài khoản</span>
            </button>
            <p class="lw-form__status" role="status" aria-live="polite" data-lw-status></p>

            <?php do_action('woocommerce_register_form_end'); ?>
        </form>
    </div>

    <?php if ($lw_has_google) { lw_google_render_block('signup'); } ?>

    <p class="lw-auth__switch">Bạn đã có tài khoản? <a class="lw-link lw-link--strong" href="<?php echo esc_url(lw_auth_switch_url('login')); ?>">Đăng nhập</a></p>

<?php endif; ?>

<a class="lw-auth__back" href="<?php echo esc_url(home_url('/')); ?>">
    <span aria-hidden="true">←</span> Quay về trang chủ
</a>

<?php do_action('woocommerce_after_customer_login_form'); ?>

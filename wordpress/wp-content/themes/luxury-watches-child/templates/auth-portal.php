<?php
/**
 * Khung toàn màn hình cho trang Tài khoản khi chưa đăng nhập
 * (đăng nhập, đăng ký, quên mật khẩu). Nội dung form vẫn do shortcode
 * [woocommerce_my_account] in ra qua các template trong woocommerce/myaccount/.
 *
 * @package Luxury_Watches_Atelier
 */

if (!defined('ABSPATH')) {
    exit;
}

$lw_home    = home_url('/');
$lw_hero    = get_stylesheet_directory_uri() . '/assets/images/hero-atelier.webp';
$lw_is_lost = function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('lost-password');
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="theme-color" content="#13232F">
    <link rel="preload" as="image" href="<?php echo esc_url($lw_hero); ?>" fetchpriority="high">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="lw-auth<?php echo $lw_is_lost ? ' lw-auth--lost' : ''; ?>">
    <aside class="lw-auth__visual" aria-label="Luxury Watches">
        <div class="lw-auth__media" aria-hidden="true">
            <img src="<?php echo esc_url($lw_hero); ?>" alt="" width="1600" height="900" decoding="async" fetchpriority="high">
        </div>

        <a class="lw-auth__brand" href="<?php echo esc_url($lw_home); ?>" aria-label="Luxury Watches – về trang chủ">
            <span class="lw-auth__brand-mark" aria-hidden="true">
                <svg width="30" height="30" viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="14.5" stroke="currentColor" stroke-width="1.2"/><circle cx="16" cy="16" r="10.5" stroke="currentColor" stroke-width=".7" opacity=".55"/><path d="M16 8.5V16l4.6 3.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <span class="lw-auth__brand-text">
                <span class="lw-auth__brand-name">Luxury Watches</span>
                <span class="lw-auth__brand-tag">Maison d’horlogerie</span>
            </span>
        </a>

        <div class="lw-auth__story">
            <p class="lw-auth__eyebrow">Thành viên Luxury Watches</p>
            <h2 class="lw-auth__headline">Tinh hoa trên từng khoảnh khắc</h2>
            <p class="lw-auth__lede">Khám phá bộ sưu tập đồng hồ được tuyển chọn và những phiên đấu giá độc quyền.</p>
            <ul class="lw-auth__benefits">
                <li>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
                    Sản phẩm chính hãng
                </li>
                <li>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Thanh toán an toàn
                </li>
                <li>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m14 13-7.5 7.5a2 2 0 0 1-2.8-2.8L11 10.2"/><path d="m16 16 6-6"/><path d="m8 8 6-6"/><path d="m9 7 8 8"/><path d="m21 11-8-8"/></svg>
                    Đấu giá minh bạch
                </li>
            </ul>
        </div>
    </aside>

    <main class="lw-auth__panel" id="main">
        <div class="lw-auth__card" data-lw-auth-card>
            <?php
            while (have_posts()) {
                the_post();
                the_content();
            }
            ?>
        </div>
        <p class="lw-auth__legal">© <?php echo esc_html(wp_date('Y')); ?> Luxury Watches · Kết nối được bảo mật</p>
    </main>
</div>

<?php wp_footer(); ?>
</body>
</html>

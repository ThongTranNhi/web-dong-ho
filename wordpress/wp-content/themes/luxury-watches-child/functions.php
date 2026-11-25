<?php

require_once get_stylesheet_directory() . '/inc/seo.php';
require_once get_stylesheet_directory() . '/inc/brand-catalog.php';
require_once get_stylesheet_directory() . '/inc/storefront-experience.php';
require_once get_stylesheet_directory() . '/inc/watch-angle-galleries.php';

add_action('wp_head', function () {
    printf(
        '<link rel="icon" type="image/svg+xml" href="%s" />' . "\n",
        esc_url(get_stylesheet_directory_uri() . '/assets/lw-favicon.svg')
    );
}, 2);

// Older article content can contain local URLs saved before the IPv4 fallback.
add_filter('the_content', function ($content) {
    if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === '127.0.0.1') {
        return str_replace('http://localhost/luxury-watches/wordpress/', 'http://127.0.0.1/luxury-watches/wordpress/', $content);
    }
    return $content;
}, 1);

add_action('wp', function () {
    if (is_page(['chinh-sach-doi-tra', 'chinh-sach-bao-hanh', 'cau-hoi-thuong-gap', 'tieu-chuan-giao-nhan-hang', 'lien-he'])) {
        remove_filter('the_content', 'wpautop');
    }
});

add_filter('nav_menu_link_attributes', function ($attributes) {
    if (isset($_SERVER['HTTP_HOST'], $attributes['href']) && $_SERVER['HTTP_HOST'] === '127.0.0.1') {
        $attributes['href'] = str_replace('http://localhost/luxury-watches/wordpress/', 'http://127.0.0.1/luxury-watches/wordpress/', $attributes['href']);
    }
    return $attributes;
});

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('storefront-parent', get_template_directory_uri() . '/style.css', [], wp_get_theme('storefront')->get('Version'));
    wp_enqueue_style('luxury-watches', get_stylesheet_uri(), ['storefront-parent'], filemtime(get_stylesheet_directory() . '/style.css'));
    $motion_css = get_stylesheet_directory() . '/assets/motion.css';
    $motion_js = get_stylesheet_directory() . '/assets/motion.js';
    wp_enqueue_style('luxury-watches-motion', get_stylesheet_directory_uri() . '/assets/motion.css', ['luxury-watches'], filemtime($motion_css));
    wp_enqueue_script('luxury-watches-motion', get_stylesheet_directory_uri() . '/assets/motion.js', [], filemtime($motion_js), true);
    $nav_css = get_stylesheet_directory() . '/assets/navigation.css';
    $nav_js = get_stylesheet_directory() . '/assets/navigation.js';
    wp_enqueue_style('luxury-watches-nav', get_stylesheet_directory_uri() . '/assets/navigation.css', ['luxury-watches'], filemtime($nav_css));
    wp_enqueue_script('luxury-watches-nav', get_stylesheet_directory_uri() . '/assets/navigation.js', [], filemtime($nav_js), true);
    $gallery_css = get_stylesheet_directory() . '/assets/watch-preview.css';
    $gallery_js = get_stylesheet_directory() . '/assets/watch-preview.js';
    wp_enqueue_style('luxury-watches-preview', get_stylesheet_directory_uri() . '/assets/watch-preview.css', ['luxury-watches-motion'], filemtime($gallery_css));
    wp_enqueue_script('luxury-watches-preview', get_stylesheet_directory_uri() . '/assets/watch-preview.js', [], filemtime($gallery_js), true);
    $ws_css = get_stylesheet_directory() . '/assets/watchstore-style.css';
    if (file_exists($ws_css)) {
        wp_enqueue_style('watchstore-style', get_stylesheet_directory_uri() . '/assets/watchstore-style.css', ['luxury-watches'], filemtime($ws_css));
    }
});

add_action('init', function () {
    add_rewrite_rule('^product-category/(.+)/?$', 'index.php?product_cat=$matches[1]', 'top');
});

add_action('after_setup_theme', function () {
    remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10);
    add_action('woocommerce_before_shop_loop_item_title', function () {
        global $product;
        if (!$product instanceof WC_Product) { woocommerce_template_loop_product_thumbnail(); return; }
        // Legacy lab galleries used unrelated illustrations. New gallery images remain available.
        $legacy = [54 => [21,22], 66 => [65,62], 67 => [64,65], 68 => [62,63,65]];
        $exclude = $legacy[$product->get_id()] ?? [];
        $urls = [];
        foreach ($product->get_gallery_image_ids() as $id) {
            if (in_array((int) $id, $exclude, true) || (int) $id === (int) $product->get_image_id()) { continue; }
            $url = wp_get_attachment_image_url($id, 'woocommerce_thumbnail');
            if ($url) { $urls[] = $url; }
            if (count($urls) >= 4) { break; }
        }
        $urls = array_values(array_unique(array_merge($urls, lw_get_curated_watch_angles($product))));
        $urls = array_slice($urls, 0, 4);
        echo '<span class="lw-watch-preview"';
        if ($urls) { echo ' data-watch-gallery="' . esc_attr(wp_json_encode($urls)) . '"'; }
        echo '>';
        woocommerce_template_loop_product_thumbnail();
        if ($urls) {
            // Keep the alternate layer request-free until hover JS selects a real angle.
            echo '<img class="lw-watch-preview__alternate" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="" aria-hidden="true" decoding="async"><span class="lw-watch-preview__hint" aria-hidden="true">Nhiều góc nhìn</span>';
        }
        echo '</span>';
    }, 10);
});

add_filter('body_class', function ($classes) {
    if (is_front_page()) {
        $classes[] = 'lw-home';
    }
    if (is_page('gioi-thieu')) {
        $classes[] = 'lw-about-page';
    }
    if (is_page(['huong-dan-mua-hang', 'huong-dan-dau-gia', 'chinh-sach-bao-hanh', 'chinh-sach-doi-tra', 'chinh-sach-van-chuyen', 'chinh-sach-bao-mat', 'cau-hoi-thuong-gap', 'tieu-chuan-giao-nhan-hang', 'lien-he'])) {
        $classes[] = 'lw-information-page';
    }
    if (is_cart() || is_checkout() || is_account_page() || is_page() || is_shop() || is_product_category()) {
        $classes = array_diff($classes, ['right-sidebar', 'left-sidebar']);
        $classes[] = 'storefront-full-width-content';
        $classes[] = 'lw-full-width';
    }
    return $classes;
}, 99);

add_action('wp', function () {
    if (is_front_page() || is_cart() || is_checkout() || is_account_page() || is_page() || is_shop() || is_product_category() || is_singular('watch_auction') || is_post_type_archive('watch_auction')) {
        remove_action('storefront_sidebar', 'storefront_get_sidebar', 10);
    }
    if (is_page('gioi-thieu')) {
        remove_action('storefront_before_content', 'woocommerce_breadcrumb', 10);
    }
    if (is_product_category()) {
        remove_action('woocommerce_archive_description', 'woocommerce_taxonomy_archive_description', 10);
    }
});

add_action('init', function () {
    remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);
    remove_action('woocommerce_after_shop_loop', 'woocommerce_result_count', 20);
});

add_filter('loop_shop_per_page', function ($count) {
    return is_product_category('bo-suu-tap-moi-2026') ? 20 : 16;
}, 20);

add_filter('woocommerce_show_page_title', function ($show) {
    return (is_shop() || is_product_category()) ? false : $show;
});

add_action('woocommerce_before_main_content', function () {
    if (!is_shop() && !is_product_category()) {
        return;
    }

    $current = is_product_category() ? get_queried_object() : null;
    $title = $current instanceof WP_Term ? $current->name : 'Bộ sưu tập đồng hồ chính hãng';
    $count = $current instanceof WP_Term ? $current->count : (function_exists('wc_get_products') ? count(wc_get_products(['status' => 'publish', 'limit' => -1])) : 0);
    $description = $current instanceof WP_Term && $current->description
        ? $current->description
        : 'Tuyển chọn những cỗ máy thời gian cao cấp chính hãng từ các thương hiệu danh tiếng Thụy Sĩ, Nhật Bản & Đức. Mỗi chiếc đều qua quy trình kiểm định nghiêm ngặt 5 bước.';
    
    // Get live counts for tabs
    $term_new = get_term_by('slug', 'bo-suu-tap-moi-2026', 'product_cat');
    $term_men = get_term_by('slug', 'dong-ho-nam', 'product_cat');
    $term_women = get_term_by('slug', 'dong-ho-nu', 'product_cat');

    $links = [
        ['Tất cả kiệt tác', wc_get_page_permalink('shop'), is_shop(), 'All'],
        ['Mới về 2026', get_term_link('bo-suu-tap-moi-2026', 'product_cat'), is_product_category('bo-suu-tap-moi-2026'), ($term_new ? $term_new->count : 40)],
        ['Đồng hồ nam', get_term_link('dong-ho-nam', 'product_cat'), is_product_category('dong-ho-nam'), ($term_men ? $term_men->count : 33)],
        ['Đồng hồ nữ', get_term_link('dong-ho-nu', 'product_cat'), is_product_category('dong-ho-nu'), ($term_women ? $term_women->count : 27)],
        ['Sàn đấu giá live', home_url('/dau-gia/'), false, 'Live'],
    ];
    ?>
    <section class="lw-shop-intro" aria-labelledby="lw-shop-title">
        <div class="lw-shop-intro__inner">
            <div class="lw-shop-intro__content">
                <div class="lw-shop-intro__meta">
                    <span class="lw-shop-intro__kicker">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="#C5A059"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        HAUTE HORLOGERIE · BỘ SƯU TẬP TUYỂN CHỌN
                    </span>
                    <?php if ($count) : ?>
                        <span class="lw-shop-intro__pill"><?php echo esc_html($count); ?> Phiên bản chính hãng</span>
                    <?php endif; ?>
                </div>
                
                <h1 id="lw-shop-title" class="lw-display"><?php echo esc_html($title); ?></h1>
                <p class="lw-shop-intro__desc"><?php echo esc_html(wp_strip_all_tags($description)); ?></p>

                <!-- Minimalist Enterprise Filter Navigation -->
                <nav class="lw-shop-chips" aria-label="Lọc nhanh bộ sưu tập">
                    <?php foreach ($links as [$label, $url, $active, $badge]) : if (is_wp_error($url)) { continue; } ?>
                        <a href="<?php echo esc_url($url); ?>" class="lw-chip-item<?php echo $active ? ' is-active' : ''; ?>"<?php echo $active ? ' aria-current="page"' : ''; ?>>
                            <span class="lw-chip-label"><?php echo esc_html($label); ?></span>
                            <?php if ($badge) : ?>
                                <span class="lw-chip-badge"><?php echo esc_html($badge); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <!-- Trust Bar with Gold Line and Minimalist Badges -->
            <div class="lw-shop-assurance" aria-label="Cam kết mua sắm">
                <div class="lw-assurance-badge">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span><strong>100% Giám định chuyên gia</strong> · Đền gấp 20 lần</span>
                </div>
                <div class="lw-assurance-sep"></div>
                <div class="lw-assurance-badge">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span><strong>Bảo hành toàn diện 5 năm</strong> · Thay pin miễn phí trọn đời</span>
                </div>
                <div class="lw-assurance-sep"></div>
                <div class="lw-assurance-badge">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    <span><strong>Đồng kiểm tận nơi toàn quốc</strong> · Đổi mới 7 ngày</span>
                </div>
            </div>
        </div>
    </section>
    <?php
}, 25);

add_action('woocommerce_before_shop_loop_item_title', function () {
    global $product;
    if ($product instanceof WC_Product && has_term('bo-suu-tap-moi-2026', 'product_cat', $product->get_id())) {
        echo '<span class="lw-new-badge">Mới 2026</span>';
    }
}, 5);

add_action('init', function () {
    remove_action('storefront_footer', 'storefront_credit', 20);
    remove_action('storefront_footer', 'storefront_footer_widgets', 10);
    add_action('storefront_footer', 'storefront_footer_widgets', 30);
});

add_action('storefront_footer', function () {
    $page_url = static function (string $slug, string $fallback = '/') {
        $page = get_page_by_path($slug);
        return $page ? get_permalink($page) : home_url($fallback);
    };
    $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
    $account_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/my-account/');
    $cart_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('cart') : home_url('/cart/');
    ?>
    <div class="lw-footer">
        <div class="lw-wrap">
            <section class="lw-footer-cta" aria-labelledby="lw-footer-cta-title">
                <div>
                    <h2 id="lw-footer-cta-title" class="lw-display">Tìm chiếc đồng hồ<br> dành riêng cho bạn.</h2>
                    <p>Khám phá những thiết kế phù hợp với phong cách của bạn, từ lựa chọn hằng ngày đến các phiên đấu giá tuyển chọn.</p>
                </div>
                <div class="lw-footer-cta__actions">
                    <a class="lw-button" href="<?php echo esc_url($shop_url); ?>">Khám phá bộ sưu tập <span aria-hidden="true">↗</span></a>
                    <a class="lw-footer-text-link" href="<?php echo esc_url($page_url('lien-he', '/lien-he/')); ?>">Liên hệ tư vấn</a>
                </div>
            </section>

            <div class="lw-footer-grid">
                <section class="lw-footer-brand" aria-label="Luxury Watches">
                    <a class="lw-footer-logo" href="<?php echo esc_url(home_url('/')); ?>">WATCHSTORE ATELIER</a>
                    <p>Hệ thống phân phối đồng hồ chính hãng: Seiko, Citizen, Carnival, Tsar Bomba cùng sàn đấu giá độc quyền. Cam kết 100% chính hãng – Đền gấp 20 lần nếu phát hiện hàng giả.</p>
                    <div class="lw-footer-badges"><span>100% Chính hãng</span><span>Bảo hành 5 năm</span><span>Đổi mới 7 ngày</span></div>
                </section>

                <nav class="lw-footer-column" aria-label="Khám phá">
                    <h3>Thương hiệu &amp; Sản phẩm</h3>
                    <a href="<?php echo esc_url($page_url('thuong-hieu-dong-ho', '/thuong-hieu-dong-ho/')); ?>">Tất cả thương hiệu</a>
                    <a href="<?php echo esc_url(add_query_arg('hang', 'seiko', $page_url('thuong-hieu-dong-ho', '/thuong-hieu-dong-ho/'))); ?>">Đồng hồ Seiko (Nhật Bản)</a>
                    <a href="<?php echo esc_url(add_query_arg('hang', 'citizen', $page_url('thuong-hieu-dong-ho', '/thuong-hieu-dong-ho/'))); ?>">Đồng hồ Citizen (Eco-Drive)</a>
                    <a href="<?php echo esc_url(add_query_arg('hang', 'carnival', $page_url('thuong-hieu-dong-ho', '/thuong-hieu-dong-ho/'))); ?>">Đồng hồ Carnival (Nautilus)</a>
                    <a href="<?php echo esc_url(add_query_arg('hang', 'tsar-bomba', $page_url('thuong-hieu-dong-ho', '/thuong-hieu-dong-ho/'))); ?>">Đồng hồ Tsar Bomba (Tonneau)</a>
                    <a href="<?php echo esc_url($page_url('dau-gia', '/dau-gia/')); ?>">Sàn đấu giá độc quyền</a>
                </nav>

                <nav class="lw-footer-column" aria-label="Hỗ trợ khách hàng">
                    <h3>Hỗ trợ khách hàng</h3>
                    <a href="<?php echo esc_url($page_url('huong-dan-mua-hang', '/huong-dan-mua-hang/')); ?>">Hướng dẫn mua hàng</a>
                    <a href="<?php echo esc_url($page_url('chinh-sach-bao-hanh', '/chinh-sach-bao-hanh/')); ?>">Chính sách bảo hành 5 năm</a>
                    <a href="<?php echo esc_url($page_url('chinh-sach-doi-tra', '/chinh-sach-doi-tra/')); ?>">Chính sách đổi trả 7 ngày</a>
                    <a href="<?php echo esc_url($page_url('chinh-sach-van-chuyen', '/chinh-sach-van-chuyen/')); ?>">Tiêu chuẩn giao nhận &amp; Freeship</a>
                    <a href="<?php echo esc_url($page_url('cau-hoi-thuong-gap', '/cau-hoi-thuong-gap/')); ?>">Câu hỏi thường gặp (FAQ)</a>
                    <a href="<?php echo esc_url($page_url('lien-he', '/lien-he/')); ?>">Hệ thống 5 Showroom</a>
                    <a href="<?php echo esc_url($account_url); ?>">Tài khoản thành viên</a>
                </nav>

                <section class="lw-footer-column lw-footer-services" aria-labelledby="lw-footer-services-title">
                    <h3 id="lw-footer-services-title">Hệ thống Showroom</h3>
                    <p><strong>Hà Nội</strong><span>97 Trần Đại Nghĩa &amp; 58 Trần Đăng Ninh</span></p>
                    <p><strong>TP. Hồ Chí Minh</strong><span>90 Lê Văn Sỹ &amp; 193 Lê Quang Định</span></p>
                    <p><strong>Đà Nẵng</strong><span>120 Nguyễn Văn Linh, Hải Châu</span></p>
                    <p style="margin-top:8px;"><strong style="color:#C5A059;">Hotline: 0988 123 456</strong><br><span style="font-size:11px;">Mở cửa: 8:30 – 21:30 (Cả T7, CN &amp; Lễ)</span></p>
                </section>
            </div>

            <div class="lw-footer-bottom">
                <p>© <?php echo esc_html(wp_date('Y')); ?> Luxury Watches. Bảo lưu mọi quyền.</p>
                <nav class="lw-footer-legal" aria-label="Thông tin pháp lý"><a href="<?php echo esc_url($page_url('chinh-sach-bao-mat', '/chinh-sach-bao-mat/')); ?>">Chính sách bảo mật</a><a href="<?php echo esc_url($page_url('dieu-khoan-va-dieu-kien', '/dieu-khoan-va-dieu-kien/')); ?>">Điều khoản và điều kiện</a></nav>
            </div>
            <div class="lw-footer-wordmark" aria-hidden="true">Luxury Watches</div>
        </div>
    </div>
    <?php
}, 20);

add_filter('gettext', function ($translated, $text, $domain) {
    $labels = [
        'Add to cart' => 'Thêm vào giỏ',
        'Select options' => 'Chọn phiên bản',
        'Sale!' => 'Ưu đãi',
        'My Account' => 'Tài khoản',
        'Search' => 'Tìm kiếm',
        'Search products&hellip;' => 'Tìm kiếm đồng hồ…',
        'Cart' => 'Giỏ hàng',
    ];
    return $labels[$text] ?? $translated;
}, 20, 3);

// Subtitle for On Sale section to enrich visual presentation
add_action('storefront_homepage_after_on_sale_products_title', function() {
    echo '<p class="lw-section-subtitle">Tuyển chọn các phiên bản đồng hồ cao cấp với mức giá ưu đãi đặc quyền giới hạn trong tháng</p>';
});

/**
 * ==========================================================================
 * LUXURY CART PAGE ENHANCEMENTS
 * ==========================================================================
 */
// 1. Checkout Steps Indicator & Assurance Bar Before Cart
add_action('woocommerce_before_cart', 'lw_render_cart_header_enhancements', 5);
function lw_render_cart_header_enhancements() {
    ?>
    <div class="lw-cart-header-wrapper">
        <!-- Breadcrumb / Checkout Steps -->
        <nav class="lw-checkout-steps" aria-label="Tiến trình thanh toán">
            <div class="lw-step is-active">
                <span class="lw-step-num">1</span>
                <span class="lw-step-label">Giỏ hàng của bạn</span>
            </div>
            <div class="lw-step-divider"></div>
            <div class="lw-step">
                <span class="lw-step-num">2</span>
                <span class="lw-step-label">Thông tin & Thanh toán</span>
            </div>
            <div class="lw-step-divider"></div>
            <div class="lw-step">
                <span class="lw-step-num">3</span>
                <span class="lw-step-label">Hoàn tất đơn hàng</span>
            </div>
        </nav>

        <!-- Luxury Assurance Banner -->
        <div class="lw-cart-assurance-banner">
            <div class="lw-cart-assurance-item">
                <span class="lw-assurance-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg></span>
                <div>
                    <strong>Bảo hiểm vận chuyển 100%</strong>
                    <span>Bảo vệ trọn vẹn giá trị đồng hồ trên toàn hành trình</span>
                </div>
            </div>
            <div class="lw-cart-assurance-item">
                <span class="lw-assurance-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><path d="m11 8 0 6"/><path d="m8 11 6 0"/></svg></span>
                <div>
                    <strong>Quyền đồng kiểm tận tay</strong>
                    <span>Kiểm tra thân vỏ & thẻ bảo hành trước khi thanh toán</span>
                </div>
            </div>
            <div class="lw-cart-assurance-item">
                <span class="lw-assurance-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg></span>
                <div>
                    <strong>Giao hỏa tốc chuyên biệt</strong>
                    <span>Giao trong 2 giờ tại nội thành TP.HCM & Hà Nội</span>
                </div>
            </div>
            <div class="lw-cart-assurance-item">
                <span class="lw-assurance-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg></span>
                <div>
                    <strong>Bảo hành quốc tế 5 năm</strong>
                    <span>Hỗ trợ kỹ thuật và bảo dưỡng định kỳ Atelier</span>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// 2. Trust Badges & VIP Concierge Support in Cart Totals
add_action('woocommerce_after_cart_totals', 'lw_render_cart_trust_and_support');
function lw_render_cart_trust_and_support() {
    ?>
    <div class="lw-cart-trust-box">
        <div class="lw-trust-title">
            <span><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block; vertical-align:-2px; margin-right:4px;"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Thanh toán bảo mật SSL 256-bit</span>
        </div>
        <div class="lw-payment-methods-icons">
            <span class="lw-pay-tag">Chuyển khoản QR</span>
            <span class="lw-pay-tag">COD Đồng kiểm</span>
            <span class="lw-pay-tag">Visa / Master</span>
            <span class="lw-pay-tag">Thẻ ATM Napas</span>
        </div>
        <div class="lw-cart-concierge-card">
            <div class="lw-concierge-avatar">VIP</div>
            <div class="lw-concierge-text">
                <strong>Cần tư vấn riêng cho đơn hàng?</strong>
                <p>Chuyên viên Concierge sẵn sàng hỗ trợ 24/7 qua hotline: <a href="tel:0988123456">0988 123 456</a></p>
            </div>
        </div>
    </div>
    <?php
}

// 3. Compact Corner Toast Notification (Bottom-Right, disappears after 1.5s)
add_action('wp_footer', 'lw_render_add_to_cart_toast_notification');
function lw_render_add_to_cart_toast_notification() {
    $cart_url = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/gio-hang/');
    ?>
    <div id="lwCartToast" class="lw-cart-toast" role="status" aria-live="polite">
        <div class="lw-cart-toast__progress" id="lwToastProgress"></div>
        <div class="lw-cart-toast__inner">
            <div class="lw-cart-toast__message">
                <span class="lw-cart-toast__icon">&#10003;</span>
                <div class="lw-cart-toast__content">
                    <span class="lw-cart-toast__badge">ĐÃ THÊM VÀO GIỎ</span>
                    <span class="lw-cart-toast__text" id="lwCartToastText">Đã thêm vào đơn hàng!</span>
                </div>
            </div>
            <div class="lw-cart-toast__actions">
                <a href="<?php echo esc_url($cart_url); ?>" class="lw-cart-toast__link">Xem giỏ &rarr;</a>
                <button type="button" class="lw-cart-toast__close" id="lwToastClose" aria-label="Đóng">&times;</button>
            </div>
        </div>
    </div>

    <script>
    jQuery(function($) {
        var toastTimer = null;

        function showCartToast(productName) {
            var $toast = $('#lwCartToast');
            var $text = $('#lwCartToastText');
            var $progress = $('#lwToastProgress');
            if (!$toast.length) return;

            // Remove any WooCommerce added_to_cart link below buttons
            $('a.added_to_cart').remove();

            if (productName) {
                $text.html('<strong>' + productName + '</strong>');
            } else {
                $text.text('Đã thêm sản phẩm vào đơn hàng!');
            }

            // Reset animation
            $progress.removeClass('is-animating');
            void $progress[0].offsetWidth; // force reflow
            $progress.addClass('is-animating');

            $toast.addClass('is-visible');

            if (toastTimer) {
                clearTimeout(toastTimer);
            }

            // Disappear after exactly 1.5 seconds (1500ms)
            toastTimer = setTimeout(function() {
                $toast.removeClass('is-visible');
            }, 1500);
        }

        $('#lwToastClose').on('click', function(e) {
            e.preventDefault();
            if (toastTimer) clearTimeout(toastTimer);
            $('#lwCartToast').removeClass('is-visible');
        });

        $(document.body).on('added_to_cart', function(event, fragments, cart_hash, $button) {
            var name = '';
            if ($button && $button.length) {
                var $card = $button.closest('.product');
                if ($card.length) {
                    name = $card.find('.woocommerce-loop-product__title').first().text().trim();
                }
                if (!name) {
                    name = $button.attr('aria-label') ? $button.attr('aria-label').replace(/Thêm vào giỏ hàng: /i, '').replace(/[“”"]/g, '').trim() : '';
                }
            }
            showCartToast(name);

            setTimeout(function() {
                $('a.added_to_cart').remove();
            }, 50);
        });
    });
    </script>
    <?php
}

// 4. Checkout Progress Header
add_action('woocommerce_before_checkout_form', 'lw_render_checkout_header_enhancements', 5);
function lw_render_checkout_header_enhancements() {
    ?>
    <div class="lw-cart-header-wrapper" style="margin-bottom: 28px;">
        <nav class="lw-checkout-steps" aria-label="Tiến trình thanh toán">
            <div class="lw-step is-complete">
                <span class="lw-step-num">&#10003;</span>
                <span class="lw-step-label">Giỏ hàng của bạn</span>
            </div>
            <div class="lw-step-divider"></div>
            <div class="lw-step is-active">
                <span class="lw-step-num">2</span>
                <span class="lw-step-label">Thông tin &amp; Thanh toán</span>
            </div>
            <div class="lw-step-divider"></div>
            <div class="lw-step">
                <span class="lw-step-num">3</span>
                <span class="lw-step-label">Hoàn tất đơn hàng</span>
            </div>
        </nav>
    </div>
    <?php
}

// 5. Performance Optimizations (Smooth Loading & Zero Lag)
add_action('init', function () {
    // Disable WP core emojis to reduce JS/CSS bloat
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
    add_filter('tiny_mce_plugins', function ($plugins) {
        return is_array($plugins) ? array_diff($plugins, ['wpemoji']) : [];
    });

    // Slow down heartbeat to save server CPU
    add_filter('heartbeat_settings', function ($settings) {
        $settings['interval'] = 60;
        return $settings;
    });
});

// Disable heavy WooCommerce cart fragments on static pages that don't need real-time cart counts
add_action('wp_enqueue_scripts', function () {
    if (is_page(['lien-he', 'chinh-sach-doi-tra', 'chinh-sach-bao-hanh', 'cau-hoi-thuong-gap', 'tieu-chuan-giao-nhan-hang', 'gioi-thieu'])) {
        wp_dequeue_script('wc-cart-fragments');
    }
}, 100);

/**
 * Nạp chức năng Lab 9 (Hooks, Shortcode, Custom Post Type)
 */
if (file_exists(WP_CONTENT_DIR . '/themes/astra/inc/lab9-functions.php')) {
    require_once WP_CONTENT_DIR . '/themes/astra/inc/lab9-functions.php';
}




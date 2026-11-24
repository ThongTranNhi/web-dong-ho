<?php
if (!defined('ABSPATH')) exit;

function lw_auction_overview() {
    static $overview;
    if (isset($overview)) return $overview;
    $overview = ['live' => [], 'upcoming' => [], 'ended' => []];
    if (!class_exists('Luxury_Watches_Auctions')) return $overview;
    $auctions = get_posts([
        'post_type' => 'watch_auction', 'post_status' => 'publish',
        'numberposts' => -1, 'meta_key' => '_lw_end_at',
        'orderby' => 'meta_value', 'order' => 'ASC',
    ]);
    foreach ($auctions as $auction) {
        $state = Luxury_Watches_Auctions::state($auction->ID);
        if (isset($overview[$state])) $overview[$state][] = $auction->ID;
    }
    // In the archive, show recently closed auctions before older sessions.
    $overview['ended'] = array_reverse($overview['ended']);
    return $overview;
}

function lw_auction_destination() {
    $page = get_page_by_path('dau-gia');
    return $page && $page->post_status === 'publish' ? get_permalink($page) : home_url('/dau-gia/');
}

add_action('wp_enqueue_scripts', function () {
    wp_dequeue_style('storefront-fonts');
    $file = get_stylesheet_directory() . '/assets/storefront-refresh.css';
    wp_enqueue_style('lw-storefront-refresh', get_stylesheet_directory_uri() . '/assets/storefront-refresh.css', ['luxury-watches', 'luxury-watches-nav', 'storefront-woocommerce-style'], filemtime($file));
}, 99);

add_filter('body_class', function ($classes) {
    if (is_page('dau-gia')) $classes[] = 'lw-auction-page';
    return $classes;
});

function lw_primary_menu_fallback($args) {
    $links = [
        ['Trang chủ', home_url('/')],
        ['Bộ sưu tập', function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/')],
        ['Đấu giá', lw_auction_destination()],
    ];
    foreach (['thuong-hieu-dong-ho' => 'Thương hiệu', 'huong-dan-mua-hang' => 'Cẩm nang', 'gioi-thieu' => 'Về Atelier'] as $slug => $label) {
        $page = get_page_by_path($slug);
        if ($page && $page->post_status === 'publish') $links[] = [$label, get_permalink($page)];
    }
    $items = '';
    foreach ($links as [$label, $url]) {
        $active = untrailingslashit(wp_parse_url($url, PHP_URL_PATH) ?: '/') === untrailingslashit(wp_parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $items .= '<li class="menu-item' . ($active ? ' current-menu-item' : '') . '"><a href="' . esc_url($url) . '"' . ($active ? ' aria-current="page"' : '') . '>' . esc_html($label) . '</a></li>';
    }
    $markup = '<div class="' . esc_attr($args['container_class']) . '"><ul class="menu">' . $items . '</ul></div>';
    if (!empty($args['echo'])) echo $markup;
    return $markup;
}

add_filter('wp_nav_menu_args', function ($args) {
    if (in_array($args['theme_location'] ?? '', ['primary', 'handheld'], true)) $args['fallback_cb'] = 'lw_primary_menu_fallback';
    return $args;
});

add_filter('storefront_handheld_footer_bar_links', function ($links) {
    unset($links['search']);
    $links['auctions'] = ['priority' => 20, 'callback' => 'lw_handheld_auction_link'];
    uasort($links, static function ($a, $b) { return $a['priority'] <=> $b['priority']; });
    return $links;
});

function lw_handheld_auction_link() {
    echo '<a href="' . esc_url(lw_auction_destination()) . '">Đấu giá</a>';
}

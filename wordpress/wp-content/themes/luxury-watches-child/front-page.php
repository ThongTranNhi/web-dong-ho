<?php
/**
 * WatchStore.vn Style Homepage - Luxury Watches Atelier
 * Upgraded E-Commerce Layout: Trust Badges, Flash Deals, Interactive Tabs, Omnibox Header
 */

get_header();

// Fetch Products & Data
$featured_products = function_exists('wc_get_products') ? wc_get_products([
    'status'   => 'publish',
    'limit'    => 8,
    'orderby'  => 'date',
    'order'    => 'DESC'
]) : [];

$flash_sale_products = function_exists('wc_get_products') ? wc_get_products([
    'status'   => 'publish',
    'limit'    => 4,
    'category' => ['dong-ho-nam', 'dong-ho-nu', 'bo-suu-tap-moi-2026'],
    'orderby'  => 'date',
    'order'    => 'DESC'
]) : [];

$men_products = function_exists('wc_get_products') ? wc_get_products([
    'status'   => 'publish',
    'limit'    => 4,
    'category' => ['dong-ho-nam']
]) : [];

$women_products = function_exists('wc_get_products') ? wc_get_products([
    'status'   => 'publish',
    'limit'    => 4,
    'category' => ['dong-ho-nu']
]) : [];

$new_products = function_exists('wc_get_products') ? wc_get_products([
    'status'   => 'publish',
    'limit'    => 8,
    'category' => ['bo-suu-tap-moi-2026'],
    'orderby'  => 'date',
    'order'    => 'DESC'
]) : [];
if (empty($new_products)) {
    $new_products = $featured_products;
}

$asset_base = get_stylesheet_directory_uri() . '/assets/images/';
$hero_image = $asset_base . 'hero-atelier.webp';
$auction_image = $asset_base . 'auction-preview.webp';
$care_image = $asset_base . 'watchmaking-service.webp';
$posts = get_posts(['post_status' => 'publish', 'numberposts' => 3, 'orderby' => 'date']);

$auction_overview = function_exists('lw_auction_overview') ? lw_auction_overview() : ['live' => [], 'upcoming' => [], 'ended' => []];
$featured_auctions = array_slice(array_merge($auction_overview['live'], $auction_overview['upcoming'], $auction_overview['ended']), 0, 3);

$shop_url = wc_get_page_permalink('shop');
$cart_url = wc_get_cart_url();
$cart_count = is_object(WC()->cart) ? WC()->cart->get_cart_contents_count() : 0;
$cart_total = is_object(WC()->cart) ? WC()->cart->get_cart_total() : '0 đ';
?>

<div class="ws-page-wrapper">

  <!-- 1. TOP ANNOUNCEMENT TICKER -->
  <div class="ws-topbar">
    <div class="ws-container">
      <div class="ws-topbar__marquee">
        <span class="ws-topbar__badge">ĐẶC QUYỀN WATCHSTORE</span>
        <span class="ws-topbar__commit">CAM KẾT 100% CHÍNH HÃNG - ĐỀN GẤP 20 LẦN NẾU PHÁT HIỆN HÀNG GIẢ - BẢO HÀNH 5 NĂM TOÀN DIỆN</span>
      </div>
      <div class="ws-topbar__links">
        <a href="#showrooms">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          Hệ thống 5 Showroom
        </a>
        <a href="<?php echo esc_url(home_url('/chinh-sach-bao-hanh/')); ?>">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Tra cứu bảo hành
        </a>
        <a href="tel:0988123456">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          Hotline: 0988 123 456
        </a>
      </div>
    </div>
  </div>

  <!-- 2. WATCHSTORE OMNIBOX HEADER -->
  <header class="ws-header">
    <div class="ws-container">
      <a href="<?php echo esc_url(home_url('/')); ?>" class="ws-logo">
        <h1 class="ws-logo__title">WATCH<span>STORE</span></h1>
        <span class="ws-logo__tagline">Luxury Watches Atelier</span>
      </a>

      <!-- Central Search Omnibox -->
      <div class="ws-search">
        <form role="search" method="get" class="ws-search__form" action="<?php echo esc_url(home_url('/')); ?>">
          <input type="search" class="ws-search__input" placeholder="Tìm kiếm theo thương hiệu, mã hiệu (Tissot, Seiko, Casio, Tourbillon...)" value="<?php echo get_search_query(); ?>" name="s" autocomplete="off" />
          <input type="hidden" name="post_type" value="product" />
          <button type="submit" class="ws-search__button">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Tìm kiếm
          </button>
        </form>
        <div class="ws-search__suggestions">
          <strong>Gợi ý hot:</strong>
          <a href="<?php echo esc_url($shop_url . '?s=tourbillon'); ?>">Tourbillon</a>
          <a href="<?php echo esc_url($shop_url . '?s=titanium'); ?>">Mono Titanium</a>
          <a href="<?php echo esc_url($shop_url . '?s=ceramique'); ?>">Céramique Blanc</a>
          <a href="<?php echo esc_url($shop_url . '?s=diamond'); ?>">Étoile Diamond</a>
          <a href="<?php echo esc_url(home_url('/dau-gia/')); ?>">Sàn đấu giá</a>
        </div>
      </div>

      <!-- Right Utility Actions -->
      <div class="ws-actions">
        <a href="tel:0988123456" class="ws-action-item">
          <div class="ws-action-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          </div>
          <div class="ws-action-text">
            <span>Tư vấn mua hàng</span>
            <strong>0988 123 456</strong>
          </div>
        </a>
        <a href="<?php echo esc_url(get_permalink(get_option('woocommerce_myaccount_page_id'))); ?>" class="ws-action-item">
          <div class="ws-action-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          </div>
          <div class="ws-action-text">
            <span>Chào mừng</span>
            <strong>Tài khoản</strong>
          </div>
        </a>
        <a href="<?php echo esc_url($cart_url); ?>" class="ws-cart-pill">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
          <span>Giỏ hàng</span>
          <span class="ws-cart-count"><?php echo esc_html($cart_count); ?></span>
        </a>
      </div>
    </div>
  </header>

  <!-- 3. PRIMARY CATEGORY MEGA BAR -->
  <nav class="ws-nav">
    <div class="ws-container">
      <ul class="ws-nav__menu">
        <li class="ws-nav__item active">
          <a href="<?php echo esc_url(home_url('/')); ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Trang chủ
          </a>
        </li>
        <li class="ws-nav__item">
          <a href="<?php echo esc_url($shop_url); ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="7"/><polyline points="12 9 12 12 13.5 13.5"/><path d="M16.51 17.35l-.85 3.22a1.5 1.5 0 0 1-1.46 1.13h-4.4a1.5 1.5 0 0 1-1.46-1.13l-.85-3.22"/><path d="M7.49 6.65l.85-3.22A1.5 1.5 0 0 1 9.8 2.3h4.4a1.5 1.5 0 0 1 1.46 1.13l.85 3.22"/></svg>
            Tất cả đồng hồ
          </a>
        </li>
        <li class="ws-nav__item">
          <a href="<?php echo esc_url(home_url('/danh-muc-san-pham/dong-ho-nam/')); ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="10" cy="14" r="5"/><line x1="19" y1="5" x2="13.6" y2="10.4"/><line x1="19" y1="5" x2="14" y2="5"/><line x1="19" y1="5" x2="19" y2="10"/></svg>
            Đồng hồ Nam
          </a>
        </li>
        <li class="ws-nav__item">
          <a href="<?php echo esc_url(home_url('/danh-muc-san-pham/dong-ho-nu/')); ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="9" r="5"/><line x1="12" y1="14" x2="12" y2="21"/><line x1="9" y1="18" x2="15" y2="18"/></svg>
            Đồng hồ Nữ
          </a>
        </li>
        <li class="ws-nav__item">
          <a href="<?php echo esc_url(home_url('/danh-muc-san-pham/bo-suu-tap-moi-2026/')); ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            Mới về 2026 <span class="ws-nav__badge">HOT</span>
          </a>
        </li>
        <li class="ws-nav__item ws-nav__item--flash">
          <a href="#flash-sale">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            Flash Sale Giờ Vàng
          </a>
        </li>
        <li class="ws-nav__item ws-nav__item--auction">
          <a href="<?php echo esc_url(home_url('/dau-gia/')); ?>">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 13l5 5m-4.5-8.5l4-4a2.121 2.121 0 0 1 3 3l-4 4m-3-3l-7 7a2 2 0 0 1-2.828 0l-1.414-1.414a2 2 0 0 1 0-2.828l7-7m3 3l-4.5 4.5"/><path d="M2 21h8"/></svg>
            Sàn Đấu Giá Live
          </a>
        </li>
        <li class="ws-nav__item">
          <a href="#showrooms">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l1-5h16l1 5"/><path d="M21 9v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9"/><path d="M9 22V12h6v10"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/></svg>
            Showroom
          </a>
        </li>
      </ul>
    </div>
  </nav>

  <!-- 4. HERO SECTION (2:1 GRID WATCHSTORE STYLE) -->
  <section class="ws-hero">
    <div class="ws-container">
      <div class="ws-hero__grid">
        <div class="ws-hero__main" style="background-image: url('<?php echo esc_url($hero_image); ?>');">
          <div class="ws-hero__content">
            <span class="ws-hero__kicker">ĐẠI TIỆC ĐỒNG HỒ CHÍNH HÃNG 2026</span>
            <h2 class="ws-hero__title">Đỉnh Cao Chế Tác Thụy Sĩ &amp; Di Sản Độc Bản</h2>
            <p class="ws-hero__desc">Khám phá hơn 100+ kiệt tác thời gian cơ khí chính xác. Bảo hành 5 năm độc quyền, miễn phí thay pin trọn đời cùng chính sách trả góp 0% lãi suất.</p>
            <div style="display:flex; gap: 12px; align-items:center;">
              <a href="<?php echo esc_url($shop_url); ?>" class="ws-hero__cta">Săn Deal Ngay →</a>
              <a href="<?php echo esc_url(home_url('/dau-gia/')); ?>" class="ws-hero__cta" style="background: rgba(255,255,255,0.2); box-shadow:none;">Tham gia đấu giá</a>
            </div>
          </div>
        </div>

        <div class="ws-hero__side">
          <a href="<?php echo esc_url(home_url('/danh-muc-san-pham/dong-ho-nam/')); ?>" class="ws-side-card" style="background-image: url('<?php echo esc_url($asset_base . 'journal-collector.webp'); ?>');">
            <div class="ws-side-card__body">
              <span class="ws-side-card__tag">DÀNH CHO QUÝ ÔNG</span>
              <h3 class="ws-side-card__title">Đồng Hồ Nam Lịch Lãm &amp; Doanh Nhân</h3>
            </div>
          </a>
          <a href="<?php echo esc_url(home_url('/danh-muc-san-pham/dong-ho-nu/')); ?>" class="ws-side-card" style="background-image: url('<?php echo esc_url($care_image); ?>');">
            <div class="ws-side-card__body">
              <span class="ws-side-card__tag">TRẢ GÓP 0% LÃI SUẤT</span>
              <h3 class="ws-side-card__title">Đồng Hồ Nữ Étoile Diamond Halo</h3>
            </div>
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- 5. 5 GOLDEN TRUST BADGES (USP BAR) -->
  <section class="ws-trust-bar">
    <div class="ws-container">
      <div class="ws-trust-grid">
        <div class="ws-trust-card">
          <div class="ws-trust-card__icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#e21134" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
          </div>
          <div class="ws-trust-card__info">
            <strong>100% Chính Hãng</strong>
            <span>Đền gấp 20 lần nếu phát hiện hàng giả</span>
          </div>
        </div>
        <div class="ws-trust-card">
          <div class="ws-trust-card__icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#e21134" stroke-width="1.8"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/></svg>
          </div>
          <div class="ws-trust-card__info">
            <strong>1 Đổi 1 Trong 7 Ngày</strong>
            <span>Miễn phí đổi mới nếu có lỗi kỹ thuật</span>
          </div>
        </div>
        <div class="ws-trust-card">
          <div class="ws-trust-card__icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#e21134" stroke-width="1.8"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
          </div>
          <div class="ws-trust-card__info">
            <strong>Freeship Toàn Quốc</strong>
            <span>Giao hỏa tốc, kiểm hàng trước thanh toán</span>
          </div>
        </div>
        <div class="ws-trust-card">
          <div class="ws-trust-card__icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#e21134" stroke-width="1.8"><rect x="1" y="6" width="18" height="12" rx="2"/><line x1="23" y1="11" x2="23" y2="13"/><polyline points="7 10 10 12 7 14"/></svg>
          </div>
          <div class="ws-trust-card__info">
            <strong>Thay Pin Trọn Đời</strong>
            <span>Áp dụng vĩnh viễn cho máy Quartz</span>
          </div>
        </div>
        <div class="ws-trust-card">
          <div class="ws-trust-card__icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#e21134" stroke-width="1.8"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
          </div>
          <div class="ws-trust-card__info">
            <strong>Bảo Hành 5 Năm</strong>
            <span>Tiêu chuẩn dịch vụ Atelier toàn diện</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- 6. FLASH SALE GIỜ VÀNG (WITH LIVE COUNTDOWN) -->
  <section id="flash-sale" class="ws-flash">
    <div class="ws-container">
      <div class="ws-flash__head">
        <div class="ws-flash__title-wrap">
          <h2 class="ws-flash__title">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="#fde047" style="margin-right:6px; vertical-align:-3px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            FLASH SALE GIỜ VÀNG
          </h2>
          <div class="ws-countdown" id="wsCountdownTimer">
            <span class="ws-countdown__box" id="wsDays">01</span>
            <span class="ws-countdown__sep">:</span>
            <span class="ws-countdown__box" id="wsHours">12</span>
            <span class="ws-countdown__sep">:</span>
            <span class="ws-countdown__box" id="wsMinutes">35</span>
            <span class="ws-countdown__sep">:</span>
            <span class="ws-countdown__box" id="wsSeconds">48</span>
          </div>
        </div>
        <a href="<?php echo esc_url($shop_url); ?>" class="ws-flash__link">Xem tất cả Flash Deal →</a>
      </div>

      <div class="ws-flash__body">
        <div class="ws-product-grid">
          <?php 
          if (!empty($flash_sale_products)) :
            foreach ($flash_sale_products as $idx => $p) :
              $price = $p->get_price();
              $reg_price = $p->get_regular_price() ?: ($price * 1.25);
              $discount_percent = $reg_price > $price ? round((($reg_price - $price) / $reg_price) * 100) : 25;
              $sold_count = 12 + ($idx * 3);
              $total_stock = 20;
              $progress = round(($sold_count / $total_stock) * 100);
          ?>
          <div class="ws-card">
            <div class="ws-card__badges">
              <span class="ws-badge-sale">-<?php echo esc_html($discount_percent); ?>%</span>
              <span class="ws-badge-auth">CHÍNH HÃNG</span>
            </div>
            <a href="<?php echo esc_url($p->get_permalink()); ?>" class="ws-card__image-wrap">
              <?php echo $p->get_image('medium', ['class' => 'ws-card__image']); ?>
            </a>
            <span class="ws-card__brand">Swiss Heritage 2026</span>
            <h3 class="ws-card__title"><a href="<?php echo esc_url($p->get_permalink()); ?>" style="text-decoration:none; color:inherit;"><?php echo esc_html($p->get_name()); ?></a></h3>
            <div class="ws-card__rating">
              ⭐⭐⭐⭐⭐ <span>(5.0 · 38 đánh giá)</span>
            </div>
            <div class="ws-card__price-wrap">
              <span class="ws-card__price-sale"><?php echo wc_price($price); ?></span>
              <span class="ws-card__price-orig"><?php echo wc_price($reg_price); ?></span>
            </div>
            <div class="ws-card__progress">
              <div class="ws-card__progress-bar" style="width: <?php echo esc_attr($progress); ?>%;"></div>
              <span class="ws-card__progress-text">Đã bán <?php echo esc_html($sold_count); ?>/<?php echo esc_html($total_stock); ?> chiếc</span>
            </div>
            <a href="<?php echo esc_url($p->get_permalink()); ?>" class="ws-card__btn">SĂN DEAL NGAY</a>
          </div>
          <?php 
            endforeach;
          endif; 
          ?>
        </div>
      </div>
    </div>
  </section>

  <!-- 7. TOP BRANDS GRID & AUTHENTIC WATCHES -->
  <section class="ws-brands" id="thuong-hieu">
    <div class="ws-container">
      <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
        <div>
          <h2 class="ws-section-title" style="margin-bottom:6px;">THƯƠNG HIỆU ĐỒNG HỒ CHÍNH HÃNG</h2>
          <p class="ws-section-desc" style="margin-bottom:0;">Phân phối chính ngạch từ 4 thương hiệu danh tiếng: Seiko, Citizen, Carnival &amp; Tsar Bomba.</p>
        </div>
        <a href="<?php echo esc_url(home_url('/thuong-hieu-dong-ho/')); ?>" class="ws-flash__link" style="padding:8px 16px; font-size:13px; font-weight:700;">Xem tất cả 124+ mẫu hãng →</a>
      </div>

      <!-- 4 Core Brand Highlights -->
      <?php
      $brand_catalog_base = get_stylesheet_directory_uri() . '/assets/brand-catalog/';
      $official_brands_info = [
          [
              'slug' => 'seiko',
              'name' => 'SEIKO',
              'origin' => 'Nhật Bản · Presage & Prospex',
              'count' => '39+ Mẫu Chính Hãng',
              'badge' => 'CHÍNH HÃNG 100%',
              'img' => $brand_catalog_base . 'seiko-00.webp',
              'desc' => 'Đẳng cấp cơ khí Nhật Bản, bộ máy cơ In-House bền bỉ vượt thời gian.'
          ],
          [
              'slug' => 'citizen',
              'name' => 'CITIZEN',
              'origin' => 'Nhật Bản · Eco-Drive & Tsuyosa',
              'count' => '30+ Mẫu Chính Hãng',
              'badge' => 'CHÍNH HÃNG 100%',
              'img' => $brand_catalog_base . 'citizen-10.webp',
              'desc' => 'Tiên phong công nghệ ánh sáng Eco-Drive và thiết kế Tsuyosa thời thượng.'
          ],
          [
              'slug' => 'carnival',
              'name' => 'CARNIVAL',
              'origin' => 'Thương Hiệu 1986 · Nautilus & Skeleton',
              'count' => '30+ Mẫu Chính Hãng',
              'badge' => 'CHÍNH HÃNG 100%',
              'img' => $brand_catalog_base . 'carnival-21.webp',
              'desc' => 'Phong cách thể thao sang trọng, mặt kính Sapphire và lộ cơ tinh xảo.'
          ],
          [
              'slug' => 'tsar-bomba',
              'name' => 'TSAR BOMBA',
              'origin' => 'Tonneau Thể Thao · Phong Cách Đột Phá',
              'count' => '25+ Mẫu Chính Hãng',
              'badge' => 'CHÍNH HÃNG 100%',
              'img' => $brand_catalog_base . 'tsar-bomba-32.webp',
              'desc' => 'Dáng vỏ Tonneau biểu tượng xa xỉ, phong cách cơ học thể thao đỉnh cao.'
          ],
      ];
      ?>
      <div class="ws-brand-highlight-grid">
        <?php foreach ($official_brands_info as $b) : 
            $brand_url = home_url('/thuong-hieu-dong-ho/?hang=' . $b['slug']);
        ?>
        <a href="<?php echo esc_url($brand_url); ?>" class="ws-brand-tile">
          <div class="ws-brand-tile__thumb">
            <img src="<?php echo esc_url($b['img']); ?>" alt="<?php echo esc_attr($b['name']); ?>" loading="lazy" decoding="async" />
          </div>
          <div class="ws-brand-tile__info">
            <span class="ws-brand-tile__badge"><?php echo esc_html($b['badge']); ?></span>
            <h3 class="ws-brand-tile__name"><?php echo esc_html($b['name']); ?></h3>
            <span class="ws-brand-tile__origin"><?php echo esc_html($b['origin']); ?></span>
            <p class="ws-brand-tile__desc"><?php echo esc_html($b['desc']); ?></p>
            <div class="ws-brand-tile__action">
              <span class="ws-brand-tile__count"><?php echo esc_html($b['count']); ?></span>
              <span class="ws-brand-tile__arrow">Khám phá →</span>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- 8. INTERACTIVE CATEGORY TABS (POPULAR / NEW / MEN / WOMEN) -->
  <section class="ws-tabs-section">
    <div class="ws-container">
      <div class="ws-tabs" id="wsTabs">
        <button class="ws-tab-btn active" onclick="wsSwitchTab(event, 'tab-bestseller')">BÁN CHẠY NHẤT</button>
        <button class="ws-tab-btn" onclick="wsSwitchTab(event, 'tab-new2026')">MỚI VỀ 2026</button>
        <button class="ws-tab-btn" onclick="wsSwitchTab(event, 'tab-men')">ĐỒNG HỒ NAM</button>
        <button class="ws-tab-btn" onclick="wsSwitchTab(event, 'tab-women')">ĐỒNG HỒ NỮ</button>
      </div>

      <!-- Tab 1: Bestsellers -->
      <div id="tab-bestseller" class="ws-tab-content" style="display:block;">
        <div class="ws-product-grid">
          <?php 
          if (!empty($featured_products)) :
            foreach (array_slice($featured_products, 0, 8) as $p) :
              $price = $p->get_price();
              $reg_price = $p->get_regular_price() ?: ($price * 1.18);
              $discount_percent = $reg_price > $price ? round((($reg_price - $price) / $reg_price) * 100) : 15;
          ?>
          <div class="ws-card">
            <div class="ws-card__badges">
              <span class="ws-badge-sale">-<?php echo esc_html($discount_percent); ?>%</span>
              <span class="ws-badge-auth">TRẢ GÓP 0%</span>
            </div>
            <a href="<?php echo esc_url($p->get_permalink()); ?>" class="ws-card__image-wrap">
              <?php echo $p->get_image('medium', ['class' => 'ws-card__image']); ?>
            </a>
            <span class="ws-card__brand">Swiss Edition</span>
            <h3 class="ws-card__title"><a href="<?php echo esc_url($p->get_permalink()); ?>" style="text-decoration:none; color:inherit;"><?php echo esc_html($p->get_name()); ?></a></h3>
            <div class="ws-card__rating">⭐⭐⭐⭐⭐ <span>(4.9 · 52 đã bán)</span></div>
            <div class="ws-card__price-wrap">
              <span class="ws-card__price-sale"><?php echo wc_price($price); ?></span>
              <span class="ws-card__price-orig"><?php echo wc_price($reg_price); ?></span>
            </div>
            <a href="<?php echo esc_url($p->add_to_cart_url()); ?>" class="ws-card__btn" data-quantity="1" data-product_id="<?php echo esc_attr($p->get_id()); ?>">+ THÊM VÀO GIỎ</a>
          </div>
          <?php 
            endforeach;
          endif; 
          ?>
        </div>
      </div>

      <!-- Tab 2: New Arrivals 2026 -->
      <div id="tab-new2026" class="ws-tab-content" style="display:none;">
        <div class="ws-product-grid">
          <?php 
          if (!empty($new_products)) :
            foreach ($new_products as $p) :
              $price = $p->get_price();
              $reg_price = $p->get_regular_price() ?: ($price * 1.15);
              $discount_percent = $reg_price > $price ? round((($reg_price - $price) / $reg_price) * 100) : 10;
          ?>
          <div class="ws-card">
            <div class="ws-card__badges">
              <?php if ($discount_percent > 0) : ?><span class="ws-badge-sale">-<?php echo esc_html($discount_percent); ?>%</span><?php endif; ?>
              <span class="ws-badge-auth">MỚI 2026</span>
            </div>
            <a href="<?php echo esc_url($p->get_permalink()); ?>" class="ws-card__image-wrap">
              <?php echo $p->get_image('medium', ['class' => 'ws-card__image', 'loading' => 'lazy', 'decoding' => 'async']); ?>
            </a>
            <span class="ws-card__brand">Bộ Sưu Tập 2026</span>
            <h3 class="ws-card__title"><a href="<?php echo esc_url($p->get_permalink()); ?>" style="text-decoration:none; color:inherit;"><?php echo esc_html($p->get_name()); ?></a></h3>
            <div class="ws-card__rating">★★★★★ <span>(5.0 · Mới ra mắt)</span></div>
            <div class="ws-card__price-wrap">
              <span class="ws-card__price-sale"><?php echo wc_price($price); ?></span>
              <?php if ($reg_price > $price) : ?><span class="ws-card__price-orig"><?php echo wc_price($reg_price); ?></span><?php endif; ?>
            </div>
            <a href="<?php echo esc_url($p->add_to_cart_url()); ?>" class="ws-card__btn" data-quantity="1" data-product_id="<?php echo esc_attr($p->get_id()); ?>">+ THÊM VÀO GIỎ</a>
          </div>
          <?php 
            endforeach;
          endif; 
          ?>
        </div>
      </div>

      <!-- Tab 3: Men Watches -->
      <div id="tab-men" class="ws-tab-content" style="display:none;">
        <div class="ws-product-grid">
          <?php 
          if (!empty($men_products)) :
            foreach ($men_products as $p) :
              $price = $p->get_price();
              $reg_price = $p->get_regular_price() ?: ($price * 1.15);
          ?>
          <div class="ws-card">
            <div class="ws-card__badges"><span class="ws-badge-auth">ĐỒNG HỒ NAM</span></div>
            <a href="<?php echo esc_url($p->get_permalink()); ?>" class="ws-card__image-wrap">
              <?php echo $p->get_image('medium', ['class' => 'ws-card__image']); ?>
            </a>
            <h3 class="ws-card__title"><a href="<?php echo esc_url($p->get_permalink()); ?>" style="text-decoration:none; color:inherit;"><?php echo esc_html($p->get_name()); ?></a></h3>
            <div class="ws-card__rating">⭐⭐⭐⭐⭐ <span>(5.0)</span></div>
            <div class="ws-card__price-wrap"><span class="ws-card__price-sale"><?php echo wc_price($price); ?></span></div>
            <a href="<?php echo esc_url($p->get_permalink()); ?>" class="ws-card__btn">XEM CHI TIẾT</a>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- Tab 4: Women Watches -->
      <div id="tab-women" class="ws-tab-content" style="display:none;">
        <div class="ws-product-grid">
          <?php 
          if (!empty($women_products)) :
            foreach ($women_products as $p) :
              $price = $p->get_price();
          ?>
          <div class="ws-card">
            <div class="ws-card__badges"><span class="ws-badge-sale" style="background:#ec4899;">QUÝ CÔ</span></div>
            <a href="<?php echo esc_url($p->get_permalink()); ?>" class="ws-card__image-wrap">
              <?php echo $p->get_image('medium', ['class' => 'ws-card__image']); ?>
            </a>
            <h3 class="ws-card__title"><a href="<?php echo esc_url($p->get_permalink()); ?>" style="text-decoration:none; color:inherit;"><?php echo esc_html($p->get_name()); ?></a></h3>
            <div class="ws-card__rating">⭐⭐⭐⭐⭐ <span>(4.9)</span></div>
            <div class="ws-card__price-wrap"><span class="ws-card__price-sale"><?php echo wc_price($price); ?></span></div>
            <a href="<?php echo esc_url($p->get_permalink()); ?>" class="ws-card__btn">XEM CHI TIẾT</a>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

    </div>
  </section>

  <!-- 9. DUAL PROMO BANNERS -->
  <section class="ws-promo-banners">
    <div class="ws-container">
      <div class="ws-promo-grid">
        <a href="<?php echo esc_url(home_url('/danh-muc-san-pham/dong-ho-nam/')); ?>" class="ws-promo-card" style="background-image: url('<?php echo esc_url($hero_image); ?>');">
          <div class="ws-promo-content">
            <span>BẢN LĨNH PHÁI MẠNH</span>
            <h3>Bộ Sưu Tập Doanh Nhân Thụy Sĩ</h3>
            <span class="ws-promo-btn">Khám phá ngay →</span>
          </div>
        </a>
        <a href="<?php echo esc_url(home_url('/danh-muc-san-pham/dong-ho-nu/')); ?>" class="ws-promo-card" style="background-image: url('<?php echo esc_url($auction_image); ?>');">
          <div class="ws-promo-content">
            <span>QUYẾN RŨ &amp; TINH TẾ</span>
            <h3>Tuyệt Tác Nữ Diamond Halo</h3>
            <span class="ws-promo-btn">Mua trả góp 0% →</span>
          </div>
        </a>
      </div>
    </div>
  </section>

  <!-- 10. LIVE AUCTION SHOWCASE (EXCLUSIVE ATELIER) -->
  <?php if (!empty($featured_auctions)) : ?>
  <section class="ws-flash" style="background: #111827; color:#fff;">
    <div class="ws-container">
      <div class="ws-flash__head" style="background: linear-gradient(90deg, #d4af37 0%, #b45309 100%);">
        <div class="ws-flash__title-wrap">
          <h2 class="ws-flash__title" style="color:#0f172a;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0f172a" stroke-width="2" style="margin-right:8px; vertical-align:-3px;"><path d="M14 13l5 5m-4.5-8.5l4-4a2.121 2.121 0 0 1 3 3l-4 4m-3-3l-7 7a2 2 0 0 1-2.828 0l-1.414-1.414a2 2 0 0 1 0-2.828l7-7m3 3l-4.5 4.5"/><path d="M2 21h8"/></svg>
            SÀN ĐẤU GIÁ ĐỘC QUYỀN ATELIER
          </h2>
        </div>
        <a href="<?php echo esc_url(home_url('/dau-gia/')); ?>" class="ws-flash__link" style="background:#0f172a; color:#fde047;">Tất cả phiên đấu giá →</a>
      </div>
      <div class="ws-flash__body" style="background: #1f2937; border-color: #374151;">
        <div class="lw-auction-grid" style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
          <?php foreach ($featured_auctions as $auction_id) echo Luxury_Watches_Auctions::auction_card($auction_id); ?>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- 11. WATCH JOURNAL & EXPERT GUIDES -->
  <?php if (!empty($posts)) : ?>
  <section class="ws-brands" style="background:#f9fafb;">
    <div class="ws-container">
      <h2 class="ws-section-title">KIẾN THỨC &amp; KINH NGHIỆM ĐỒNG HỒ</h2>
      <p class="ws-section-desc">Cẩm nang phân biệt đồng hồ chính hãng, bảo dưỡng và xu hướng thời trang từ chuyên gia.</p>

      <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
        <?php 
        $article_fallbacks = [
            $asset_base . 'service-authentication.webp',
            $asset_base . 'journal-collector.webp',
            $asset_base . 'watchmaking-service.webp',
        ];
        foreach ($posts as $idx => $post) : setup_postdata($post); 
            $has_thumb = has_post_thumbnail($post);
            $fallback_thumb = $article_fallbacks[$idx % count($article_fallbacks)];
        ?>
        <article style="background:#fff; border-radius:10px; overflow:hidden; border:1px solid #e5e7eb; box-shadow:var(--ws-shadow);">
          <div style="aspect-ratio: 16/9; overflow:hidden; background:#111827;">
            <?php if ($has_thumb) : ?>
              <?php echo get_the_post_thumbnail($post, 'large', ['style' => 'width:100%; height:100%; object-fit:cover; display:block;']); ?>
            <?php else : ?>
              <img src="<?php echo esc_url($fallback_thumb); ?>" alt="<?php echo esc_attr(get_the_title($post)); ?>" style="width:100%; height:100%; object-fit:cover; display:block;" />
            <?php endif; ?>
          </div>
          <div style="padding: 16px;">
            <span style="font-size:11px; font-weight:700; color:var(--ws-primary); text-transform:uppercase;">Kiến thức chuyên sâu</span>
            <h3 style="font-size:15px; font-weight:800; color:#111827; margin:6px 0 10px; line-height:1.4;"><a href="<?php echo esc_url(get_permalink($post)); ?>" style="text-decoration:none; color:inherit;"><?php echo esc_html(get_the_title($post)); ?></a></h3>
            <p style="font-size:12.5px; color:#6b7280; line-height:1.5; margin-bottom:12px;"><?php echo wp_trim_words(get_the_excerpt($post), 16); ?></p>
            <a href="<?php echo esc_url(get_permalink($post)); ?>" style="color:var(--ws-primary); font-weight:700; font-size:12.5px; text-decoration:none;">Đọc tiếp bài viết →</a>
          </div>
        </article>
        <?php endforeach; wp_reset_postdata(); ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- 12. STORE NETWORK (SHOWROOMS) -->
  <section id="showrooms" class="ws-brands">
    <div class="ws-container">
      <h2 class="ws-section-title">HỆ THỐNG SHOWROOM TRẢI NGHIỆM THỰC TẾ</h2>
      <p class="ws-section-desc">Không gian sang trọng chuẩn 5 sao, sẵn sàng đón tiếp quý khách đến thử trực tiếp trên cổ tay.</p>

      <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
        <div style="background:#f9fafb; padding:20px; border-radius:10px; border:1px solid #e5e7eb;">
          <h4 style="font-size:15px; font-weight:800; color:var(--ws-primary); margin:0 0 8px;">SHOWROOM HÀ NỘI</h4>
          <p style="font-size:13px; color:#374151; margin-bottom:6px;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#e21134" stroke-width="2" style="margin-right:6px; vertical-align:-1px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>97 Trần Đại Nghĩa, Q. Hai Bà Trưng, Hà Nội</p>
          <p style="font-size:13px; color:#374151; margin-bottom:6px;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#e21134" stroke-width="2" style="margin-right:6px; vertical-align:-1px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>58 Trần Đăng Ninh, Q. Cầu Giấy, Hà Nội</p>
          <p style="font-size:12px; color:#6b7280; margin:0;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px; vertical-align:-1px;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>Hotline: 0988 123 456 (8:30 - 21:30)</p>
        </div>
        <div style="background:#f9fafb; padding:20px; border-radius:10px; border:1px solid #e5e7eb;">
          <h4 style="font-size:15px; font-weight:800; color:var(--ws-primary); margin:0 0 8px;">SHOWROOM TP. HỒ CHÍ MINH</h4>
          <p style="font-size:13px; color:#374151; margin-bottom:6px;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#e21134" stroke-width="2" style="margin-right:6px; vertical-align:-1px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>90 Lê Văn Sỹ, Phường 11, Quận Phú Nhuận</p>
          <p style="font-size:13px; color:#374151; margin-bottom:6px;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#e21134" stroke-width="2" style="margin-right:6px; vertical-align:-1px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>193 Lê Quang Định, Phường 7, Quận Bình Thạnh</p>
          <p style="font-size:12px; color:#6b7280; margin:0;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px; vertical-align:-1px;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>Hotline: 0988 123 456 (8:30 - 21:30)</p>
        </div>
        <div style="background:#f9fafb; padding:20px; border-radius:10px; border:1px solid #e5e7eb;">
          <h4 style="font-size:15px; font-weight:800; color:var(--ws-primary); margin:0 0 8px;">SHOWROOM ĐÀ NẴNG</h4>
          <p style="font-size:13px; color:#374151; margin-bottom:6px;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#e21134" stroke-width="2" style="margin-right:6px; vertical-align:-1px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>120 Nguyễn Văn Linh, Q. Hải Châu, Đà Nẵng</p>
          <p style="font-size:13px; color:#374151; margin-bottom:6px;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#e21134" stroke-width="2" style="margin-right:6px; vertical-align:-1px;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>Trung tâm bảo dưỡng &amp; giám định độc lập</p>
          <p style="font-size:12px; color:#6b7280; margin:0;"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px; vertical-align:-1px;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>Hotline: 0988 123 456 (8:30 - 21:30)</p>
        </div>
      </div>
    </div>
  </section>

  <!-- 13. COMPREHENSIVE E-COMMERCE FOOTER -->
  <footer class="ws-footer">
    <div class="ws-container">
      <div class="ws-footer-grid">
        <!-- Col 1: About -->
        <div class="ws-footer-col">
          <h4 style="color:#fde047;">WATCHSTORE ATELIER</h4>
          <p>Hệ thống phân phối đồng hồ chính hãng hàng đầu Việt Nam. Tự hào là đối tác chính ngạch của các thương hiệu đồng hồ Thụy Sĩ, Nhật Bản danh tiếng.</p>
          <div class="ws-footer-hotline">
            <span>TỔNG ĐÀI TƯ VẤN MIỄN PHÍ</span>
            <strong>0988 123 456</strong>
          </div>
          <p style="font-size:11.5px; color:#64748b;">Đã thông báo Bộ Công Thương · Giấy phép ĐKKD số: 0108923456</p>
        </div>

        <!-- Col 2: Showrooms -->
        <div class="ws-footer-col">
          <h4>HỆ THỐNG CỬA HÀNG</h4>
          <p><strong>Hà Nội:</strong> 97 Trần Đại Nghĩa &amp; 58 Trần Đăng Ninh</p>
          <p><strong>TP. HCM:</strong> 90 Lê Văn Sỹ &amp; 193 Lê Quang Định</p>
          <p><strong>Đà Nẵng:</strong> 120 Nguyễn Văn Linh, Hải Châu</p>
          <p><strong>Giờ phục vụ:</strong> 8:30 – 21:30 (Cả Thứ 7, Chủ Nhật &amp; Lễ)</p>
        </div>

        <!-- Col 3: Policies -->
        <div class="ws-footer-col">
          <h4>CHÍNH SÁCH BÁN HÀNG</h4>
          <ul>
            <li><a href="<?php echo esc_url(home_url('/chinh-sach-bao-hanh/')); ?>">Chính sách bảo hành 5 năm</a></li>
            <li><a href="<?php echo esc_url(home_url('/huong-dan-mua-hang/')); ?>">Chính sách đổi trả 7 ngày</a></li>
            <li><a href="<?php echo esc_url(home_url('/tieu-chuan-giao-nhan/')); ?>">Tiêu chuẩn giao nhận &amp; Freeship</a></li>
            <li><a href="<?php echo esc_url(home_url('/huong-dan-dau-gia/')); ?>">Quy chế tham gia đấu giá</a></li>
            <li><a href="<?php echo esc_url(home_url('/cau-hoi-thuong-gap/')); ?>">Câu hỏi thường gặp (FAQ)</a></li>
          </ul>
        </div>

        <!-- Col 4: Payments & Guarantee -->
        <div class="ws-footer-col">
          <h4>THANH TOÁN &amp; BẢO ĐẢM</h4>
          <p>Hỗ trợ thanh toán linh hoạt:</p>
          <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:14px; font-size:11.5px; font-weight:700;">
            <span style="background:#1f2937; padding:4px 10px; border-radius:4px; border:1px solid #374151; color:#f3f4f6;">VISA</span>
            <span style="background:#1f2937; padding:4px 10px; border-radius:4px; border:1px solid #374151; color:#f3f4f6;">MASTERCARD</span>
            <span style="background:#1f2937; padding:4px 10px; border-radius:4px; border:1px solid #374151; color:#f3f4f6;">VNPAY-QR</span>
            <span style="background:#1f2937; padding:4px 10px; border-radius:4px; border:1px solid #374151; color:#f3f4f6;">COD TẬN NƠI</span>
            <span style="background:#1f2937; padding:4px 10px; border-radius:4px; border:1px solid #374151; color:#fde047;">TRẢ GÓP 0%</span>
          </div>
          <p style="font-size:12px; color:#10b981; font-weight:600; display:flex; align-items:center; gap:6px; margin:0;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Chứng nhận bảo mật SSL 256-bit tiêu chuẩn quốc tế
          </p>
        </div>
      </div>

      <div class="ws-footer-bottom">
        <div>© 2026 WATCHSTORE LUXURY ATELIER. Bảo lưu mọi quyền.</div>
        <div>Phong cách thiết kế chuẩn trải nghiệm E-Commerce WatchStore.vn</div>
      </div>
    </div>
  </footer>

</div>

<!-- INTERACTIVE SCRIPTS: COUNTDOWN & TABS -->
<script>
function wsSwitchTab(evt, tabId) {
  var tabContents = document.querySelectorAll('.ws-tab-content');
  tabContents.forEach(function(content) {
    content.style.display = 'none';
  });
  var tabButtons = document.querySelectorAll('.ws-tab-btn');
  tabButtons.forEach(function(btn) {
    btn.classList.remove('active');
  });
  var activeContent = document.getElementById(tabId);
  if (activeContent) {
    activeContent.style.display = 'block';
  }
  evt.currentTarget.classList.add('active');
}

// Live Countdown Timer (Simulated 36 Hours Loop)
(function() {
  var targetTime = new Date().getTime() + (36 * 60 * 60 * 1000);
  function updateCountdown() {
    var now = new Date().getTime();
    var diff = targetTime - now;
    if (diff <= 0) {
      targetTime = new Date().getTime() + (24 * 60 * 60 * 1000);
      diff = targetTime - now;
    }
    var days = Math.floor(diff / (1000 * 60 * 60 * 24));
    var hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    var minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    var seconds = Math.floor((diff % (1000 * 60)) / 1000);

    var dEl = document.getElementById('wsDays');
    var hEl = document.getElementById('wsHours');
    var mEl = document.getElementById('wsMinutes');
    var sEl = document.getElementById('wsSeconds');

    if (dEl) dEl.innerText = days < 10 ? '0' + days : days;
    if (hEl) hEl.innerText = hours < 10 ? '0' + hours : hours;
    if (mEl) mEl.innerText = minutes < 10 ? '0' + minutes : minutes;
    if (sEl) sEl.innerText = seconds < 10 ? '0' + seconds : seconds;
  }
  updateCountdown();
  setInterval(updateCountdown, 1000);
})();
</script>

<?php get_footer();

<?php
/**
 * Luxury Watches Atelier - 404 Error Page Template
 * Haute Horlogerie Design System
 */

get_header();

// Fetch 4 featured products for recommendations
$curated_products = function_exists('wc_get_products') ? wc_get_products([
    'status'   => 'publish',
    'limit'    => 4,
    'orderby'  => 'date',
    'order'    => 'DESC'
]) : [];

// Fetch valid watch categories (hide empty)
$watch_categories = get_terms([
    'taxonomy'   => 'product_cat',
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
]);
?>

<div class="lw-404-page">
  <div class="ws-container">
    
    <!-- 1. HERO ERROR HEADER -->
    <header class="lw-404-header">
      <span class="lw-404-kicker">LUXURY WATCHES ATELIER · LỖI 404</span>
      <h1 class="lw-404-title">Trang không tìm thấy hoặc đã đổi địa chỉ</h1>
      <p class="lw-404-desc">Đường dẫn quý khách truy cập hiện không khả dụng. Quý khách có thể tìm kiếm mã hiệu, thương hiệu hoặc khám phá các bộ sưu tập đồng hồ chính hãng bên dưới.</p>

      <!-- Search Omnibox -->
      <div class="lw-404-search">
        <form role="search" method="get" class="lw-404-search__form" action="<?php echo esc_url(home_url('/')); ?>">
          <input type="search" class="lw-404-search__input" placeholder="Tìm theo tên đồng hồ, thương hiệu (Seiko, Citizen, Carnival, Tsar Bomba...)" value="" name="s" autocomplete="off" />
          <input type="hidden" name="post_type" value="product" />
          <button type="submit" class="lw-404-search__button">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Tìm kiếm
          </button>
        </form>
      </div>
    </header>

    <!-- 2. MAIN 2-COLUMN LUXURY SHOWCASE -->
    <div class="lw-404-grid">
      
      <!-- Left: Curated Watches -->
      <section class="lw-404-col lw-404-col--products">
        <div class="lw-404-col__head">
          <div>
            <span class="lw-404-badge">GỢI Ý NỔI BẬT</span>
            <h2 class="lw-404-heading">Mẫu đồng hồ được quan tâm nhất</h2>
          </div>
          <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="lw-404-link">Xem tất cả kho đồng hồ →</a>
        </div>

        <div class="lw-404-product-list">
          <?php foreach ($curated_products as $product) : 
              $pid = $product->get_id();
              $price_html = $product->get_price_html();
              $image = wp_get_attachment_image_url($product->get_image_id(), 'medium') ?: wc_placeholder_img_src();
          ?>
          <article class="lw-404-item">
            <a class="lw-404-item__thumb" href="<?php echo esc_url(get_permalink($pid)); ?>">
              <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($product->get_name()); ?>" loading="lazy" />
            </a>
            <div class="lw-404-item__info">
              <span class="lw-404-item__sku">CHÍNH HÃNG 100%</span>
              <h3 class="lw-404-item__title">
                <a href="<?php echo esc_url(get_permalink($pid)); ?>"><?php echo esc_html($product->get_name()); ?></a>
              </h3>
              <div class="lw-404-item__price"><?php echo wp_kses_post($price_html); ?></div>
              <a href="<?php echo esc_url(get_permalink($pid)); ?>" class="lw-404-item__btn">Xem chi tiết</a>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- Right: Refined Categories & Concierge -->
      <aside class="lw-404-col lw-404-col--sidebar">
        <div class="lw-404-sidebar-card">
          <div class="lw-404-col__head">
            <div>
              <span class="lw-404-badge">KHÁM PHÁ THEO HẠNG MỤC</span>
              <h2 class="lw-404-heading">Danh mục sản phẩm</h2>
            </div>
          </div>

          <ul class="lw-404-cats">
            <?php foreach ($watch_categories as $cat) : 
                $cat_link = get_term_link($cat);
            ?>
            <li class="lw-404-cats__item">
              <a href="<?php echo esc_url($cat_link); ?>" class="lw-404-cats__link">
                <span class="lw-404-cats__name">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lw-404-cats__icon"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>
                  <?php echo esc_html($cat->name); ?>
                </span>
                <span class="lw-404-cats__count"><?php echo esc_html($cat->count); ?> mẫu</span>
              </a>
            </li>
            <?php endforeach; ?>
            
            <li class="lw-404-cats__item lw-404-cats__item--auction">
              <a href="<?php echo esc_url(home_url('/dau-gia/')); ?>" class="lw-404-cats__link">
                <span class="lw-404-cats__name">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="lw-404-cats__icon"><path d="M14 13l5 5m-4.5-8.5l4-4a2.121 2.121 0 0 1 3 3l-4 4m-3-3l-7 7a2 2 0 0 1-2.828 0l-1.414-1.414a2 2 0 0 1 0-2.828l7-7m3 3l-4.5 4.5"/><path d="M2 21h8"/></svg>
                  Sàn đấu giá đồng hồ tuyển chọn
                </span>
                <span class="lw-404-cats__count lw-404-cats__count--gold">Live</span>
              </a>
            </li>
          </ul>
        </div>

        <!-- VIP Concierge Support Card -->
        <div class="lw-404-concierge">
          <div class="lw-404-concierge__icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#C5A059" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          </div>
          <div>
            <strong>Cần tư vấn tìm mẫu theo yêu cầu?</strong>
            <p>Chuyên viên giám tuyển đồng hồ luôn sẵn sàng hỗ trợ quý khách.</p>
            <a href="tel:0988123456" class="lw-404-concierge__tel">Hotline VIP: 0988 123 456</a>
          </div>
        </div>

      </aside>

    </div>
  </div>
</div>

<?php
get_footer();

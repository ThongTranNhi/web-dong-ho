<?php
if (!defined('ABSPATH')) exit;
$value = static function ($key, $default = '') use ($values) {
    return isset($values[$key]) && is_scalar($values[$key]) ? (string) $values[$key] : $default;
};
$user = wp_get_current_user();
$selected = (int) $value('_lw_product_id');
$selected_product = $selected ? wc_get_product($selected) : false;
$preview_image = $selected_product ? wp_get_attachment_image_url($selected_product->get_image_id(), 'medium') : '';
$now = current_datetime();
$created = absint($_GET['created'] ?? 0);
$created_post = $created ? get_post($created) : null;
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Quản trị đấu giá · LW Atelier</title>
  <link rel="stylesheet" href="<?php echo esc_url($base . 'dashboard.css?ver=' . filemtime(dirname(__DIR__) . '/assets/dashboard.css')); ?>">
  <script src="<?php echo esc_url($base . 'dashboard.js?ver=' . filemtime(dirname(__DIR__) . '/assets/dashboard.js')); ?>" defer></script>
</head>
<body>
<a class="skip" href="#dashboard-content">Đến nội dung chính</a>
<div class="dashboard">
  <aside class="sidebar">
    <a class="brand" href="<?php echo esc_url(self::url()); ?>"><span class="brand-mark">LW</span><span>Atelier<small>Quản trị đấu giá</small></span></a>
    <nav aria-label="Điều hướng quản trị">
      <a href="<?php echo esc_url(self::url()); ?>" <?php if ($view === 'list') echo 'aria-current="page"'; ?>>Danh sách phiên</a>
      <a href="<?php echo esc_url(add_query_arg('view', 'new', self::url())); ?>" <?php if ($view === 'new') echo 'aria-current="page"'; ?>>Tạo phiên đấu giá</a>
      <a href="<?php echo esc_url(home_url('/dau-gia/')); ?>" target="_blank" rel="noopener">Xem sàn đấu giá</a>
    </nav>
    <div class="sidebar-note"><strong>Một phiên, một lựa chọn.</strong><p>Thông tin rõ ràng giúp khách hàng tự tin tham gia.</p></div>
    <a class="back-store" href="<?php echo esc_url(home_url('/')); ?>">Về cửa hàng</a>
  </aside>
  <div class="workspace">
    <header class="topbar"><span>Không gian quản trị / Đấu giá</span><div><span class="user-name"><?php echo esc_html($user->display_name); ?></span><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Đăng xuất</a></div></header>
    <main id="dashboard-content">
      <header class="page-heading">
        <div><p class="eyebrow">Luxury Watches</p><h1><?php echo $view === 'new' ? 'Tạo phiên đấu giá' : 'Các phiên đấu giá'; ?></h1><p><?php echo $view === 'new' ? 'Chọn đồng hồ, thiết lập mức giá và sẵn sàng đón lượt trả đầu tiên.' : 'Theo dõi các phiên đã đăng và chuẩn bị những phiên tiếp theo.'; ?></p></div>
        <?php if ($view === 'list') : ?><a class="button" href="<?php echo esc_url(add_query_arg('view', 'new', self::url())); ?>">+ Tạo phiên mới</a><?php endif; ?>
      </header>
      <?php if ($error) : ?><div class="notice error" role="alert"><?php echo esc_html($error); ?></div><?php endif; ?>
      <?php if ($created_post && $created_post->post_type === 'watch_auction' && current_user_can('edit_post', $created)) : ?>
        <div class="notice success" role="status">Đã lưu phiên “<?php echo esc_html($created_post->post_title); ?>”. <?php if ($created_post->post_status === 'publish') : ?><a href="<?php echo esc_url(get_permalink($created)); ?>">Xem trên website</a><?php else : ?>Phiên đang ở trạng thái nháp. <a href="<?php echo esc_url(get_preview_post_link($created)); ?>">Xem trước</a><?php endif; ?></div>
      <?php endif; ?>

      <?php if ($view === 'new') : ?>
      <form method="post" enctype="multipart/form-data" class="create-layout" id="auction-form">
        <?php wp_nonce_field('lwa_dashboard_create', 'lwa_nonce'); ?>
        <input type="hidden" name="submission" value="<?php echo esc_attr($submission); ?>">
        <div class="form-sections">
          <section class="panel" aria-labelledby="product-heading">
            <div class="section-heading"><span class="step">1</span><div><h2 id="product-heading">Chọn đồng hồ</h2><p>Dùng sản phẩm có sẵn trong cửa hàng.</p></div></div>
            <label for="product-search">Tìm theo tên hoặc SKU</label>
            <input id="product-search" type="search" placeholder="Ví dụ: Aurelius hoặc LW-AUC" autocomplete="off">
            <label for="auction-product">Sản phẩm liên kết <span aria-hidden="true">*</span></label>
            <select id="auction-product" name="auction[_lw_product_id]" required>
              <option value="">Chọn một sản phẩm</option>
              <?php foreach ($products as $product) : ?>
                <option value="<?php echo esc_attr($product->get_id()); ?>" <?php selected($selected, $product->get_id()); ?> data-title="<?php echo esc_attr($product->get_name()); ?>" data-price="<?php echo esc_attr((int) $product->get_regular_price()); ?>" data-image="<?php echo esc_url(wp_get_attachment_image_url($product->get_image_id(), 'medium') ?: ''); ?>"><?php echo esc_html($product->get_name() . ($product->get_sku() ? ' · ' . $product->get_sku() : '')); ?></option>
              <?php endforeach; ?>
            </select>
            <p class="help" id="product-search-status" aria-live="polite">Chọn sản phẩm để lấy tên, giá niêm yết và ảnh có sẵn.</p>
          </section>
          <section class="panel" aria-labelledby="details-heading">
            <div class="section-heading"><span class="step">2</span><div><h2 id="details-heading">Thông tin phiên</h2><p>Những gì khách hàng sẽ thấy trên sàn đấu giá.</p></div></div>
            <label for="auction-title">Tên phiên <span aria-hidden="true">*</span></label>
            <input id="auction-title" name="auction[title]" value="<?php echo esc_attr($value('title')); ?>" required maxlength="200" placeholder="Nhập tên đồng hồ hoặc tên phiên">
            <div class="field-grid">
              <div><label for="auction-lot">Mã phiên <span aria-hidden="true">*</span></label><input id="auction-lot" name="auction[_lw_lot_number]" value="<?php echo esc_attr($value('_lw_lot_number', 'LOT-' . $now->format('ymd') . '-' . strtoupper(substr($submission, 0, 4)))); ?>" required></div>
              <div><label for="auction-gender">Phân loại</label><select id="auction-gender" name="auction[_lw_gender]"><?php foreach (['Đồng hồ nam', 'Đồng hồ nữ', 'Thiết kế unisex'] as $gender) : ?><option <?php selected($value('_lw_gender', 'Đồng hồ nam'), $gender); ?>><?php echo esc_html($gender); ?></option><?php endforeach; ?></select></div>
            </div>
            <label for="auction-condition">Tình trạng sản phẩm</label><input id="auction-condition" name="auction[_lw_condition]" value="<?php echo esc_attr($value('_lw_condition')); ?>" placeholder="Ví dụ: Mới, đầy đủ hộp và giấy tờ">
            <label for="auction-description">Mô tả</label><textarea id="auction-description" name="auction[description]" rows="5" placeholder="Thông số nổi bật, phụ kiện đi kèm và những lưu ý khi tham gia."><?php echo esc_textarea($value('description')); ?></textarea>
            <label for="auction-image">Ảnh đại diện riêng</label><input id="auction-image" type="file" name="auction_image" accept="image/jpeg,image/png,image/webp"><p class="help">JPG, PNG hoặc WebP, tối đa <?php echo esc_html(size_format(wp_max_upload_size())); ?>. Bỏ trống để dùng ảnh sản phẩm. Khi biểu mẫu báo lỗi, hãy chọn lại file ảnh.</p>
          </section>
          <section class="panel" aria-labelledby="price-heading">
            <div class="section-heading"><span class="step">3</span><div><h2 id="price-heading">Mức giá và thời gian</h2><p>Đơn vị VND, nhập số nguyên. Múi giờ <?php echo esc_html(wp_timezone_string()); ?>.</p></div></div>
            <div class="field-grid">
              <?php foreach (['_lw_retail_price' => ['Giá niêm yết', 'retail', 0], '_lw_start_price' => ['Giá khởi điểm *', 'start-price', 1], '_lw_bid_increment' => ['Bước giá *', 'increment', 1], '_lw_reserve_price' => ['Giá dự trữ', 'reserve', 0]] as $key => [$label, $id, $minimum]) : ?>
              <div><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label><input type="number" inputmode="numeric" id="<?php echo esc_attr($id); ?>" name="auction[<?php echo esc_attr($key); ?>]" min="<?php echo esc_attr($minimum); ?>" step="1" value="<?php echo esc_attr($value($key, $minimum ? '' : '0')); ?>" <?php if ($minimum) echo 'required'; ?>></div>
              <?php endforeach; ?>
              <div><label for="auction-start">Bắt đầu *</label><input id="auction-start" type="datetime-local" name="auction[_lw_start_at]" value="<?php echo esc_attr($value('_lw_start_at', $now->modify('+1 hour')->format('Y-m-d\TH:i'))); ?>" required></div>
              <div><label for="auction-end">Kết thúc *</label><input id="auction-end" type="datetime-local" name="auction[_lw_end_at]" value="<?php echo esc_attr($value('_lw_end_at', $now->modify('+3 days')->format('Y-m-d\TH:i'))); ?>" required></div>
            </div>
            <p class="help">Giá dự trữ là ngưỡng để xác nhận người thắng, không hiển thị số tiền này cho khách hàng. Nhập 0 nếu không áp dụng.</p>
          </section>
        </div>
        <aside class="publish-panel">
          <section class="panel preview"><p class="eyebrow">Xem nhanh sản phẩm</p><div class="preview-image"><img id="preview-image" src="<?php echo esc_url($preview_image ?: ''); ?>" alt="Đồng hồ liên kết" <?php if (!$preview_image) echo 'hidden'; ?>><span id="preview-placeholder" <?php if ($preview_image) echo 'hidden'; ?>>Chọn đồng hồ để xem ảnh</span></div><h2 id="preview-title"><?php echo esc_html($selected_product ? $selected_product->get_name() : 'Phiên đấu giá mới'); ?></h2><p class="help">Giá khởi điểm</p><strong class="preview-price" id="preview-price">—</strong></section>
          <section class="panel publish-box"><h2>Sẵn sàng công bố?</h2><p class="help">Kiểm tra sản phẩm, ảnh và giờ kết thúc trước khi đăng. Khách chỉ trả giá khi phiên đang mở.</p><button class="button" type="submit" name="auction[status]" value="publish">Đăng phiên đấu giá</button><button class="button secondary" type="submit" name="auction[status]" value="draft">Lưu nháp</button><p class="help submit-status" role="status"></p></section>
        </aside>
      </form>
      <?php else : ?>
      <section class="panel auction-list" aria-label="Danh sách phiên đấu giá">
        <div class="list-heading"><h2>Phiên đã tạo</h2><span><?php echo esc_html($query->found_posts); ?> phiên</span></div>
        <?php if ($query->posts) : ?>
          <div class="table-wrap"><table><thead><tr><th>Sản phẩm / phiên</th><th>Trạng thái</th><th>Giá khởi điểm</th><th>Kết thúc</th><th>Thao tác</th></tr></thead><tbody>
          <?php foreach ($query->posts as $auction) : $state = $auction->post_status === 'draft' ? 'draft' : Luxury_Watches_Auctions::state($auction->ID); $labels = ['draft' => 'Bản nháp', 'live' => 'Đang diễn ra', 'upcoming' => 'Sắp mở', 'ended' => 'Đã kết thúc', 'invalid' => 'Cần cấu hình']; $thumbnail = get_the_post_thumbnail_url($auction->ID, 'thumbnail'); ?>
            <tr><td><div class="auction-name"><?php if ($thumbnail) : ?><img src="<?php echo esc_url($thumbnail); ?>" alt=""><?php endif; ?><div><strong><?php echo esc_html($auction->post_title); ?></strong><small><?php echo esc_html(get_post_meta($auction->ID, '_lw_lot_number', true)); ?></small></div></div></td><td><span class="badge <?php echo esc_attr($state); ?>"><?php echo esc_html($labels[$state] ?? 'Chưa công bố'); ?></span></td><td><?php echo esc_html(number_format((int) get_post_meta($auction->ID, '_lw_start_price', true), 0, ',', '.')); ?> ₫</td><td><?php echo esc_html(str_replace('T', ' ', get_post_meta($auction->ID, '_lw_end_at', true))); ?></td><td><a href="<?php echo esc_url($auction->post_status === 'publish' ? get_permalink($auction) : get_preview_post_link($auction)); ?>">Xem phiên</a><a class="edit-link" href="<?php echo esc_url(get_edit_post_link($auction->ID)); ?>">Chỉnh sửa</a></td></tr>
          <?php endforeach; ?>
          </tbody></table></div>
          <nav class="pagination" aria-label="Trang danh sách"><?php echo wp_kses_post(paginate_links(['base' => add_query_arg('paged', '%#%', self::url()), 'total' => $query->max_num_pages, 'current' => max(1, absint($_GET['paged'] ?? 1)), 'prev_text' => 'Trước', 'next_text' => 'Tiếp'])); ?></nav>
        <?php else : ?><div class="empty"><h3>Bắt đầu phiên đấu giá đầu tiên</h3><p>Chọn một sản phẩm và thiết lập lịch mở để khách hàng tham gia.</p><a class="button" href="<?php echo esc_url(add_query_arg('view', 'new', self::url())); ?>">Tạo phiên mới</a></div><?php endif; ?>
      </section>
      <?php endif; ?>
    </main>
  </div>
</div>
</body>
</html>

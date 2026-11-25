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
$pvalue = static function ($key, $default = '') use ($product_values) {
    return isset($product_values[$key]) && is_scalar($product_values[$key]) ? (string) $product_values[$key] : $default;
};
$auction_collection = get_term_by('slug', 'bo-suu-tap-dau-gia', 'product_cat');
$checked_collections = isset($product_values['collections']) ? array_map('intval', (array) $product_values['collections']) : ($auction_collection ? [(int) $auction_collection->term_id] : []);
$product_created = absint($_GET['product_created'] ?? 0);
$new_product = ($product_created && current_user_can('edit_post', $product_created)) ? wc_get_product($product_created) : null;
$headings = [
    'list' => ['Các phiên đấu giá', 'Theo dõi các phiên đã đăng và chuẩn bị những phiên tiếp theo.'],
    'new' => ['Tạo phiên đấu giá', 'Chọn đồng hồ, thiết lập mức giá và sẵn sàng đón lượt trả đầu tiên.'],
    'new-product' => ['Thêm sản phẩm', 'Đưa một chiếc đồng hồ mới vào cửa hàng, sau đó có thể mở phiên đấu giá cho nó.'],
    'products' => ['Sản phẩm', 'Các đồng hồ đang có trong cửa hàng. Sản phẩm bị xóa được chuyển vào thùng rác và có thể khôi phục.'],
    'orders' => ['Đơn hàng', 'Theo dõi thanh toán, tìm đơn và xử lý khi khách hàng cần hỗ trợ.'],
    'order' => ['Chi tiết đơn hàng', 'Kiểm tra đầy đủ thông tin trước khi xác nhận thanh toán hoặc đổi trạng thái.'],
];
$trashed_id = absint($_GET['trashed'] ?? 0);
$trashed_post = $trashed_id ? get_post($trashed_id) : null;
$restored_id = absint($_GET['restored'] ?? 0);
$restored_post = $restored_id ? get_post($restored_id) : null;
$status_labels = ['publish' => 'Đang bán', 'draft' => 'Bản nháp', 'pending' => 'Chờ duyệt', 'private' => 'Riêng tư'];
// Keeps search and page when an action returns to the list.
$keep_fields = static function () use ($product_search, $product_page) {
    if ($product_search !== '') echo '<input type="hidden" name="lwa_q" value="' . esc_attr($product_search) . '">';
    if ($product_page > 1) echo '<input type="hidden" name="lwa_page" value="' . esc_attr($product_page) . '">';
};
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
      <a href="<?php echo esc_url(add_query_arg('view', 'products', self::url())); ?>" <?php if ($view === 'products') echo 'aria-current="page"'; ?>>Sản phẩm</a>
      <a href="<?php echo esc_url(add_query_arg('view', 'new-product', self::url())); ?>" <?php if ($view === 'new-product') echo 'aria-current="page"'; ?>>Thêm sản phẩm</a>
      <a href="<?php echo esc_url(add_query_arg('view', 'orders', self::url())); ?>" <?php if (in_array($view, ['orders', 'order'], true)) echo 'aria-current="page"'; ?>>Đơn hàng</a>
      <a href="<?php echo esc_url(home_url('/dau-gia/')); ?>" target="_blank" rel="noopener">Xem sàn đấu giá</a>
    </nav>
    <div class="sidebar-note"><strong>Một phiên, một lựa chọn.</strong><p>Thông tin rõ ràng giúp khách hàng tự tin tham gia.</p></div>
    <a class="back-store" href="<?php echo esc_url(home_url('/')); ?>">Về cửa hàng</a>
  </aside>
  <div class="workspace">
    <header class="topbar"><span>Không gian quản trị / Đấu giá</span><div><span class="user-name"><?php echo esc_html($user->display_name); ?></span><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">Đăng xuất</a></div></header>
    <main id="dashboard-content">
      <header class="page-heading">
        <div><p class="eyebrow">Luxury Watches</p><h1><?php echo esc_html($headings[$view][0]); ?></h1><p><?php echo esc_html($headings[$view][1]); ?></p></div>
        <?php if ($view === 'list') : ?><a class="button" href="<?php echo esc_url(add_query_arg('view', 'new', self::url())); ?>">+ Tạo phiên mới</a><?php endif; ?>
        <?php if ($view === 'products') : ?><a class="button" href="<?php echo esc_url(add_query_arg('view', 'new-product', self::url())); ?>">+ Thêm sản phẩm</a><?php endif; ?>
        <?php if ($view === 'order') : ?><a class="button secondary" href="<?php echo esc_url(add_query_arg('view', 'orders', self::url())); ?>">← Danh sách đơn</a><?php endif; ?>
      </header>
      <?php if ($trashed_post && $trashed_post->post_type === 'product' && $trashed_post->post_status === 'trash' && current_user_can('delete_post', $trashed_id)) : ?>
        <div class="notice success" role="status">
          Đã xóa sản phẩm “<?php echo esc_html($trashed_post->post_title); ?>” (chuyển vào thùng rác).
          <form method="post" class="inline-form">
            <?php wp_nonce_field('lwa_restore_product_' . $trashed_id, 'lwa_nonce'); ?>
            <input type="hidden" name="lwa_form" value="restore_product"><input type="hidden" name="lwa_product_id" value="<?php echo esc_attr($trashed_id); ?>"><?php $keep_fields(); ?>
            <button type="submit" class="link-button">Hoàn tác</button>
          </form>
        </div>
      <?php endif; ?>
      <?php if ($restored_post && $restored_post->post_type === 'product' && $restored_post->post_status !== 'trash') : ?>
        <div class="notice success" role="status">Đã khôi phục sản phẩm “<?php echo esc_html($restored_post->post_title); ?>”.</div>
      <?php endif; ?>
      <?php if ($error) : ?><div class="notice error" role="alert"><?php echo esc_html($error); ?></div><?php endif; ?>
      <?php if ($created_post && $created_post->post_type === 'watch_auction' && current_user_can('edit_post', $created)) : ?>
        <div class="notice success" role="status">Đã lưu phiên “<?php echo esc_html($created_post->post_title); ?>”. <?php if ($created_post->post_status === 'publish') : ?><a href="<?php echo esc_url(get_permalink($created)); ?>">Xem trên website</a><?php else : ?>Phiên đang ở trạng thái nháp. <a href="<?php echo esc_url(get_preview_post_link($created)); ?>">Xem trước</a><?php endif; ?></div>
      <?php endif; ?>

      <?php if ($new_product) : ?>
        <div class="notice success" role="status">
          Đã lưu sản phẩm “<?php echo esc_html($new_product->get_name()); ?>”<?php echo $new_product->get_status() === 'publish' ? ' và đăng lên cửa hàng.' : ' ở trạng thái nháp.'; ?>
          <span class="notice-actions">
            <?php if ($new_product->get_status() === 'publish') : ?>
              <a class="button" href="<?php echo esc_url(add_query_arg(['view' => 'new', 'lwa_product' => $new_product->get_id()], self::url())); ?>">Tạo phiên đấu giá cho sản phẩm này</a>
              <a href="<?php echo esc_url(get_permalink($new_product->get_id())); ?>" target="_blank" rel="noopener">Xem trên cửa hàng</a>
            <?php else : ?>
              <span>Đăng sản phẩm trước khi tạo phiên đấu giá.</span>
            <?php endif; ?>
            <a href="<?php echo esc_url(get_edit_post_link($new_product->get_id())); ?>">Chỉnh sửa chi tiết</a>
          </span>
        </div>
      <?php endif; ?>

      <?php if (!empty($_GET['order_updated']) && $managed_order) : ?>
        <div class="notice success" role="status">Đã cập nhật đơn hàng #<?php echo esc_html($managed_order->get_order_number()); ?> và lưu lịch sử thao tác.</div>
      <?php endif; ?>

      <?php if ($view === 'orders') : ?>
      <section class="panel auction-list order-list" aria-label="Danh sách đơn hàng">
        <div class="list-heading">
          <h2>Đơn hàng WooCommerce</h2>
          <span><?php echo esc_html((int) $order_query->total); ?> đơn</span>
        </div>
        <form method="get" action="<?php echo esc_url(self::url()); ?>" class="order-filters" role="search">
          <input type="hidden" name="view" value="orders">
          <div><label for="order-q">Tìm đơn hàng</label><input id="order-q" type="search" name="order_q" value="<?php echo esc_attr($order_search); ?>" placeholder="Mã đơn, tên, email hoặc số điện thoại"></div>
          <div><label for="order-status">Trạng thái</label><select id="order-status" name="order_status"><option value="">Tất cả trạng thái</option><?php foreach ($order_statuses as $key => $label) : $slug = str_replace('wc-', '', $key); ?><option value="<?php echo esc_attr($slug); ?>" <?php selected($order_status, $slug); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div>
          <button class="button" type="submit">Lọc đơn</button>
          <?php if ($order_search !== '' || $order_status !== '') : ?><a class="button secondary" href="<?php echo esc_url(add_query_arg('view', 'orders', self::url())); ?>">Xóa lọc</a><?php endif; ?>
        </form>
        <?php if ($order_query->orders) : ?>
          <div class="table-wrap"><table><thead><tr><th>Đơn hàng</th><th>Khách hàng</th><th>Trạng thái</th><th>Tổng tiền</th><th>Thanh toán</th><th>Ngày tạo</th><th>Thao tác</th></tr></thead><tbody>
          <?php foreach ($order_query->orders as $item_order) : ?>
            <tr>
              <td><strong>#<?php echo esc_html($item_order->get_order_number()); ?></strong><small class="table-sub"><?php echo esc_html($item_order->get_item_count()); ?> sản phẩm</small></td>
              <td><strong><?php echo esc_html($item_order->get_formatted_billing_full_name() ?: 'Khách vãng lai'); ?></strong><small class="table-sub"><?php echo esc_html($item_order->get_billing_email()); ?></small><small class="table-sub"><?php echo esc_html($item_order->get_billing_phone()); ?></small></td>
              <td><span class="badge order-status status-<?php echo esc_attr($item_order->get_status()); ?>"><?php echo esc_html($order_statuses[$item_order->get_status()] ?? wc_get_order_status_name($item_order->get_status())); ?></span><?php if ($item_order->is_paid()) : ?><small class="paid-flag">Đã ghi nhận thanh toán</small><?php endif; ?></td>
              <td><?php echo wp_kses_post($item_order->get_formatted_order_total()); ?></td>
              <td><?php echo esc_html($item_order->get_payment_method_title() ?: 'Chưa chọn'); ?></td>
              <td><?php echo esc_html($item_order->get_date_created() ? $item_order->get_date_created()->date_i18n('d/m/Y H:i') : '—'); ?></td>
              <td><a class="button secondary compact" href="<?php echo esc_url(add_query_arg(['view' => 'order', 'order_id' => $item_order->get_id()], self::url())); ?>">Xem chi tiết</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody></table></div>
          <nav class="pagination" aria-label="Trang danh sách đơn hàng"><?php echo wp_kses_post(paginate_links(['base' => add_query_arg(array_filter(['view' => 'orders', 'order_q' => $order_search, 'order_status' => $order_status, 'order_page' => '%#%']), self::url()), 'format' => '', 'total' => $order_query->max_num_pages, 'current' => $order_page, 'prev_text' => 'Trước', 'next_text' => 'Tiếp'])); ?></nav>
        <?php else : ?><div class="empty"><h3>Không tìm thấy đơn hàng</h3><p>Thử bỏ bộ lọc hoặc tìm bằng thông tin khác.</p></div><?php endif; ?>
      </section>

      <?php elseif ($view === 'order') : ?>
        <?php if ($managed_order) : $order_notes = wc_get_order_notes(['order_id' => $managed_order->get_id(), 'type' => 'internal']); ?>
        <div class="order-detail-grid">
          <div class="order-detail-main">
            <section class="panel order-overview">
              <div class="order-title-row"><div><p class="eyebrow">Đơn hàng</p><h2>#<?php echo esc_html($managed_order->get_order_number()); ?></h2></div><span class="badge order-status status-<?php echo esc_attr($managed_order->get_status()); ?>"><?php echo esc_html($order_statuses[$managed_order->get_status()] ?? wc_get_order_status_name($managed_order->get_status())); ?></span></div>
              <div class="order-meta-grid">
                <div><span>Khách hàng</span><strong><?php echo esc_html($managed_order->get_formatted_billing_full_name() ?: 'Khách vãng lai'); ?></strong><a href="mailto:<?php echo esc_attr($managed_order->get_billing_email()); ?>"><?php echo esc_html($managed_order->get_billing_email()); ?></a><a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $managed_order->get_billing_phone())); ?>"><?php echo esc_html($managed_order->get_billing_phone()); ?></a></div>
                <div><span>Thanh toán</span><strong><?php echo esc_html($managed_order->get_payment_method_title() ?: 'Chưa chọn phương thức'); ?></strong><small><?php echo $managed_order->is_paid() ? 'Đã ghi nhận thanh toán' : 'Chưa ghi nhận thanh toán'; ?></small><?php if ($managed_order->get_transaction_id()) : ?><small>Mã GD: <?php echo esc_html($managed_order->get_transaction_id()); ?></small><?php endif; ?></div>
                <div><span>Ngày tạo</span><strong><?php echo esc_html($managed_order->get_date_created() ? $managed_order->get_date_created()->date_i18n('d/m/Y H:i') : '—'); ?></strong><small>Tiền tệ: <?php echo esc_html($managed_order->get_currency()); ?></small></div>
              </div>
            </section>
            <section class="panel"><h2>Sản phẩm</h2><div class="table-wrap"><table class="order-items"><thead><tr><th>Sản phẩm</th><th>Số lượng</th><th>Thành tiền</th></tr></thead><tbody><?php foreach ($managed_order->get_items() as $order_item) : ?><tr><td><strong><?php echo esc_html($order_item->get_name()); ?></strong><?php if ($order_item->get_product()) : ?><small class="table-sub"><?php echo esc_html($order_item->get_product()->get_sku()); ?></small><?php endif; ?></td><td><?php echo esc_html($order_item->get_quantity()); ?></td><td><?php echo wp_kses_post($managed_order->get_formatted_line_subtotal($order_item)); ?></td></tr><?php endforeach; ?></tbody><tfoot><tr><th colspan="2">Tổng cộng</th><td><strong><?php echo wp_kses_post($managed_order->get_formatted_order_total()); ?></strong></td></tr></tfoot></table></div></section>
            <section class="panel"><h2>Lịch sử đơn hàng</h2><?php if ($order_notes) : ?><ol class="order-timeline"><?php foreach ($order_notes as $note) : ?><li><p><?php echo wp_kses_post(wpautop($note->content)); ?></p><time><?php echo esc_html(wp_date('d/m/Y H:i', strtotime($note->date_created))); ?></time></li><?php endforeach; ?></ol><?php else : ?><p class="help">Chưa có ghi chú nội bộ.</p><?php endif; ?></section>
          </div>
          <aside class="order-actions-panel">
            <form method="post" class="panel js-order-update" data-order-number="<?php echo esc_attr($managed_order->get_order_number()); ?>">
              <?php wp_nonce_field('lwa_order_update_' . $managed_order->get_id(), 'lwa_nonce'); ?>
              <input type="hidden" name="lwa_form" value="order_update"><input type="hidden" name="lwa_order_id" value="<?php echo esc_attr($managed_order->get_id()); ?>">
              <h2>Xử lý đơn hàng</h2>
              <label for="order-new-status">Trạng thái mới</label>
              <?php $editable_order_statuses = ['pending', 'on-hold', 'processing', 'completed', 'cancelled', 'failed']; ?>
              <select id="order-new-status" name="order[status]"><?php if (!in_array($managed_order->get_status(), $editable_order_statuses, true)) : ?><option value="" disabled selected>Chọn trạng thái mới</option><?php endif; ?><?php foreach ($editable_order_statuses as $slug) : ?><option value="<?php echo esc_attr($slug); ?>" <?php selected($managed_order->get_status(), $slug); ?>><?php echo esc_html($order_statuses[$slug]); ?></option><?php endforeach; ?></select>
              <label for="order-transaction">Mã giao dịch <span class="label-note">(không bắt buộc)</span></label><input id="order-transaction" name="order[transaction_id]" value="<?php echo esc_attr($managed_order->get_transaction_id()); ?>" maxlength="100">
              <label for="order-reason">Lý do thay đổi *</label><textarea id="order-reason" name="order[reason]" rows="4" minlength="5" required placeholder="Ví dụ: Đã kiểm tra giao dịch trên SePay và xác nhận khách đã thanh toán."></textarea>
              <button class="button secondary order-status-submit" type="submit" name="order[action]" value="status">Cập nhật trạng thái</button>
              <button class="button order-paid-submit" type="submit" name="order[action]" value="mark_paid" <?php if ($managed_order->is_paid()) echo 'disabled'; ?>><?php echo $managed_order->is_paid() ? 'Đơn đã thanh toán' : 'Xác nhận đã thanh toán'; ?></button>
              <p class="help warning"><strong>Lưu ý:</strong> xác nhận đã thanh toán chỉ cập nhật WooCommerce. Hệ thống không tự thu tiền. Không dùng đổi trạng thái để xử lý hoàn tiền thật.</p>
            </form>
          </aside>
        </div>
        <?php else : ?><div class="empty panel"><h3>Không thể mở đơn hàng</h3><p>Đơn không tồn tại hoặc bạn không có quyền truy cập.</p><a class="button secondary" href="<?php echo esc_url(add_query_arg('view', 'orders', self::url())); ?>">Về danh sách đơn</a></div><?php endif; ?>

      <?php elseif ($view === 'new-product') : ?>
      <form method="post" enctype="multipart/form-data" class="create-layout" id="product-form">
        <?php wp_nonce_field('lwa_dashboard_product', 'lwa_nonce'); ?>
        <input type="hidden" name="lwa_form" value="product">
        <input type="hidden" name="submission" value="<?php echo esc_attr($submission); ?>">
        <div class="form-sections">
          <section class="panel" aria-labelledby="p-info-heading">
            <div class="section-heading"><span class="step">1</span><div><h2 id="p-info-heading">Thông tin đồng hồ</h2><p>Tên và mô tả khách hàng sẽ thấy trên trang sản phẩm.</p></div></div>
            <label for="p-name">Tên sản phẩm <span aria-hidden="true">*</span></label>
            <input id="p-name" name="lwa_product[name]" value="<?php echo esc_attr($pvalue('name')); ?>" required maxlength="200" placeholder="Ví dụ: Aurelius Deepsea GMT">
            <div class="field-grid">
              <div><label for="p-sku">SKU</label><input id="p-sku" name="lwa_product[sku]" value="<?php echo esc_attr($pvalue('sku')); ?>" maxlength="64" pattern="[A-Za-z0-9._\-]+" placeholder="Ví dụ: LW-AUC-GMT-02" aria-describedby="p-sku-help"></div>
              <div><label for="p-gender">Phân loại</label><select id="p-gender" name="lwa_product[gender]"><?php foreach (['dong-ho-nam' => 'Đồng hồ nam', 'dong-ho-nu' => 'Đồng hồ nữ', 'unisex' => 'Thiết kế unisex'] as $slug => $label) : ?><option value="<?php echo esc_attr($slug); ?>" <?php selected($pvalue('gender', 'dong-ho-nam'), $slug); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div>
            </div>
            <p class="help" id="p-sku-help">Mã nội bộ, không trùng với sản phẩm khác. Có thể bỏ trống.</p>
            <label for="p-short">Mô tả ngắn</label><textarea id="p-short" name="lwa_product[short_description]" rows="2" placeholder="Một hai câu nêu điểm nổi bật, hiển thị cạnh giá."><?php echo esc_textarea($pvalue('short_description')); ?></textarea>
            <label for="p-desc">Mô tả chi tiết</label><textarea id="p-desc" name="lwa_product[description]" rows="5" placeholder="Kích thước vỏ (mm), bộ máy, chất liệu, chống nước, phụ kiện đi kèm."><?php echo esc_textarea($pvalue('description')); ?></textarea>
            <p class="help">Ghi kích thước vỏ theo dạng “Vỏ thép 40 mm” để chatbot tư vấn đúng theo kích thước.</p>
          </section>
          <section class="panel" aria-labelledby="p-price-heading">
            <div class="section-heading"><span class="step">2</span><div><h2 id="p-price-heading">Giá và tồn kho</h2><p>Đơn vị VND, nhập số nguyên.</p></div></div>
            <div class="field-grid">
              <div><label for="p-regular">Giá niêm yết <span aria-hidden="true">*</span></label><input type="number" inputmode="numeric" id="p-regular" name="lwa_product[regular_price]" min="1" step="1" value="<?php echo esc_attr($pvalue('regular_price')); ?>" required></div>
              <div><label for="p-sale">Giá khuyến mãi</label><input type="number" inputmode="numeric" id="p-sale" name="lwa_product[sale_price]" min="1" step="1" value="<?php echo esc_attr($pvalue('sale_price')); ?>" aria-describedby="p-sale-help"></div>
              <div><label for="p-stock">Số lượng tồn kho <span aria-hidden="true">*</span></label><input type="number" inputmode="numeric" id="p-stock" name="lwa_product[stock]" min="0" max="100000" step="1" value="<?php echo esc_attr($pvalue('stock', '1')); ?>" required></div>
            </div>
            <p class="help" id="p-sale-help">Bỏ trống nếu không giảm giá. Giá khuyến mãi phải thấp hơn giá niêm yết. Tồn kho bằng 0 sẽ hiển thị “Hết hàng”.</p>
          </section>
          <section class="panel" aria-labelledby="p-cat-heading">
            <div class="section-heading"><span class="step">3</span><div><h2 id="p-cat-heading">Bộ sưu tập và ảnh</h2><p>Nơi sản phẩm xuất hiện trên cửa hàng.</p></div></div>
            <fieldset class="checks"><legend>Bộ sưu tập</legend>
              <?php foreach ($collections as $term) : ?>
                <label class="check"><input type="checkbox" name="lwa_product[collections][]" value="<?php echo esc_attr($term->term_id); ?>" <?php checked(in_array((int) $term->term_id, $checked_collections, true)); ?>> <?php echo esc_html($term->name); ?></label>
              <?php endforeach; ?>
            </fieldset>
            <p class="help">“Bộ sưu tập đấu giá” dành cho đồng hồ bán qua phiên đấu giá; chatbot sẽ không gợi ý mua trực tiếp những mẫu này.</p>
            <label for="p-image">Ảnh sản phẩm <span class="label-note">(bắt buộc khi đăng)</span></label><input id="p-image" type="file" name="product_image" accept="image/jpeg,image/png,image/webp"><p class="help">JPG, PNG hoặc WebP, tối đa <?php echo esc_html(size_format(wp_max_upload_size())); ?>. Khi biểu mẫu báo lỗi, hãy chọn lại file ảnh.</p>
          </section>
        </div>
        <aside class="publish-panel">
          <section class="panel preview"><p class="eyebrow">Xem nhanh sản phẩm</p><div class="preview-image"><img id="p-preview-image" alt="Ảnh sản phẩm" hidden><span id="p-preview-placeholder">Chọn ảnh để xem trước</span></div><h2 id="p-preview-title"><?php echo esc_html($pvalue('name') ?: 'Sản phẩm mới'); ?></h2><p class="help">Giá bán</p><strong class="preview-price" id="p-preview-price">—</strong><p class="help" id="p-preview-stock"></p></section>
          <section class="panel publish-box"><h2>Sẵn sàng đăng?</h2><p class="help">Sản phẩm được đăng sẽ hiển thị ngay trên cửa hàng và có thể dùng để tạo phiên đấu giá.</p><button class="button" type="submit" name="lwa_product[status]" value="publish">Đăng sản phẩm</button><button class="button secondary" type="submit" name="lwa_product[status]" value="draft">Lưu nháp</button><p class="help submit-status" role="status"></p></section>
        </aside>
      </form>
      <?php elseif ($view === 'new') : ?>
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
            <p class="help">Đồng hồ chưa có trong cửa hàng? <a href="<?php echo esc_url(add_query_arg('view', 'new-product', self::url())); ?>">Thêm sản phẩm mới</a></p>
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
      <?php elseif ($view === 'products') : ?>
      <section class="panel auction-list" aria-label="Danh sách sản phẩm">
        <div class="list-heading">
          <h2>Sản phẩm trong cửa hàng</h2>
          <form method="get" action="<?php echo esc_url(self::url()); ?>" class="list-search" role="search">
            <input type="hidden" name="view" value="products">
            <label class="sr-only" for="lwa-q">Tìm sản phẩm</label>
            <input id="lwa-q" type="search" name="lwa_q" value="<?php echo esc_attr($product_search); ?>" placeholder="Tìm theo tên hoặc SKU">
            <button class="button secondary" type="submit">Tìm</button>
          </form>
          <span><?php echo esc_html($product_query->found_posts); ?> sản phẩm</span>
        </div>
        <?php if ($product_query->posts) : ?>
          <div class="table-wrap"><table><thead><tr><th>Sản phẩm</th><th>Trạng thái</th><th>Giá bán</th><th>Tồn kho</th><th>Bộ sưu tập</th><th>Thao tác</th></tr></thead><tbody>
          <?php foreach ($product_query->posts as $post) : $item = wc_get_product($post->ID); if (!$item) continue; $thumb = wp_get_attachment_image_url($item->get_image_id(), 'thumbnail'); $linked = self::linked_auctions($item->get_id()); $cats = wp_get_post_terms($item->get_id(), 'product_cat', ['fields' => 'names']); ?>
            <tr>
              <td><div class="auction-name"><?php if ($thumb) : ?><img src="<?php echo esc_url($thumb); ?>" alt=""><?php else : ?><span class="thumb-empty" aria-hidden="true"></span><?php endif; ?><div><strong><?php echo esc_html($item->get_name()); ?></strong><small><?php echo esc_html($item->get_sku() ?: 'Chưa có SKU'); ?></small><?php if ($linked) : ?><small class="linked">Đang dùng trong <?php echo esc_html(count($linked)); ?> phiên đấu giá</small><?php endif; ?></div></div></td>
              <td><span class="badge <?php echo esc_attr($item->get_status() === 'publish' ? 'live' : 'draft'); ?>"><?php echo esc_html($status_labels[$item->get_status()] ?? $item->get_status()); ?></span></td>
              <td><?php echo esc_html(number_format((float) $item->get_price(), 0, ',', '.')); ?> ₫<?php if ($item->is_on_sale()) : ?><small class="was"><?php echo esc_html(number_format((float) $item->get_regular_price(), 0, ',', '.')); ?> ₫</small><?php endif; ?></td>
              <td><?php echo $item->is_in_stock() ? esc_html($item->managing_stock() ? 'Còn ' . (int) $item->get_stock_quantity() : 'Còn hàng') : '<span class="out">Hết hàng</span>'; ?></td>
              <td class="cats"><?php echo esc_html(implode(', ', $cats)); ?></td>
              <td class="actions">
                <?php if ($item->get_status() === 'publish') : ?><a href="<?php echo esc_url(get_permalink($item->get_id())); ?>" target="_blank" rel="noopener">Xem</a><a href="<?php echo esc_url(add_query_arg(['view' => 'new', 'lwa_product' => $item->get_id()], self::url())); ?>">Tạo phiên</a><?php endif; ?>
                <a href="<?php echo esc_url(get_edit_post_link($item->get_id())); ?>">Sửa</a>
                <?php if (current_user_can('delete_post', $item->get_id())) : ?>
                  <?php if ($linked) : ?>
                    <button type="button" class="link-button danger" disabled title="Sản phẩm đang dùng trong phiên đấu giá nên không thể xóa">Xóa</button>
                  <?php else : ?>
                    <form method="post" class="inline-form js-trash-product" data-name="<?php echo esc_attr($item->get_name()); ?>">
                      <?php wp_nonce_field('lwa_trash_product_' . $item->get_id(), 'lwa_nonce'); ?>
                      <input type="hidden" name="lwa_form" value="trash_product"><input type="hidden" name="lwa_product_id" value="<?php echo esc_attr($item->get_id()); ?>"><?php $keep_fields(); ?>
                      <button type="submit" class="link-button danger" aria-label="Xóa <?php echo esc_attr($item->get_name()); ?>">Xóa</button>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody></table></div>
          <nav class="pagination" aria-label="Trang danh sách sản phẩm"><?php echo wp_kses_post(paginate_links(['base' => add_query_arg(array_filter(['view' => 'products', 'lwa_q' => $product_search, 'lwa_page' => '%#%']), self::url()), 'format' => '', 'total' => $product_query->max_num_pages, 'current' => $product_page, 'prev_text' => 'Trước', 'next_text' => 'Tiếp'])); ?></nav>
        <?php elseif ($product_search !== '') : ?>
          <div class="empty"><h3>Không tìm thấy sản phẩm phù hợp</h3><p>Thử tìm với tên khác hoặc một phần SKU.</p><a class="button secondary" href="<?php echo esc_url(add_query_arg('view', 'products', self::url())); ?>">Xem tất cả sản phẩm</a></div>
        <?php else : ?>
          <div class="empty"><h3>Cửa hàng chưa có sản phẩm</h3><p>Thêm chiếc đồng hồ đầu tiên để bắt đầu bán hoặc mở phiên đấu giá.</p><a class="button" href="<?php echo esc_url(add_query_arg('view', 'new-product', self::url())); ?>">Thêm sản phẩm</a></div>
        <?php endif; ?>
      </section>
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

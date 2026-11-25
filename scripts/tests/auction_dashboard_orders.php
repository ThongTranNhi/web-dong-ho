<?php
require_once dirname(__DIR__, 2) . '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

function order_dashboard_check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

$users = [];
$order_id = 0;
$product_id = 0;

try {
    order_dashboard_check(method_exists('Luxury_Auction_Dashboard', 'update_order'), 'Order update service is missing.');

    foreach (['subscriber', 'administrator'] as $role) {
        $id = wp_insert_user([
            'user_login' => 'lwa_order_test_' . bin2hex(random_bytes(5)),
            'user_pass' => wp_generate_password(24),
            'user_email' => 'lwa_' . bin2hex(random_bytes(5)) . '@example.test',
            'role' => $role,
        ]);
        if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
        $users[] = $id;
    }

    $product = new WC_Product_Simple();
    $product->set_name('Order dashboard test watch');
    $product->set_regular_price('2500000');
    $product->set_status('publish');
    $product_id = $product->save();

    $order = wc_create_order(['customer_id' => $users[0]]);
    if (is_wp_error($order)) throw new RuntimeException($order->get_error_message());
    $order->add_product($product, 1);
    $order->set_billing_first_name('Khách');
    $order->set_billing_last_name('Kiểm thử');
    $order->set_billing_email('order-dashboard@example.test');
    $order->calculate_totals();
    $order->save();
    $order_id = $order->get_id();

    wp_set_current_user($users[0]);
    order_dashboard_check(method_exists('Luxury_Auction_Dashboard', 'order_list'), 'Order list service is missing.');
    order_dashboard_check(method_exists('Luxury_Auction_Dashboard', 'managed_order'), 'Managed order service is missing.');
    order_dashboard_check(method_exists('Luxury_Auction_Dashboard', 'order_status_labels'), 'Vietnamese order status labels are missing.');
    $labels = Luxury_Auction_Dashboard::order_status_labels();
    order_dashboard_check(($labels['pending'] ?? '') === 'Chờ thanh toán' && ($labels['checkout-draft'] ?? '') === 'Bản nháp thanh toán', 'Order statuses must use explicit Vietnamese labels.');
    $managed = Luxury_Auction_Dashboard::managed_order($order_id);
    order_dashboard_check(is_wp_error($managed) && $managed->get_error_code() === 'forbidden', 'A customer must not read order details through the dashboard.');
    $result = Luxury_Auction_Dashboard::update_order($order_id, ['action' => 'status', 'status' => 'processing', 'reason' => 'Khách đã thanh toán.']);
    order_dashboard_check(is_wp_error($result) && $result->get_error_code() === 'forbidden', 'A customer must not update an order.');

    wp_set_current_user($users[1]);
    $managed = Luxury_Auction_Dashboard::managed_order($order_id);
    order_dashboard_check($managed instanceof WC_Order && $managed->get_id() === $order_id, 'An administrator must read a valid order.');
    $found = Luxury_Auction_Dashboard::order_list('order-dashboard@example.test', 'pending', 1, 10);
    order_dashboard_check(!is_wp_error($found) && $found->total >= 1, 'Order search must find billing email.');
    order_dashboard_check(in_array($order_id, array_map(static fn($item) => $item->get_id(), $found->orders), true), 'Filtered results must include the matching order.');
    order_dashboard_check($found->max_num_pages >= 1, 'Order results must expose pagination metadata.');
    $invalid_list = Luxury_Auction_Dashboard::order_list('', 'made-up-status', 1, 10);
    order_dashboard_check(is_wp_error($invalid_list) && $invalid_list->get_error_code() === 'invalid_status', 'Unknown status filters must be rejected.');

    $result = Luxury_Auction_Dashboard::update_order($order_id, ['action' => 'status', 'status' => 'processing', 'reason' => '   ']);
    order_dashboard_check(is_wp_error($result) && $result->get_error_code() === 'reason_required', 'A short or empty reason must be rejected.');

    $result = Luxury_Auction_Dashboard::update_order($order_id, ['action' => 'status', 'status' => 'refunded', 'reason' => 'Thử trạng thái không được phép.']);
    order_dashboard_check(is_wp_error($result) && $result->get_error_code() === 'invalid_status', 'A non-allowlisted status must be rejected.');

    $result = Luxury_Auction_Dashboard::update_order($order_id, ['action' => 'status', 'status' => 'on-hold', 'reason' => 'Chờ đối soát giao dịch ngân hàng.']);
    order_dashboard_check($result === $order_id, 'A valid status transition must return the order ID.');
    $order = wc_get_order($order_id);
    order_dashboard_check($order->get_status() === 'on-hold', 'The order must move to on-hold.');

    $result = Luxury_Auction_Dashboard::update_order($order_id, ['action' => 'mark_paid', 'reason' => 'Đã xác minh giao dịch trên SePay.', 'transaction_id' => 'SP-TEST-001']);
    order_dashboard_check($result === $order_id, 'Mark paid must return the order ID.');
    $order = wc_get_order($order_id);
    order_dashboard_check($order->is_paid(), 'Mark paid must complete WooCommerce payment.');
    order_dashboard_check($order->get_transaction_id() === 'SP-TEST-001', 'Mark paid must preserve the transaction ID.');

    $notes = wc_get_order_notes(['order_id' => $order_id, 'type' => 'internal']);
    $note_text = implode("\n", array_map(static fn($note) => $note->content, $notes));
    order_dashboard_check(strpos($note_text, 'Đã xác minh giao dịch trên SePay.') !== false, 'The audit note must contain the reason.');
    order_dashboard_check(strpos($note_text, wp_get_current_user()->display_name) !== false, 'The audit note must identify the administrator.');

    $template = file_get_contents(dirname(__DIR__, 2) . '/wordpress/wp-content/plugins/luxury-auctions/templates/auction-dashboard.php');
    $script = file_get_contents(dirname(__DIR__, 2) . '/wordpress/wp-content/plugins/luxury-auctions/assets/dashboard.js');
    order_dashboard_check(strpos($template, '>Đơn hàng<') !== false, 'The dashboard menu must contain Orders.');
    order_dashboard_check(strpos($template, 'view === \'orders\'') !== false, 'The template must render the order list view.');
    order_dashboard_check(strpos($template, 'view === \'order\'') !== false, 'The template must render the order detail view.');
    order_dashboard_check(strpos($template, 'name="order[reason]"') !== false, 'The order form must require an audit reason.');
    order_dashboard_check(strpos($template, 'name="order[action]" value="mark_paid"') !== false, 'The order detail must provide mark-paid action.');
    order_dashboard_check(strpos($template, 'value="" disabled') !== false, 'A non-editable current status must require an explicit new status selection.');
    order_dashboard_check(strpos($script, 'js-order-update') !== false && strpos($script, 'window.confirm') !== false, 'Order updates must have a client confirmation hook.');

    echo "PASS: order permissions, validation, status changes, payment completion and audit notes.\n";
} finally {
    wp_set_current_user(0);
    if ($order_id) {
        $order = wc_get_order($order_id);
        if ($order) $order->delete(true);
    }
    if ($product_id) wp_delete_post($product_id, true);
    foreach ($users as $id) wp_delete_user($id);
}

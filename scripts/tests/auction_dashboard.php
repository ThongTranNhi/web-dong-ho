<?php
require_once dirname(__DIR__, 2) . '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

function dashboard_check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}

$users = [];
$auction_id = 0;
$product = new WC_Product_Simple();
$product->set_name('Dashboard test watch');
$product->set_status('publish');
$product_id = $product->save();
try {
    foreach (['subscriber', 'administrator'] as $role) {
        $id = wp_insert_user(['user_login' => 'lwa_dashboard_test_' . bin2hex(random_bytes(5)), 'user_pass' => wp_generate_password(24), 'role' => $role]);
        if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
        $users[] = $id;
    }
    $input = [
        'title' => 'Dashboard test auction', 'status' => 'draft',
        '_lw_product_id' => (string) $product_id, '_lw_lot_number' => 'TEST-' . bin2hex(random_bytes(5)),
        '_lw_start_price' => '1000', '_lw_bid_increment' => '100', '_lw_reserve_price' => '1500',
        '_lw_start_at' => current_datetime()->modify('+1 hour')->format('Y-m-d\TH:i'),
        '_lw_end_at' => current_datetime()->modify('+2 hours')->format('Y-m-d\TH:i'),
    ];
    $token = bin2hex(random_bytes(16));
    wp_set_current_user($users[0]);
    $result = Luxury_Auction_Dashboard::create_auction($input, $token);
    dashboard_check(is_wp_error($result) && $result->get_error_code() === 'forbidden', 'A customer must not create auctions.');
    wp_set_current_user($users[1]);
    $invalid = $input;
    $invalid['_lw_bid_increment'] = '0';
    dashboard_check(is_wp_error(Luxury_Auction_Dashboard::create_auction($invalid, $token)), 'Zero increment must be rejected before creating a post.');
    $result = Luxury_Auction_Dashboard::create_auction($input, $token);
    dashboard_check(!is_wp_error($result) && $result > 0, 'Valid input must create a draft.');
    $auction_id = $result;
    dashboard_check(get_post_status($auction_id) === 'draft' && (int) get_post_meta($auction_id, '_lw_product_id', true) === $product_id, 'Draft must store the product and auction settings.');
    dashboard_check(Luxury_Auction_Dashboard::create_auction($input, $token) === $auction_id, 'Repeated submission must reuse the same auction.');
    $duplicate = Luxury_Auction_Dashboard::create_auction($input, bin2hex(random_bytes(16)));
    dashboard_check(is_wp_error($duplicate) && $duplicate->get_error_code() === 'duplicate_lot', 'A new submission must not reuse an existing lot code.');
    $invalid = $input;
    $invalid['status'] = 'publish';
    dashboard_check(is_wp_error(Luxury_Auction_Dashboard::create_auction($invalid, bin2hex(random_bytes(16)))), 'Publishing without an image must be rejected.');
    echo "PASS: dashboard permissions, validation, draft creation and duplicate submission.\n";
} finally {
    wp_set_current_user(0);
    if ($auction_id) wp_delete_post($auction_id, true);
    wp_delete_post($product_id, true);
    foreach ($users as $id) wp_delete_user($id);
}

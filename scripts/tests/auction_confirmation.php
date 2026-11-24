<?php
require_once dirname(__DIR__, 2) . '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

function confirmation_check($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

global $wpdb;
$auction_id = 0;
$user_ids = [];
$table = $wpdb->prefix . 'luxury_auction_bids';
try {
    foreach (['subscriber', 'administrator'] as $role) {
        $id = wp_insert_user([
            'user_login' => 'lw_confirm_test_' . bin2hex(random_bytes(5)),
            'user_pass' => wp_generate_password(32),
            'role' => $role,
        ]);
        if (is_wp_error($id)) throw new RuntimeException($id->get_error_message());
        $user_ids[] = $id;
    }
    [$bidder, $admin] = $user_ids;
    $product = new WC_Product_Simple();
    $product->set_name('Auction configuration test product');
    $product_id = $product->save();
    $auction_id = wp_insert_post(['post_type' => 'watch_auction', 'post_status' => 'publish', 'post_title' => 'Confirmation test'], true);
    if (is_wp_error($auction_id)) throw new RuntimeException($auction_id->get_error_message());
    $now = current_datetime();
    update_post_meta($auction_id, '_lw_start_at', $now->modify('-2 hours')->format('Y-m-d\TH:i'));
    update_post_meta($auction_id, '_lw_end_at', $now->modify('+1 hour')->format('Y-m-d\TH:i'));
    update_post_meta($auction_id, '_lw_reserve_price', 1500);
    wp_set_current_user($bidder);
    $result = Luxury_Auction_Admin::confirm_result($auction_id);
    confirmation_check(is_wp_error($result) && $result->get_error_code() === 'forbidden', 'A customer must not confirm a winner.');
    wp_set_current_user($admin);
    $_POST = [
        'luxury_auction_nonce' => wp_create_nonce('luxury_auction_save'),
        '_lw_product_id' => (string) $product_id,
        '_lw_start_price' => '1000',
        '_lw_bid_increment' => '100',
    ];
    Luxury_Watches_Auctions::save_meta($auction_id);
    confirmation_check((int) get_post_meta($auction_id, '_lw_start_price', true) === 1000, 'Valid settings must be saved.');
    $_POST['_lw_bid_increment'] = '0';
    Luxury_Watches_Auctions::save_meta($auction_id);
    confirmation_check((int) get_post_meta($auction_id, '_lw_bid_increment', true) === 100, 'Invalid settings must retain the previous increment.');
    $_POST['_lw_bid_increment'] = '100';
    $result = Luxury_Auction_Admin::confirm_result($auction_id);
    confirmation_check(is_wp_error($result) && $result->get_error_code() === 'not_ended', 'A live auction must not be confirmed.');
    update_post_meta($auction_id, '_lw_end_at', $now->modify('-1 hour')->format('Y-m-d\TH:i'));
    $result = Luxury_Auction_Admin::confirm_result($auction_id);
    confirmation_check(is_wp_error($result) && $result->get_error_code() === 'no_bids', 'An auction with no bids must not have a winner.');
    $wpdb->insert($table, ['auction_id' => $auction_id, 'user_id' => $bidder, 'amount' => 1000, 'created_at' => current_time('mysql')]);
    $_POST['_lw_start_price'] = '2000';
    Luxury_Watches_Auctions::save_meta($auction_id);
    confirmation_check((int) get_post_meta($auction_id, '_lw_start_price', true) === 1000, 'Existing bids must lock the pricing rules.');
    $result = Luxury_Auction_Admin::confirm_result($auction_id);
    confirmation_check(is_wp_error($result) && $result->get_error_code() === 'reserve', 'A bid below reserve must not win.');
    $wpdb->insert($table, ['auction_id' => $auction_id, 'user_id' => $bidder, 'amount' => 1500, 'created_at' => current_time('mysql')]);
    $winning_bid = (int) $wpdb->insert_id;
    $post = get_post($auction_id);
    $GLOBALS['post'] = $post;
    set_current_screen('watch_auction');
    ob_start();
    Luxury_Auction_Admin::meta_box($post);
    Luxury_Auction_Admin::confirmation_form();
    $markup = ob_get_clean();
    confirmation_check(strpos($markup, 'form="lw-confirm-auction"') !== false && strpos($markup, 'name="lw_confirm_nonce"') !== false, 'Admin confirmation must render a separate form with a nonce.');
    $result = Luxury_Auction_Admin::confirm_result($auction_id);
    confirmation_check(is_array($result) && $result['bid_id'] === $winning_bid && $result['amount'] === 1500 && $result['user_id'] === $bidder && $result['confirmed_by'] === $admin, 'Confirmation must record the highest qualifying bid and administrator.');
    confirmation_check(get_post_meta($auction_id, '_lw_auction_result', true) === $result, 'Confirmation must persist the result.');
    confirmation_check(Luxury_Auction_Admin::confirm_result($auction_id) === $result, 'Repeated confirmation must reuse the saved result.');
    update_post_meta($auction_id, '_lw_end_at', $now->modify('+2 hours')->format('Y-m-d\TH:i'));
    confirmation_check(Luxury_Watches_Auctions::state($auction_id) === 'ended', 'A confirmed auction must remain closed even if its metadata changes.');
    echo "PASS: permissions, auction end, reserve, winner selection, persistence and repeat confirmation.\n";
} finally {
    wp_set_current_user(0);
    if ($auction_id && !is_wp_error($auction_id)) {
        $wpdb->delete($table, ['auction_id' => $auction_id], ['%d']);
        wp_delete_post($auction_id, true);
    }
    foreach ($user_ids as $id) wp_delete_user($id);
    foreach ($user_ids as $id) delete_transient('lw_auction_notice_' . $id);
    if (isset($product_id)) wp_delete_post($product_id, true);
}

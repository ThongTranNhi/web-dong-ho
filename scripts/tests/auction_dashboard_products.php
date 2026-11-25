<?php
/**
 * Tests for "Thêm sản phẩm" on /quan-tri-dau-gia/.
 * Run: C:\xampp\php\php.exe scripts\tests\auction_dashboard_products.php
 * Image upload goes through a real browser upload and is covered by the browser check.
 */
require_once dirname(__DIR__, 2) . '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$passed = 0;
$failed = [];
function check($name, $condition, $detail = '') {
    global $passed, $failed;
    if ($condition) { $passed++; echo "  ok  $name\n"; }
    else { $failed[] = $name; echo "  FAIL $name" . ($detail !== '' ? " -> $detail" : '') . "\n"; }
}
function code($result) { return is_wp_error($result) ? $result->get_error_code() : 'id:' . $result; }

$users = [];
$created = [];
$auction_term = get_term_by('slug', 'bo-suu-tap-dau-gia', 'product_cat');
$men_term = get_term_by('slug', 'dong-ho-nam', 'product_cat');
try {
    foreach (['subscriber', 'shop_manager'] as $role) {
        $users[$role] = wp_insert_user(['user_login' => 'lwa_prod_test_' . bin2hex(random_bytes(4)), 'user_pass' => wp_generate_password(24), 'role' => $role]);
    }
    $sku = 'LWTEST-' . strtoupper(bin2hex(random_bytes(4)));
    $input = [
        'name' => 'Test Tourbillon ' . bin2hex(random_bytes(3)), 'sku' => $sku,
        'regular_price' => '45900000', 'sale_price' => '41900000', 'stock' => '3',
        'gender' => 'dong-ho-nam', 'collections' => [(string) $auction_term->term_id, '999999'],
        'short_description' => 'Mô tả <script>alert(1)</script>ngắn', 'description' => 'Vỏ thép 41 mm', 'status' => 'draft',
    ];

    wp_set_current_user($users['subscriber']);
    check('customers cannot add products', code(Luxury_Auction_Dashboard::create_product($input, bin2hex(random_bytes(16)))) === 'forbidden');

    wp_set_current_user($users['shop_manager']);
    $cases = [
        'invalid_submission' => [[], 'not-a-token'],
        'name_required' => [['name' => ' ']],
        'invalid_sku' => [['sku' => 'bad sku!']],
        'invalid_price' => [['regular_price' => '0']],
        'invalid_sale_price' => [['sale_price' => '45900000']],
        'invalid_stock' => [['stock' => '-1']],
        'image_required' => [['status' => 'publish']],
    ];
    foreach ($cases as $expected => $case) {
        $result = Luxury_Auction_Dashboard::create_product(array_merge($input, $case[0]), $case[1] ?? bin2hex(random_bytes(16)));
        check("rejects $expected", code($result) === $expected, code($result));
    }
    check('rejected input leaves no product behind', !wc_get_product_id_by_sku($sku));

    $token = bin2hex(random_bytes(16));
    $id = Luxury_Auction_Dashboard::create_product($input, $token);
    check('valid input creates a product', !is_wp_error($id) && $id > 0, code($id));
    if (!is_wp_error($id)) {
        $created[] = $id;
        $p = wc_get_product($id);
        $cats = $p->get_category_ids();
        check('stores name, SKU, prices and stock', $p->get_name() === $input['name'] && $p->get_sku() === $sku && (int) $p->get_regular_price() === 45900000 && (int) $p->get_sale_price() === 41900000 && (int) $p->get_price() === 41900000 && $p->get_manage_stock() && $p->get_stock_quantity() === 3 && $p->get_stock_status() === 'instock');
        check('saved as draft when requested', $p->get_status() === 'draft');
        check('gender and allowed collections only', in_array((int) $men_term->term_id, $cats, true) && in_array((int) $auction_term->term_id, $cats, true) && !in_array(999999, $cats, true) && count($cats) === 2);
        check('description HTML is sanitised', strpos($p->get_short_description(), '<script') === false);
        check('repeated submission returns the same product', Luxury_Auction_Dashboard::create_product($input, $token) === $id);
        check('a new submission cannot reuse the SKU', code(Luxury_Auction_Dashboard::create_product($input, bin2hex(random_bytes(16)))) === 'duplicate_sku');
    }

    $no_stock = array_merge($input, ['sku' => '', 'sale_price' => '', 'stock' => '0', 'gender' => 'unisex', 'collections' => []]);
    $id2 = Luxury_Auction_Dashboard::create_product($no_stock, bin2hex(random_bytes(16)));
    if (!is_wp_error($id2)) $created[] = $id2;
    $p2 = is_wp_error($id2) ? null : wc_get_product($id2);
    // With no category chosen WooCommerce applies its default product category.
    check('stock 0 is out of stock, no sale price, unisex gets no gender category', $p2 && $p2->get_stock_status() === 'outofstock' && $p2->get_sale_price() === '' && $p2->get_category_ids() === [(int) get_option('default_product_cat')], code($id2));

    // A published product created here can be linked to an auction; a draft cannot.
    if (!is_wp_error($id)) {
        $auction_input = ['title' => 'Phiên thử', 'status' => 'draft', '_lw_product_id' => (string) $id, '_lw_lot_number' => 'T-' . bin2hex(random_bytes(4)),
            '_lw_start_price' => '1000', '_lw_bid_increment' => '100', '_lw_reserve_price' => '0',
            '_lw_start_at' => current_datetime()->modify('+1 hour')->format('Y-m-d\TH:i'), '_lw_end_at' => current_datetime()->modify('+2 hours')->format('Y-m-d\TH:i')];
        check('draft product cannot be used for an auction yet', code(Luxury_Auction_Dashboard::create_auction($auction_input, bin2hex(random_bytes(16)))) === 'invalid_product');
    }

    echo "Product list and delete\n";
    $pub = new WC_Product_Simple();
    $pub->set_name('Delete test ' . bin2hex(random_bytes(3))); $pub->set_regular_price('1000'); $pub->set_sku('LWDEL-' . strtoupper(bin2hex(random_bytes(4)))); $pub->set_status('publish');
    $pub_id = $pub->save();
    $created[] = $pub_id;
    $list = Luxury_Auction_Dashboard::product_list($pub->get_sku(), 1);
    check('list finds a product by SKU', wp_list_pluck($list->posts, 'ID') === [$pub_id]);
    $list = Luxury_Auction_Dashboard::product_list(substr($pub->get_name(), 0, 11), 1);
    check('list finds a product by name', in_array($pub_id, wp_list_pluck($list->posts, 'ID'), true));
    check('unknown search returns nothing', Luxury_Auction_Dashboard::product_list('zzz-no-such-watch-' . bin2hex(random_bytes(3)), 1)->posts === []);

    wp_set_current_user($users['subscriber']);
    check('customers cannot delete products', code(Luxury_Auction_Dashboard::trash_product($pub_id)) === 'forbidden' && get_post_status($pub_id) === 'publish');
    wp_set_current_user($users['shop_manager']);
    check('non-product IDs are rejected', code(Luxury_Auction_Dashboard::trash_product(get_option('page_on_front') ?: 1)) === 'not_found');

    $auction_id = wp_insert_post(['post_type' => 'watch_auction', 'post_status' => 'draft', 'post_title' => 'Phiên giữ sản phẩm', 'meta_input' => ['_lw_product_id' => (string) $pub_id]]);
    $blocked = Luxury_Auction_Dashboard::trash_product($pub_id);
    check('product used by an auction cannot be deleted', code($blocked) === 'in_auction' && strpos($blocked->get_error_message(), 'Phiên giữ sản phẩm') !== false && get_post_status($pub_id) === 'publish');
    wp_delete_post($auction_id, true);

    check('delete moves the product to the trash', Luxury_Auction_Dashboard::trash_product($pub_id) === $pub_id && get_post_status($pub_id) === 'trash');
    check('trashed product leaves the list', !in_array($pub_id, wp_list_pluck(Luxury_Auction_Dashboard::product_list('', 1, 500)->posts, 'ID'), true));
    check('deleting twice is reported', code(Luxury_Auction_Dashboard::trash_product($pub_id)) === 'already_trashed');
    check('undo restores the previous published status', Luxury_Auction_Dashboard::restore_product($pub_id) === $pub_id && get_post_status($pub_id) === 'publish');
    check('restoring a product not in the trash is reported', code(Luxury_Auction_Dashboard::restore_product($pub_id)) === 'not_trashed');
} catch (Throwable $e) {
    $failed[] = 'exception';
    echo '  FAIL exception: ' . $e->getMessage() . "\n";
} finally {
    wp_set_current_user(0);
    foreach ($created as $id) wp_delete_post($id, true);
    foreach ($users as $id) if ($id && !is_wp_error($id)) wp_delete_user($id);
}
echo "\n{$passed} passed, " . count($failed) . " failed\n";
exit($failed ? 1 : 0);

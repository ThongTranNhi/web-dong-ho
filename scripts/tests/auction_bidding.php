<?php
require_once dirname(__DIR__, 2) . '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

global $wpdb;
$auction_id = 0;
$user_id = 0;
$table = $wpdb->prefix . 'luxury_auction_bids';
try {
    $user_id = wp_insert_user([
        'user_login' => 'lw_backend_test_' . bin2hex(random_bytes(5)),
        'user_pass' => wp_generate_password(32),
        'role' => 'subscriber',
    ]);
    if (is_wp_error($user_id)) throw new RuntimeException($user_id->get_error_message());
    $auction_id = wp_insert_post([
        'post_type' => 'watch_auction',
        'post_status' => 'publish',
        'post_title' => 'Backend test ' . bin2hex(random_bytes(5)),
    ], true);
    if (is_wp_error($auction_id)) throw new RuntimeException($auction_id->get_error_message());
    $now = current_datetime();
    foreach ([
        '_lw_start_price' => 1000,
        '_lw_bid_increment' => 100,
        '_lw_start_at' => $now->modify('-1 hour')->format('Y-m-d\TH:i'),
        '_lw_end_at' => $now->modify('+1 hour')->format('Y-m-d\TH:i'),
    ] as $key => $value) update_post_meta($auction_id, $key, $value);

    $worker = <<<'PHP'
ob_start();
require $argv[1];
wp_set_current_user((int) $argv[3]);
$_POST = ['auction_id' => $argv[2], 'amount' => $argv[4], 'luxury_bid_nonce' => wp_create_nonce('luxury_bid_' . $argv[2])];
add_filter('wp_redirect', static function ($url) {
    parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $query);
    echo $query['bid_status'] ?? 'login';
    return $url;
});
do_action('admin_post_luxury_place_bid');
PHP;
    $rate_key = 'lw_bid_' . md5($auction_id . ':' . $user_id);
    foreach ([
        ['-1000', 'low', 0, true],
        ['1000.00', 'low', 0, true],
        ['1000', 'accepted', 1, true],
        ['1100', 'rate', 1, false],
        ['1050', 'low', 1, true],
        ['1000', 'low', 1, true],
        ['1100', 'accepted', 2, true],
    ] as [$amount, $expected, $count, $reset]) {
        if ($reset) delete_transient($rate_key);
        // Age earlier fixture bids so price tests are independent of rate limiting.
        if ($reset) $wpdb->update($table, ['created_at' => $now->modify('-10 seconds')->format('Y-m-d H:i:s')], ['auction_id' => $auction_id]);
        $process = proc_open([
            PHP_BINARY, '-r', $worker, ABSPATH . 'wp-load.php',
            (string) $auction_id, (string) $user_id, $amount,
        ], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Could not start bid request.');
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $actual = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE auction_id = %d", $auction_id));
        if ($exit !== 0 || $error !== '' || $output !== $expected || $actual !== $count) {
            throw new RuntimeException(json_encode(compact('amount', 'output', 'error', 'actual', 'expected', 'count')));
        }
    }
    echo "PASS: real bid handler accepts valid bids, rejects malformed/low bids, and rate limits requests.\n";
} finally {
    if ($auction_id && !is_wp_error($auction_id)) {
        $wpdb->delete($table, ['auction_id' => $auction_id], ['%d']);
        wp_delete_post($auction_id, true);
    }
    if ($user_id && !is_wp_error($user_id)) {
        if ($auction_id && !is_wp_error($auction_id)) delete_transient('lw_bid_' . md5($auction_id . ':' . $user_id));
        wp_delete_user($user_id);
    }
}

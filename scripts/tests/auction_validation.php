<?php
require_once dirname(__DIR__, 2) . '/wordpress/wp-load.php';

function auction_check($condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$timestamp = new ReflectionMethod(Luxury_Watches_Auctions::class, 'timestamp');
$timestamp->setAccessible(true);
foreach (['', '2026-02-30T10:00', '2026-11-24T25:00', '2026-11-24T10:00junk'] as $value) {
    auction_check($timestamp->invoke(null, $value) === 0, 'Invalid date must be rejected: ' . $value);
}
auction_check($timestamp->invoke(null, '2026-11-24T10:00') > 0, 'Valid local date must be accepted.');

$parse = new ReflectionMethod(Luxury_Watches_Auctions::class, 'parse_amount');
$parse->setAccessible(true);
foreach (['-100', '+100', '1.5', '1e6', '100abc', '', '0', [], (string) PHP_INT_MAX . '0'] as $value) {
    auction_check($parse->invoke(null, $value) === false, 'Malformed amount must be rejected.');
}
auction_check($parse->invoke(null, '25000000') === 25000000, 'Whole VND amount must be accepted.');
auction_check($parse->invoke(null, '000100') === 100, 'Leading zeros must retain the amount.');
auction_check($parse->invoke(null, (string) PHP_INT_MAX) === PHP_INT_MAX, 'Largest supported integer must be accepted.');

global $wpdb;
$engine = $wpdb->get_var($wpdb->prepare(
    'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
    $wpdb->posts
));
auction_check($engine === 'InnoDB', 'The fixture requires transactional tables.');
$wpdb->query('START TRANSACTION');
$auction_id = 0;
try {
    $auction_id = wp_insert_post(['post_type' => 'watch_auction', 'post_title' => 'Auction validation fixture', 'post_status' => 'draft'], true);
    auction_check(!is_wp_error($auction_id), 'Fixture creation must succeed.');
    $state = new ReflectionMethod(Luxury_Watches_Auctions::class, 'state');
    $state->setAccessible(true);
    auction_check($state->invoke(null, $auction_id) !== 'live', 'Missing schedule must reject bids.');
    $now = current_datetime();
    update_post_meta($auction_id, '_lw_start_at', $now->modify('-1 hour')->format('Y-m-d\TH:i'));
    update_post_meta($auction_id, '_lw_end_at', $now->modify('+1 hour')->format('Y-m-d\TH:i'));
    auction_check($state->invoke(null, $auction_id) === 'live', 'Open schedule must accept bids.');
    update_post_meta($auction_id, '_lw_end_at', $now->modify('-2 hours')->format('Y-m-d\TH:i'));
    auction_check($state->invoke(null, $auction_id) !== 'live', 'Reversed schedule must reject bids.');
    update_post_meta($auction_id, '_lw_start_at', $now->modify('+1 hour')->format('Y-m-d\TH:i'));
    update_post_meta($auction_id, '_lw_end_at', $now->modify('+2 hours')->format('Y-m-d\TH:i'));
    auction_check($state->invoke(null, $auction_id) === 'upcoming', 'Future schedule must remain upcoming.');
    update_post_meta($auction_id, '_lw_start_at', $now->modify('-2 hours')->format('Y-m-d\TH:i'));
    update_post_meta($auction_id, '_lw_end_at', $now->modify('-1 hour')->format('Y-m-d\TH:i'));
    auction_check($state->invoke(null, $auction_id) === 'ended', 'Past schedule must remain ended.');
} finally {
    $wpdb->query('ROLLBACK');
    if ($auction_id) clean_post_cache($auction_id);
}
echo "PASS: auction dates, bid amounts and schedule boundaries.\n";

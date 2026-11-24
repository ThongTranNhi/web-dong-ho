<?php
/**
 * TEST FIXTURE ONLY — never deploy.
 * Copied to wp-content/mu-plugins/ by scripts/tests/chatbox_frontend.js and
 * removed afterwards. Replaces Gemini with a deterministic provider that
 * answers from the website data the backend sends, so the real REST API,
 * database and frontend can be exercised without network calls or cost.
 *
 * Markers in the customer message:
 *   #qua-tai  first attempt fails with ai_unavailable (retry_after 3), retry succeeds
 *   #xss      answer contains HTML/script text (must render as plain text)
 */
if (!defined('ABSPATH')) exit;

// Short browser timeout so the client-timeout path can be tested quickly.
add_filter('luxury_chatbox_frontend_config', static function ($config) {
    $config['sendTimeoutMs'] = 4000;
    return $config;
});

add_filter('luxury_chatbox_provider', static function () {
    if (!interface_exists('LC_AI_Provider')) return null;
    return new class implements LC_AI_Provider {
        public function is_configured() { return true; }

        public function generate($system, array $messages, array $schema) {
            usleep(700000);
            $last = end($messages)['content'];
            preg_match('#<website_data>\n(.*)\n</website_data>#s', $last, $d);
            preg_match('#<customer_message>\n(.*)\n</customer_message>#s', $last, $q);
            $ctx = json_decode($d[1] ?? '{}', true) ?: [];
            $question = $q[1] ?? '';
            if (strpos($question, '#qua-tai') !== false) {
                $key = 'lc_mock_fail_' . md5($question);
                if (!get_transient($key)) {
                    set_transient($key, 1, 300);
                    throw new LC_AI_Error('ai_unavailable', 'Trợ lý AI đang tạm gián đoạn. Vui lòng thử lại sau.', true, 3, 'mock 503');
                }
                delete_transient($key);
            }
            $fmt = static function ($n) { return number_format((float) $n, 0, ',', '.') . ' ₫'; };
            $parts = [];
            $product_ids = [];
            $auction_ids = [];
            $urls = [];
            $products = $ctx['products'] ?? [];
            if (!$products && !empty($ctx['alternative_products'])) {
                $parts[] = 'Hiện chưa có mẫu đúng tiêu chí. Mẫu gần nhất:';
                $products = $ctx['alternative_products'];
            }
            foreach (array_slice($products, 0, 3) as $p) {
                $parts[] = sprintf('%s giá %s, %s.', $p['name'], $fmt($p['price']), $p['in_stock'] ? 'còn hàng' : 'hết hàng');
                $product_ids[] = $p['id'];
            }
            if (count($messages) > 1 && $products) {
                $cheapest = $products[0];
                foreach ($products as $p) if ($p['price'] < $cheapest['price']) $cheapest = $p;
                $parts[] = sprintf('Trong các mẫu đã nhắc, mẫu rẻ nhất là %s (%s).', $cheapest['name'], $fmt($cheapest['price']));
            }
            foreach (array_slice($ctx['auctions'] ?? [], 0, 2) as $a) {
                $parts[] = sprintf('Phiên %s: %s, giá hiện tại %s, bước giá %s.', $a['title'], $a['state_label'], $fmt($a['current_bid']), $fmt($a['bid_increment']));
                $auction_ids[] = $a['id'];
            }
            foreach ($ctx['policies'] ?? [] as $page) {
                $sentence = preg_split('/(?<=[.!?])\s/u', $page['content'])[0] ?? '';
                $parts[] = $page['title'] . ': ' . $sentence;
                $urls[] = $page['url'];
            }
            if (strpos($question, '#xss') !== false) $parts[] = '<img src=x onerror="window.__lwcXss=1"><script>window.__lwcXss=1</script>';
            if (!$parts) $parts[] = 'Tôi có thể giúp bạn chọn đồng hồ, giải đáp chính sách và thông tin đấu giá.';
            return [
                'data' => [
                    'answer' => implode("\n", $parts),
                    'product_ids' => $product_ids,
                    'auction_ids' => $auction_ids,
                    'source_urls' => $urls,
                    'missing_info' => mb_stripos($question, 'trả góp') !== false,
                ],
                'model' => 'mock',
                'usage' => [],
            ];
        }
    };
}, 99);

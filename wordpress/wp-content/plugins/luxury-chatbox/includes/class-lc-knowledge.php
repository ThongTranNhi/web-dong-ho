<?php
if (!defined('ABSPATH')) exit;

/**
 * Builds the minimal, public, live website data needed to answer one
 * question. Nothing here reads customer records: only published products,
 * published pages and published auctions (without bidder identities or the
 * confidential reserve price).
 */
final class LC_Knowledge {
    const MAX_PRODUCTS = 6;
    const MAX_ALTERNATIVES = 3;
    const MAX_AUCTIONS = 6;
    const MAX_POLICIES = 3;
    const POLICY_CHARS = 3500;
    const AUCTION_PRODUCT_CATEGORY = 'bo-suu-tap-dau-gia';

    /** slug => trigger regex (matched against lower-cased text). */
    private static function policy_triggers() {
        return [
            'lien-he' => '/liên hệ|lien he|showroom|cửa hàng ở đâu|địa chỉ|dia chi|ở đâu|o dau|hotline|số điện thoại|sđt|sdt|chi nhánh|tổng đài|giờ mở cửa|thời gian làm việc|email|hỗ trợ/u',
            'gioi-thieu' => '/giới thiệu|gioi thieu|về chúng tôi|ve chung toi|cửa hàng là ai|shop là ai|website này|trang web này|watch store|watchstore|luxury atelier|lịch sử|nguồn gốc|uy tín|cam kết|atelier/u',
            'chinh-sach-bao-hanh' => '/bảo hành|bao hanh|sửa chữa|hỏng|bảo dưỡng|thay pin|lau dầu/u',
            'chinh-sach-doi-tra' => '/đổi trả|doi tra|trả hàng|hoàn tiền|hoàn trả|đổi hàng|đổi sản phẩm|1 đổi 1/u',
            'chinh-sach-van-chuyen' => '/vận chuyển|van chuyen|giao hàng|giao hang|ship|phí giao|nhận hàng|bao lâu.*(nhận|giao)|hỏa tốc/u',
            'tieu-chuan-giao-nhan-hang' => '/tiêu chuẩn|giao nhận|đóng gói|đồng kiểm|kiểm tra khi nhận|tem niêm phong|hộp|mở hộp/u',
            'cau-hoi-thuong-gap' => '/câu hỏi|thắc mắc|faq|hỏi đáp|thông tin thêm/u',
            'thuong-hieu-dong-ho' => '/thương hiệu|hãng nào|rolex|omega|patek|hublot|tissot|casio|hãng đồng hồ/u',
            'dieu-khoan-va-dieu-kien' => '/điều khoản|dieu khoan|quy định|quy che|điều kiện/u',
            'chinh-sach-bao-mat' => '/bảo mật|bao mat|dữ liệu cá nhân|thông tin cá nhân|quyền riêng tư/u',
            'huong-dan-mua-hang' => '/đặt hàng|dat hang|thanh toán|thanh toan|cách mua|mua như thế nào|giỏ hàng|trả góp|hóa đơn/u',
            'huong-dan-dau-gia' => '/(cách|hướng dẫn|làm sao|làm thế nào|quy trình|quy tắc|điều kiện).*(đấu giá|trả giá)|(đấu giá|trả giá).*(như thế nào|ra sao|thế nào)/u',
        ];
    }

    public static function normalize($text) {
        $text = function_exists('mb_strtolower') ? mb_strtolower((string) $text, 'UTF-8') : strtolower((string) $text);
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** Criteria parsed from the question plus recent user turns (for follow-ups). */
    public static function parse_criteria($text) {
        $t = self::normalize($text);
        $criteria = ['min' => null, 'max' => null, 'gender' => null, 'size_min' => null, 'size_max' => null, 'keywords' => []];

        $num = '(\d+(?:[.,]\d+)?)\s*(tỷ|ty|triệu|trieu|tr|củ|m|k|nghìn|ngàn|ngan|đ|vnd|vnđ)?';
        $range = '/(?:từ|tu)?\s*' . $num . '\s*(?:đến|den|tới|toi|-|–)\s*' . $num . '/u';
        if (preg_match($range, $t, $m)) {
            $a = self::money($m[1], $m[2] ?? '' ?: ($m[4] ?? ''));
            $b = self::money($m[3], $m[4] ?? '');
            if ($a && $b && $b >= $a && $b >= 1000000) { $criteria['min'] = $a; $criteria['max'] = $b; }
        }
        if ($criteria['max'] === null && preg_match('/(?:dưới|duoi|tối đa|toi da|không quá|khong qua|chưa tới|chưa đến|max|<=?|trong tầm)\s*' . $num . '/u', $t, $m)) {
            $criteria['max'] = self::money($m[1], $m[2] ?? '');
        }
        if ($criteria['min'] === null && preg_match('/(?:trên|tren|tối thiểu|toi thieu|ít nhất|it nhat|từ|>=?)\s*' . $num . '(?!\s*(?:đến|den|tới|toi|-))/u', $t, $m)) {
            $value = self::money($m[1], $m[2] ?? '');
            if ($value >= 1000000) $criteria['min'] = $value;
        }
        if ($criteria['min'] === null && $criteria['max'] === null && preg_match('/(?:khoảng|khoang|tầm|tam|cỡ|chừng|budget|ngân sách|ngan sach|có)\s*' . $num . '/u', $t, $m)) {
            $value = self::money($m[1], $m[2] ?? '');
            if ($value >= 1000000) { $criteria['min'] = (int) round($value * 0.8); $criteria['max'] = (int) round($value * 1.15); }
        }
        foreach (['min', 'max'] as $key) {
            if ($criteria[$key] !== null && $criteria[$key] < 1000000) $criteria[$key] = null;
        }

        $word = static function ($pattern) use ($t) {
            return (bool) preg_match('/(?<![\p{L}])(?:' . $pattern . ')(?![\p{L}])/u', $t);
        };
        $female = $word('nữ|nu|phụ nữ|vợ|bạn gái|người yêu nữ|mẹ|chị|em gái|cô ấy');
        $male = $word('nam|đàn ông|chồng|bạn trai|bố|ba|anh trai|anh ấy|ông');
        if ($female && !$male) $criteria['gender'] = 'nu';
        if ($male && !$female) $criteria['gender'] = 'nam';

        if (preg_match('/(\d{2})\s*mm/u', $t, $m)) {
            $criteria['size_min'] = (int) $m[1] - 2;
            $criteria['size_max'] = (int) $m[1] + 2;
        } elseif ($word('nhỏ|nhỏ gọn|cổ tay nhỏ|mảnh|thanh mảnh')) {
            $criteria['size_max'] = 36;
        } elseif ($word('lớn|to|cổ tay to|hầm hố')) {
            $criteria['size_min'] = 41;
        }

        $styles = [
            'lặn' => ['lặn', 'diver', 'chống nước 300', 'bezel'],
            'thể thao' => ['thể thao', 'chronograph', 'dây liền', 'đường đua'],
            'chronograph' => ['chronograph'],
            'thanh lịch' => ['thanh lịch', 'dress', 'cổ điển', 'grand feu'],
            'công sở' => ['thanh lịch', 'dress', 'cổ điển'],
            'cổ điển' => ['cổ điển', 'art deco', 'sector', 'guilloché'],
            'tuần trăng' => ['tuần trăng', 'moonphase'],
            'moonphase' => ['tuần trăng', 'moonphase'],
            'xà cừ' => ['xà cừ'],
            'trang sức' => ['trang sức', 'pha lê', 'kim cương', 'diamond'],
            'kim cương' => ['kim cương', 'diamond'],
            'du lịch' => ['múi giờ', 'gmt', 'worldtimer', 'hành trình'],
            'gmt' => ['gmt', 'múi giờ'],
            'phi công' => ['pilot', 'hàng không', 'flyback'],
            'tự động' => ['tự động', 'automatic'],
            'cơ' => ['tự động', 'automatic', 'cơ'],
            'ceramic' => ['ceramic'],
            'titanium' => ['titanium', 'titan'],
            'vàng hồng' => ['vàng hồng'],
            'dây da' => ['dây da'],
            'dây kim loại' => ['dây thép', 'dây kim loại'],
            'hằng ngày' => ['hằng ngày', 'thường ngày', 'field'],
        ];
        foreach ($styles as $trigger => $terms) {
            if ($word(preg_quote($trigger, '/'))) $criteria['keywords'] = array_merge($criteria['keywords'], $terms);
        }
        $criteria['keywords'] = array_values(array_unique($criteria['keywords']));
        return $criteria;
    }

    private static function money($number, $unit) {
        $unit = (string) $unit;
        $raw = (string) $number;
        if (in_array($unit, ['', 'đ', 'vnd', 'vnđ'], true) && preg_match('/\A\d{1,3}([.,]\d{3})+\z/', $raw)) {
            return (int) str_replace(['.', ','], '', $raw);
        }
        $value = (float) str_replace(',', '.', $raw);
        $multiplier = 1;
        if (in_array($unit, ['tỷ', 'ty'], true)) $multiplier = 1000000000;
        elseif (in_array($unit, ['triệu', 'trieu', 'tr', 'củ', 'm'], true)) $multiplier = 1000000;
        elseif (in_array($unit, ['k', 'nghìn', 'ngàn', 'ngan'], true)) $multiplier = 1000;
        elseif ($unit === '' && $value > 0 && $value < 1000) $multiplier = 1000000; // "dưới 30" in a watch shop means 30 triệu
        return (int) round($value * $multiplier);
    }

    public static function detect_intents($text) {
        $t = self::normalize($text);
        $intents = ['products' => false, 'auctions' => false, 'policies' => []];
        foreach (self::policy_triggers() as $slug => $regex) {
            if (preg_match($regex, $t)) $intents['policies'][] = $slug;
        }
        $intents['auctions'] = (bool) preg_match('/đấu giá|dau gia|trả giá|tra gia|phiên|lot\s*\d|bước giá|buoc gia|giá khởi điểm|đặt giá/u', $t);
        $intents['products'] = (bool) preg_match('/đồng hồ|dong ho|mẫu|gợi ý|goi y|tư vấn|tu van|còn hàng|tồn kho|giá|triệu|ngân sách|mm|tặng|quà|nam|nữ|chronograph|moonphase|lặn|thể thao|thanh lịch|dây|mặt số|sản phẩm|bộ sưu tập/u', $t);
        if (!$intents['products'] && !$intents['auctions'] && !$intents['policies']) {
            $intents['policies'][] = 'gioi-thieu';
            $intents['policies'][] = 'cau-hoi-thuong-gap';
        }
        // Policy or auction questions only pull products when there is a real shopping signal.
        if (($intents['auctions'] || $intents['policies']) && $intents['products']) {
            $c = self::parse_criteria($t);
            $shopping = $c['min'] || $c['max'] || $c['size_min'] || $c['size_max'] || $c['keywords']
                || preg_match('/gợi ý|goi y|tư vấn|tu van|mẫu nào|còn hàng|tồn kho|giá bao nhiêu|mua đồng hồ|đồng hồ (nam|nữ)/u', $t);
            if (!$shopping) $intents['products'] = false;
        }
        $intents['policies'] = array_slice(array_values(array_unique($intents['policies'])), 0, self::MAX_POLICIES);
        return $intents;
    }

    /**
     * @param string $question     The current message.
     * @param string $recent_turns Earlier user messages, for follow-up questions.
     */
    public static function build($question, $recent_turns = '') {
        $intent_text = trim($recent_turns . "\n" . $question);
        $intents = self::detect_intents($question);
        $follow_up = self::detect_intents($intent_text);
        // A short follow-up ("mẫu thứ hai còn hàng không?") inherits the previous topic.
        if (!$intents['auctions'] && $follow_up['auctions'] && mb_strlen($question) < 80) $intents['auctions'] = true;
        if (!$intents['products'] && $follow_up['products'] && mb_strlen($question) < 80 && !$intents['policies']) $intents['products'] = true;

        $context = [
            'now' => wp_date('H:i d/m/Y'),
            'timezone' => wp_timezone_string(),
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'VND',
            'store_overview' => [
                'name' => 'WatchStore - Luxury Watches Atelier',
                'tagline' => 'Đỉnh cao chế tác Thụy Sĩ & Di sản độc bản',
                'website' => home_url('/'),
                'hotline' => '0988 123 456 (Tư vấn 24/7)',
                'email' => 'concierge@watchstore.luxury',
                'working_hours' => '08:30 - 21:30 (Mở cửa tất cả các ngày trong tuần, kể cả ngày Lễ)',
                'showrooms' => [
                    'Hà Nội 1: 97 Trần Hưng Đạo, P. Cửa Nam, Hoàn Kiếm, Hà Nội (Flagship Atelier)',
                    'Hà Nội 2: 58 Tràng Tiền, P. Tràng Tiền, Hoàn Kiếm, Hà Nội',
                    'TP. Hồ Chí Minh 1: 125 Đồng Khởi, P. Bến Nghé, Quận 1, TP. HCM',
                    'TP. Hồ Chí Minh 2: 72 Lê Thánh Tôn, P. Bến Nghé, Quận 1, TP. HCM',
                    'Đà Nẵng: 36 Bạch Đằng, P. Thạch Thang, Hải Châu, Đà Nẵng',
                ],
                'core_commitments' => [
                    '100% Chính hãng: Đền gấp 20 lần nếu phát hiện hàng giả, hàng nhái.',
                    'Bảo hành 5 năm: Toàn diện bộ máy theo tiêu chuẩn Thụy Sĩ.',
                    'Thay pin trọn đời: Áp dụng miễn phí vĩnh viễn cho đồng hồ Quartz.',
                    'Lau dầu bảo dưỡng định kỳ miễn phí trọn đời cho đồng hồ cơ khí.',
                    '1 Đổi 1 trong 7 ngày nếu có lỗi kỹ thuật từ nhà sản xuất.',
                    'Giao hàng hỏa tốc toàn quốc, miễn phí 100%, bảo hiểm toàn diện, cho phép mở hộp kiểm tra trước khi thanh toán.',
                    'Sàn đấu giá độc bản: Đấu giá trực tuyến các cỗ máy độc bản minh bạch và công khai.',
                ],
                'url' => home_url('/lien-he/'),
            ],
            'criteria' => null,
            'products' => [],
            'alternative_products' => [],
            'auctions' => [],
            'policies' => [],
            'unavailable' => [],
        ];
        $sources = [
            home_url('/lien-he/') => 'Hệ thống Showroom & Dịch vụ Khách hàng VIP',
            home_url('/gioi-thieu/') => 'Về WatchStore Luxury Atelier',
        ];

        if ($intents['products']) {
            // The current question wins; earlier turns only fill criteria it leaves open.
            $criteria = self::parse_criteria($question);
            if ($recent_turns !== '') {
                $previous = self::parse_criteria($recent_turns);
                if ($criteria['min'] === null && $criteria['max'] === null) { $criteria['min'] = $previous['min']; $criteria['max'] = $previous['max']; }
                if ($criteria['size_min'] === null && $criteria['size_max'] === null) { $criteria['size_min'] = $previous['size_min']; $criteria['size_max'] = $previous['size_max']; }
                if ($criteria['gender'] === null) $criteria['gender'] = $previous['gender'];
                if (!$criteria['keywords']) $criteria['keywords'] = $previous['keywords'];
            }
            $context['criteria'] = self::criteria_for_model($criteria);
            [$matches, $alternatives] = self::products($criteria);
            $context['products'] = $matches;
            $context['alternative_products'] = $alternatives;
            if (!function_exists('wc_get_products')) $context['unavailable'][] = 'Dữ liệu sản phẩm (WooCommerce chưa hoạt động).';
            foreach (array_merge($matches, $alternatives) as $p) $sources[$p['url']] = $p['name'];
        }
        if ($intents['auctions']) {
            $context['auctions'] = self::auctions($question);
            if (!class_exists('Luxury_Watches_Auctions')) $context['unavailable'][] = 'Dữ liệu đấu giá (plugin đấu giá chưa hoạt động).';
            foreach ($context['auctions'] as $a) $sources[$a['url']] = $a['title'];
            if (!in_array('huong-dan-dau-gia', $intents['policies'], true) && count($intents['policies']) < self::MAX_POLICIES) {
                $intents['policies'][] = 'huong-dan-dau-gia';
            }
        }
        foreach ($intents['policies'] as $slug) {
            $page = self::policy($slug);
            if (!$page) { $context['unavailable'][] = 'Trang "' . $slug . '" chưa được xuất bản.'; continue; }
            $context['policies'][] = $page;
            $sources[$page['url']] = $page['title'];
        }

        $intents_used = array_keys(array_filter(['products' => $intents['products'], 'auctions' => $intents['auctions'], 'policies' => (bool) $intents['policies']]));
        return ['context' => $context, 'sources' => $sources, 'intents' => $intents_used];
    }

    private static function criteria_for_model(array $c) {
        return array_filter([
            'gia_toi_thieu' => $c['min'],
            'gia_toi_da' => $c['max'],
            'gioi_tinh' => $c['gender'] === 'nam' ? 'Nam' : ($c['gender'] === 'nu' ? 'Nữ' : null),
            'kich_thuoc_mm' => ($c['size_min'] || $c['size_max']) ? trim(($c['size_min'] ? $c['size_min'] : '') . '-' . ($c['size_max'] ? $c['size_max'] : '')) : null,
            'phong_cach' => $c['keywords'] ? implode(', ', array_slice($c['keywords'], 0, 6)) : null,
        ], static function ($v) { return $v !== null && $v !== ''; });
    }

    private static function plain($html, $limit) {
        $text = html_entity_decode(wp_strip_all_tags(strip_shortcodes((string) $html), true), ENT_QUOTES, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit)) . '…' : $text;
    }

    /** Returns [matches, alternatives] as model-ready arrays, read live from WooCommerce. */
    public static function products(array $criteria) {
        if (!function_exists('wc_get_products')) return [[], []];
        $all = wc_get_products(['status' => 'publish', 'limit' => 300, 'orderby' => 'date', 'order' => 'DESC']);
        $matches = [];
        $outside = [];
        foreach ($all as $product) {
            if (!$product->is_visible()) continue;
            $slugs = [];
            foreach ($product->get_category_ids() as $cid) {
                $term = get_term($cid, 'product_cat');
                if ($term && !is_wp_error($term)) $slugs[] = $term->slug;
            }
            if (in_array(self::AUCTION_PRODUCT_CATEGORY, $slugs, true)) continue; // sold through auctions, not the shop
            $price = (int) round((float) $product->get_price());
            if ($price <= 0) continue;
            $gender = in_array('dong-ho-nu', $slugs, true) ? 'nu' : (in_array('dong-ho-nam', $slugs, true) ? 'nam' : null);
            $text = self::normalize($product->get_name() . ' ' . $product->get_short_description() . ' ' . $product->get_description());
            $size = preg_match('/(\d{2})\s*mm/u', $text, $m) ? (int) $m[1] : null;

            if ($criteria['gender'] && $gender !== $criteria['gender']) continue;
            $score = 0;
            foreach ($criteria['keywords'] as $kw) if (mb_strpos($text, $kw) !== false) $score += 3;
            if ($product->is_in_stock()) $score += 2;
            $size_ok = true;
            if ($size !== null && $criteria['size_min'] && $size < $criteria['size_min']) $size_ok = false;
            if ($size !== null && $criteria['size_max'] && $size > $criteria['size_max']) $size_ok = false;
            if ($size_ok && ($criteria['size_min'] || $criteria['size_max'])) $score += 2;
            if (!$size_ok) $score -= 4;

            $row = self::product_row($product, $price, $gender, $size);
            $in_budget = (!$criteria['min'] || $price >= $criteria['min']) && (!$criteria['max'] || $price <= $criteria['max']);
            if ($in_budget) {
                $matches[] = ['score' => $score, 'row' => $row];
            } else {
                $distance = $criteria['max'] && $price > $criteria['max'] ? $price - $criteria['max'] : ($criteria['min'] ? $criteria['min'] - $price : 0);
                $outside[] = ['score' => $score, 'distance' => $distance, 'row' => $row];
            }
        }
        usort($matches, static function ($a, $b) { return [$b['score'], $a['row']['price']] <=> [$a['score'], $b['row']['price']]; });
        usort($outside, static function ($a, $b) { return [$a['distance'], -$a['score']] <=> [$b['distance'], -$b['score']]; });
        $matches = array_slice(array_column($matches, 'row'), 0, self::MAX_PRODUCTS);
        $alternatives = [];
        if (count($matches) < 2 && ($criteria['min'] || $criteria['max'])) {
            $alternatives = array_slice(array_column($outside, 'row'), 0, self::MAX_ALTERNATIVES);
        }
        return [$matches, $alternatives];
    }

    private static function product_row($product, $price, $gender, $size) {
        $regular = (int) round((float) $product->get_regular_price());
        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price' => $price,
            'regular_price' => $regular ?: $price,
            'on_sale' => $product->is_on_sale(),
            'in_stock' => $product->is_in_stock(),
            'stock_quantity' => $product->managing_stock() ? (int) $product->get_stock_quantity() : null,
            'gender' => $gender === 'nam' ? 'Nam' : ($gender === 'nu' ? 'Nữ' : 'Unisex/chưa phân loại'),
            'case_size_mm' => $size,
            'summary' => self::plain($product->get_short_description() ?: $product->get_description(), 220),
            'details' => self::plain($product->get_description(), 320),
            'url' => get_permalink($product->get_id()),
            'image' => $product->get_image_id() ? wp_get_attachment_image_url($product->get_image_id(), 'woocommerce_thumbnail') : null,
        ];
    }

    public static function auctions($question) {
        if (!class_exists('Luxury_Watches_Auctions') || !method_exists('Luxury_Watches_Auctions', 'snapshot')) return [];
        $ids = get_posts(['post_type' => 'watch_auction', 'post_status' => 'publish', 'posts_per_page' => 50, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC']);
        $t = self::normalize($question);
        $rows = [];
        foreach ($ids as $id) {
            $snap = Luxury_Watches_Auctions::snapshot($id);
            if (!$snap) continue;
            $title = self::normalize($snap['title']);
            $lot = (string) get_post_meta($id, '_lw_lot_number', true);
            $mentioned = mb_strpos($t, $title) !== false || ($lot !== '' && mb_strpos($t, self::normalize($lot)) !== false);
            if (!$mentioned) {
                foreach (preg_split('/\s+/u', $title) as $w) if (mb_strlen($w) >= 5 && mb_strpos($t, $w) !== false) { $mentioned = true; break; }
            }
            $rank = ['live' => 0, 'upcoming' => 1, 'ended' => 2, 'invalid' => 3][$snap['state']] ?? 4;
            $rows[] = ['mentioned' => $mentioned, 'rank' => $rank, 'row' => [
                'id' => $snap['id'],
                'title' => $snap['title'],
                'lot' => $lot ?: null,
                'category' => (string) get_post_meta($id, '_lw_gender', true) ?: null,
                'state' => $snap['state'],
                'state_label' => $snap['state_label'],
                'starts_at' => $snap['starts_at'] ? wp_date('H:i d/m/Y', strtotime($snap['starts_at'])) : null,
                'ends_at' => $snap['ends_at'] ? wp_date('H:i d/m/Y', strtotime($snap['ends_at'])) : null,
                'start_price' => $snap['start_price'],
                'current_bid' => $snap['current_bid'],
                'minimum_bid' => $snap['state'] === 'live' || $snap['state'] === 'upcoming' ? $snap['minimum_bid'] : null,
                'bid_increment' => $snap['bid_increment'],
                'bid_count' => $snap['bid_count'],
                'accepting_bids' => $snap['state'] === 'live',
                'url' => $snap['permalink'],
                'image' => $snap['image'] ?: null,
            ]];
        }
        $mentioned = array_filter($rows, static function ($r) { return $r['mentioned']; });
        $pool = $mentioned ?: $rows;
        usort($pool, static function ($a, $b) { return $a['rank'] <=> $b['rank']; });
        return array_slice(array_column($pool, 'row'), 0, self::MAX_AUCTIONS);
    }

    public static function policy($slug) {
        $page = get_page_by_path($slug, OBJECT, 'page');
        if (!$page || $page->post_status !== 'publish' || post_password_required($page)) return null;
        return [
            'slug' => $slug,
            'title' => get_the_title($page),
            'url' => get_permalink($page),
            'content' => self::plain($page->post_content, self::POLICY_CHARS),
        ];
    }
}

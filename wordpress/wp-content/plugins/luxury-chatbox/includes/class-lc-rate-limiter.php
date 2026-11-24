<?php
if (!defined('ABSPATH')) exit;

/**
 * Limits are counted from the messages table, so they hold across PHP
 * workers without relying on a persistent object cache.
 */
final class LC_Rate_Limiter {
    /** @return array|null null when allowed, otherwise [code, message, retry_after] */
    public static function check(array $owner) {
        $now = time();
        $minute = gmdate('Y-m-d H:i:s', $now - MINUTE_IN_SECONDS);

        if (LC_Repository::count_owner_user_messages_since($owner, $minute) >= (int) LC_Settings::get('per_minute')) {
            return ['rate_limited', 'Bạn gửi tin nhắn quá nhanh. Vui lòng chờ khoảng một phút rồi thử lại.', 60];
        }
        return self::check_ai_quota($owner);
    }

    /**
     * Daily quotas count every AI call (main model, fallback model and manual
     * retries), so it is checked again before a fallback call.
     */
    public static function check_ai_quota(array $owner) {
        $day = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
        if (LC_Repository::count_owner_ai_calls_since($owner, $day) >= (int) LC_Settings::get('per_day')) {
            return ['quota_exceeded', 'Bạn đã dùng hết lượt hỏi trong 24 giờ. Vui lòng quay lại sau hoặc liên hệ cửa hàng.', HOUR_IN_SECONDS];
        }
        if (LC_Repository::count_ai_calls_since($day) >= (int) LC_Settings::get('global_per_day')) {
            return ['service_quota_exceeded', 'Trợ lý đang tạm ngưng do đạt hạn mức sử dụng trong ngày. Vui lòng thử lại sau.', HOUR_IN_SECONDS];
        }
        return null;
    }
}

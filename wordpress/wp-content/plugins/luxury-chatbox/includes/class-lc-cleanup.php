<?php
if (!defined('ABSPATH')) exit;

/** Daily removal of conversations inactive longer than the retention period. */
final class LC_Cleanup {
    const HOOK = 'luxury_chatbox_cleanup';

    public static function init() {
        add_action(self::HOOK, [__CLASS__, 'run']);
        add_action('init', [__CLASS__, 'schedule']);
    }

    public static function schedule() {
        if (!wp_next_scheduled(self::HOOK)) wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::HOOK);
    }

    public static function unschedule() {
        wp_clear_scheduled_hook(self::HOOK);
    }

    /** @return int number of conversations deleted */
    public static function run() {
        $days = (int) LC_Settings::get('retention_days');
        $before = gmdate('Y-m-d H:i:s', time() - max(1, $days) * DAY_IN_SECONDS);
        $total = 0;
        for ($i = 0; $i < 40; $i++) { // at most 20,000 conversations per run
            $deleted = LC_Repository::delete_inactive_before($before, 500);
            $total += $deleted;
            if ($deleted < 500) break;
        }
        update_option('luxury_chatbox_last_cleanup', ['at' => time(), 'deleted' => $total], false);
        return $total;
    }
}

<?php
if (!defined('ABSPATH')) exit;

/**
 * All SQL for conversations and messages. Every read or write that takes a
 * conversation goes through an owner check built from the current session,
 * never from a client-supplied identifier alone.
 */
final class LC_Repository {
    private static function now() {
        return current_time('mysql', true);
    }

    /** SQL fragment + args restricting conversations to the given owner. */
    private static function owner_clause(array $owner) {
        if ($owner['user_id'] > 0) return ['user_id = %d', [$owner['user_id']]];
        return ['user_id = 0 AND guest_hash = %s', [$owner['guest_hash']]];
    }

    public static function find_latest_conversation(array $owner) {
        global $wpdb;
        if ($owner['user_id'] <= 0 && $owner['guest_hash'] === '') return null;
        [$where, $args] = self::owner_clause($owner);
        $table = LC_Installer::conversations_table();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE {$where} ORDER BY last_activity_at DESC, id DESC LIMIT 1", ...$args));
    }

    public static function create_conversation(array $owner) {
        global $wpdb;
        $now = self::now();
        $ok = $wpdb->insert(LC_Installer::conversations_table(), [
            'user_id' => $owner['user_id'],
            'guest_hash' => $owner['user_id'] > 0 ? '' : $owner['guest_hash'],
            'created_at' => $now,
            'last_activity_at' => $now,
        ], ['%d', '%s', '%s', '%s']);
        if (!$ok) return null;
        return self::get_owned_conversation((int) $wpdb->insert_id, $owner);
    }

    public static function get_owned_conversation($conversation_id, array $owner) {
        global $wpdb;
        [$where, $args] = self::owner_clause($owner);
        $table = LC_Installer::conversations_table();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d AND {$where}", (int) $conversation_id, ...$args));
    }

    public static function touch_conversation($conversation_id) {
        global $wpdb;
        $wpdb->update(LC_Installer::conversations_table(), ['last_activity_at' => self::now()], ['id' => (int) $conversation_id], ['%s'], ['%d']);
    }

    public static function messages($conversation_id, $limit = 100) {
        global $wpdb;
        $table = LC_Installer::messages_table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE conversation_id = %d ORDER BY id DESC LIMIT %d",
            (int) $conversation_id, (int) $limit
        ));
        return array_reverse($rows ?: []);
    }

    /** Completed user/assistant pairs used as model context, oldest first. */
    public static function history_for_model($conversation_id, $limit, $exclude_request_id) {
        if ($limit <= 0) return [];
        global $wpdb;
        $table = LC_Installer::messages_table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE conversation_id = %d AND status = 'completed' AND request_id <> %s ORDER BY id DESC LIMIT %d",
            (int) $conversation_id, (string) $exclude_request_id, (int) $limit
        ));
        return array_reverse($rows ?: []);
    }

    public static function find_by_request($conversation_id, $request_id, $role) {
        global $wpdb;
        $table = LC_Installer::messages_table();
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE conversation_id = %d AND request_id = %s AND role = %s",
            (int) $conversation_id, (string) $request_id, (string) $role
        ));
    }

    /**
     * Inserts the user message as the idempotency lock. Returns the new row,
     * or false when this request ID was already recorded (unique key).
     */
    public static function insert_user_message($conversation_id, $request_id, $content) {
        global $wpdb;
        $suppress = $wpdb->suppress_errors(true);
        $ok = $wpdb->insert(LC_Installer::messages_table(), [
            'conversation_id' => (int) $conversation_id,
            'role' => 'user',
            'content' => $content,
            'status' => 'pending',
            'request_id' => $request_id,
            'meta' => null,
            'created_at' => self::now(),
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s']);
        $wpdb->suppress_errors($suppress);
        if (!$ok) return false;
        return self::find_by_request($conversation_id, $request_id, 'user');
    }

    /**
     * Atomically claims a failed (or abandoned pending) request for a retry.
     * Only one concurrent caller can win the UPDATE.
     */
    public static function claim_for_retry($message_id, $stale_before) {
        global $wpdb;
        $table = LC_Installer::messages_table();
        $claimed = $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = 'pending', created_at = %s WHERE id = %d AND role = 'user' AND (status = 'failed' OR (status = 'pending' AND created_at < %s))",
            self::now(), (int) $message_id, $stale_before
        ));
        return $claimed === 1;
    }

    public static function set_status($message_id, $status, $meta = null) {
        global $wpdb;
        $data = ['status' => $status];
        $format = ['%s'];
        if ($meta !== null) { $data['meta'] = wp_json_encode($meta); $format[] = '%s'; }
        $wpdb->update(LC_Installer::messages_table(), $data, ['id' => (int) $message_id], $format, ['%d']);
    }

    public static function insert_assistant_message($conversation_id, $request_id, $content, array $meta) {
        global $wpdb;
        $ok = $wpdb->insert(LC_Installer::messages_table(), [
            'conversation_id' => (int) $conversation_id,
            'role' => 'assistant',
            'content' => $content,
            'status' => 'completed',
            'request_id' => $request_id,
            'meta' => wp_json_encode($meta),
            'created_at' => self::now(),
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s']);
        if (!$ok) return null;
        return self::find_by_request($conversation_id, $request_id, 'assistant');
    }

    public static function delete_conversation($conversation_id) {
        global $wpdb;
        $wpdb->delete(LC_Installer::messages_table(), ['conversation_id' => (int) $conversation_id], ['%d']);
        $wpdb->delete(LC_Installer::conversations_table(), ['id' => (int) $conversation_id], ['%d']);
    }

    /** Counts this owner's user messages since a UTC datetime (rate limiting). */
    public static function count_owner_user_messages_since(array $owner, $since) {
        global $wpdb;
        [$where, $args] = self::owner_clause($owner);
        $c = LC_Installer::conversations_table();
        $m = LC_Installer::messages_table();
        $where = str_replace(['user_id', 'guest_hash'], ['c.user_id', 'c.guest_hash'], $where);
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$m} m INNER JOIN {$c} c ON c.id = m.conversation_id WHERE {$where} AND m.role = 'user' AND m.created_at >= %s",
            ...array_merge($args, [$since])
        ));
    }

    /** Records one call to the AI provider for this user message (main, fallback or retry). */
    public static function add_ai_call($message_id) {
        global $wpdb;
        $table = LC_Installer::messages_table();
        $wpdb->query($wpdb->prepare("UPDATE {$table} SET ai_calls = ai_calls + 1 WHERE id = %d", (int) $message_id));
    }

    /** Total AI calls site-wide since a UTC datetime. */
    public static function count_ai_calls_since($since) {
        global $wpdb;
        $table = LC_Installer::messages_table();
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(ai_calls), 0) FROM {$table} WHERE role = 'user' AND created_at >= %s",
            $since
        ));
    }

    /** AI calls made for this owner's messages since a UTC datetime. */
    public static function count_owner_ai_calls_since(array $owner, $since) {
        global $wpdb;
        [$where, $args] = self::owner_clause($owner);
        $c = LC_Installer::conversations_table();
        $m = LC_Installer::messages_table();
        $where = str_replace(['user_id', 'guest_hash'], ['c.user_id', 'c.guest_hash'], $where);
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(m.ai_calls), 0) FROM {$m} m INNER JOIN {$c} c ON c.id = m.conversation_id WHERE {$where} AND m.role = 'user' AND m.created_at >= %s",
            ...array_merge($args, [$since])
        ));
    }

    /** Deletes up to $batch expired conversations and their messages. */
    public static function delete_inactive_before($before, $batch = 500) {
        global $wpdb;
        $c = LC_Installer::conversations_table();
        $ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$c} WHERE last_activity_at < %s ORDER BY id ASC LIMIT %d", $before, (int) $batch));
        if (!$ids) return 0;
        $ids = array_map('intval', $ids);
        $in = implode(',', $ids);
        $wpdb->query('DELETE FROM ' . LC_Installer::messages_table() . " WHERE conversation_id IN ({$in})");
        $wpdb->query("DELETE FROM {$c} WHERE id IN ({$in})");
        return count($ids);
    }

    public static function purge_all() {
        global $wpdb;
        $wpdb->query('TRUNCATE TABLE ' . LC_Installer::messages_table());
        $wpdb->query('TRUNCATE TABLE ' . LC_Installer::conversations_table());
    }

    public static function stats() {
        global $wpdb;
        return [
            'conversations' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . LC_Installer::conversations_table()),
            'messages' => (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . LC_Installer::messages_table()),
        ];
    }
}

<?php
if (!defined('ABSPATH')) exit;

final class LC_Installer {
    const DB_OPTION = 'luxury_chatbox_db_version';

    public static function conversations_table() {
        global $wpdb;
        return $wpdb->prefix . 'luxury_chat_conversations';
    }

    public static function messages_table() {
        global $wpdb;
        return $wpdb->prefix . 'luxury_chat_messages';
    }

    public static function activate() {
        self::install();
        LC_Cleanup::schedule();
    }

    public static function deactivate() {
        LC_Cleanup::unschedule();
    }

    public static function maybe_upgrade() {
        if (get_option(self::DB_OPTION) !== LC_DB_VERSION) self::install();
    }

    /** dbDelta is idempotent, so activation and upgrades can run any number of times. */
    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $conversations = self::conversations_table();
        $messages = self::messages_table();

        dbDelta("CREATE TABLE {$conversations} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            guest_hash char(64) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            last_activity_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_activity (user_id, last_activity_at),
            KEY guest_activity (guest_hash, last_activity_at),
            KEY last_activity (last_activity_at)
        ) {$charset};");

        dbDelta("CREATE TABLE {$messages} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            conversation_id bigint(20) unsigned NOT NULL,
            role varchar(16) NOT NULL,
            content longtext NOT NULL,
            status varchar(16) NOT NULL DEFAULT 'completed',
            request_id varchar(64) NOT NULL DEFAULT '',
            meta longtext NULL,
            ai_calls smallint unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY conversation_request_role (conversation_id, request_id, role),
            KEY conversation_created (conversation_id, created_at),
            KEY role_created (role, created_at),
            KEY created_at (created_at)
        ) {$charset};");

        update_option(self::DB_OPTION, LC_DB_VERSION, false);
    }

    public static function drop_all_data() {
        global $wpdb;
        $wpdb->query('DROP TABLE IF EXISTS ' . self::messages_table());
        $wpdb->query('DROP TABLE IF EXISTS ' . self::conversations_table());
        delete_option(self::DB_OPTION);
        delete_option(LC_Settings::OPTION);
    }
}

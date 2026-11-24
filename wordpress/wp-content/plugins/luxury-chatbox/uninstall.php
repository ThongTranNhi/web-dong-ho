<?php
/**
 * Runs when the plugin is deleted from WP Admin (not on deactivation).
 * Data is kept unless the admin opted in under Chatbox → "Khi gỡ plugin".
 */
if (!defined('WP_UNINSTALL_PLUGIN')) exit;

wp_clear_scheduled_hook('luxury_chatbox_cleanup');
$settings = get_option('luxury_chatbox_settings', []);
if (is_array($settings) && !empty($settings['delete_data_on_uninstall'])) {
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}luxury_chat_messages");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}luxury_chat_conversations");
    delete_option('luxury_chatbox_settings');
    delete_option('luxury_chatbox_db_version');
    delete_option('luxury_chatbox_last_cleanup');
}

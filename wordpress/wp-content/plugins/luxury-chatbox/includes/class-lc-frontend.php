<?php
if (!defined('ABSPATH')) exit;

/**
 * Loads the storefront chat widget. The widget only talks to this site's
 * REST API (luxury-chat/v1); no AI credentials ever reach the browser.
 */
final class LC_Frontend {
    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('wp_footer', [__CLASS__, 'mount'], 50);
    }

    private static function should_load() {
        if (is_admin() || wp_doing_ajax() || is_feed() || is_embed()) return false;
        $load = (bool) LC_Settings::get('enabled');
        return (bool) apply_filters('luxury_chatbox_show_widget', $load);
    }

    public static function assets() {
        if (!self::should_load()) return;
        $base = plugin_dir_url(LC_FILE) . 'assets/';
        $dir = LC_DIR . '/assets/';
        wp_enqueue_style('luxury-chatbox', $base . 'chatbox.css', [], (string) filemtime($dir . 'chatbox.css'));
        wp_enqueue_script('luxury-chatbox', $base . 'chatbox.js', [], (string) filemtime($dir . 'chatbox.js'), ['in_footer' => true, 'strategy' => 'defer']);
        // Browser-side wait: the backend's whole AI budget (main + fallback call) plus headroom.
        $send_timeout = (LC_Settings::max_ai_seconds() + 30) * 1000;
        $config = apply_filters('luxury_chatbox_frontend_config', [
            'restUrl' => esc_url_raw(rest_url('luxury-chat/v1/')),
            // Lets WordPress recognise logged-in members on REST requests.
            'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'sendTimeoutMs' => $send_timeout,
        ]);
        wp_localize_script('luxury-chatbox', 'LWChatConfig', $config);
    }

    public static function mount() {
        if (!self::should_load()) return;
        echo '<div id="lw-chatbox" class="lwc-root" data-lwc-root></div>';
    }
}

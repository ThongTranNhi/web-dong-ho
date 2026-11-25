<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin-editable settings. Secrets (the API key) are never stored here:
 * they come from the environment or wp-config.php only.
 */
final class LC_Settings {
    const OPTION = 'luxury_chatbox_settings';

    public static function defaults() {
        return [
            'enabled' => 0,
            'model' => 'gemini-3.8-flash',
            // Tried at most once per request, only when the main model returns HTTP 503. Empty = off.
            'fallback_model' => '',
            'max_message_chars' => 1000,
            'history_messages' => 8,
            'per_minute' => 6,
            'per_day' => 60,
            'global_per_day' => 2000,
            'timeout' => 30,
            'retention_days' => 30,
            'delete_data_on_uninstall' => 0,
        ];
    }

    /** Hard bounds keep a typo in the admin form from disabling the limits. */
    public static function bounds() {
        return [
            'max_message_chars' => [50, 4000],
            'history_messages' => [0, 20],
            'per_minute' => [1, 60],
            'per_day' => [1, 1000],
            'global_per_day' => [1, 100000],
            'timeout' => [5, 120],
            'retention_days' => [1, 365],
        ];
    }

    public static function all() {
        $saved = get_option(self::OPTION, []);
        $settings = array_merge(self::defaults(), is_array($saved) ? $saved : []);
        // Existing installations may still have a Claude model saved.
        if (!is_string($settings['model']) || !preg_match('/\Agemini-[a-z0-9.\-]{1,56}\z/', $settings['model'])) {
            $settings['model'] = self::defaults()['model'];
        }
        if (!self::valid_model($settings['fallback_model']) || $settings['fallback_model'] === $settings['model']) {
            $settings['fallback_model'] = '';
        }
        return $settings;
    }

    public static function valid_model($model) {
        return is_string($model) && preg_match('/\Agemini-[a-z0-9.\-]{1,56}\z/', $model) === 1;
    }

    /**
     * Longest time one chat request may spend waiting for AI: the main call
     * plus, when configured, one fallback call. The browser waits a little longer.
     */
    public static function max_ai_seconds() {
        $timeout = (int) self::get('timeout');
        return self::get('fallback_model') !== '' ? $timeout * 2 : $timeout;
    }

    public static function get($key) {
        $all = self::all();
        return $all[$key] ?? null;
    }

    public static function sanitize($input) {
        $input = is_array($input) ? $input : [];
        $clean = self::defaults();
        $clean['enabled'] = empty($input['enabled']) ? 0 : 1;
        $clean['delete_data_on_uninstall'] = empty($input['delete_data_on_uninstall']) ? 0 : 1;
        $model = isset($input['model']) ? sanitize_text_field((string) $input['model']) : '';
        $clean['model'] = preg_match('/\Agemini-[a-z0-9.\-]{1,56}\z/', $model) ? $model : self::defaults()['model'];
        $fallback = isset($input['fallback_model']) ? sanitize_text_field((string) $input['fallback_model']) : '';
        $clean['fallback_model'] = self::valid_model($fallback) && $fallback !== $clean['model'] ? $fallback : '';
        foreach (self::bounds() as $key => [$min, $max]) {
            $value = isset($input[$key]) ? (int) $input[$key] : $clean[$key];
            $clean[$key] = max($min, min($max, $value));
        }
        return $clean;
    }

    /** Returns [key, source] without ever exposing the key outside the server. */
    public static function api_key() {
        if (defined('LUXURY_CHATBOX_GEMINI_KEY') && is_string(LUXURY_CHATBOX_GEMINI_KEY) && LUXURY_CHATBOX_GEMINI_KEY !== '') {
            return [LUXURY_CHATBOX_GEMINI_KEY, 'wp-config.php'];
        }
        $env = getenv('GEMINI_API_KEY');
        if (is_string($env) && $env !== '') return [$env, 'biến môi trường'];
        $file = self::env_file_value('GEMINI_API_KEY');
        if ($file !== '') return [$file, 'file .env'];
        return ['', ''];
    }

    /**
     * Path of the project's .env file: the folder that contains wordpress/
     * (outside the web root when WordPress is served from wordpress/).
     * Override with define('LUXURY_CHATBOX_ENV_FILE', '/path/to/.env').
     */
    public static function env_file_path() {
        if (defined('LUXURY_CHATBOX_ENV_FILE') && is_string(LUXURY_CHATBOX_ENV_FILE)) return LUXURY_CHATBOX_ENV_FILE;
        return dirname(untrailingslashit(ABSPATH)) . DIRECTORY_SEPARATOR . '.env';
    }

    /**
     * Reads one variable from the .env file (KEY=value lines, # comments,
     * optional quotes and "export "). Nothing is added to the process
     * environment, so other code never sees the file's contents.
     */
    public static function env_file_value($name) {
        static $values = null;
        if ($values === null) {
            $values = [];
            $path = self::env_file_path();
            if (is_readable($path) && filesize($path) < 65536) {
                foreach ((array) file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim((string) $line);
                    if ($line === '' || $line[0] === '#') continue;
                    if (strpos($line, 'export ') === 0) $line = trim(substr($line, 7));
                    if (!preg_match('/\A([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)\z/', $line, $m)) continue;
                    $value = $m[2];
                    if (preg_match('/\A"(.*)"\z/', $value, $q) || preg_match("/\\A'(.*)'\\z/", $value, $q)) {
                        $value = $q[1];
                    } else {
                        $value = trim(preg_replace('/\s+#.*\z/', '', $value)); // inline comment
                    }
                    $values[$m[1]] = $value;
                }
            }
        }
        return isset($values[$name]) && is_string($values[$name]) ? $values[$name] : '';
    }
}

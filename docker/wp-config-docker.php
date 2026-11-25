<?php
/**
 * wp-config.php cho Docker / Railway – Luxury Watches.
 *
 * Mọi cấu hình đọc từ biến môi trường, không chứa bí mật. File này chỉ dùng trong
 * image Docker; khi chạy bằng XAMPP, WordPress vẫn dùng wordpress/wp-config.php
 * riêng của từng máy (bị Git bỏ qua).
 *
 * Danh sách biến: xem docs/DOCKER_DEPLOY.md.
 */

require_once '/var/www/lw-env.php';

/* -------------------------------------------------------------------------
 * Database
 * ---------------------------------------------------------------------- */

$lw_db = lw_db_config();
define('DB_NAME', $lw_db['name']);
define('DB_USER', $lw_db['user']);
define('DB_PASSWORD', $lw_db['password']);
define('DB_HOST', $lw_db['host']);
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

$table_prefix = preg_replace('/[^A-Za-z0-9_]/', '', lw_env('WORDPRESS_TABLE_PREFIX', 'wp_')) ?: 'wp_';

/* -------------------------------------------------------------------------
 * Khóa bảo mật
 * Nên đặt 8 biến WORDPRESS_AUTH_KEY… trong môi trường. Nếu thiếu, khóa được dẫn
 * xuất ổn định từ thông tin database (không đổi giữa các lần khởi động, nên người
 * dùng không bị đăng xuất), nhưng đổi mật khẩu database sẽ làm đăng xuất mọi người.
 * ---------------------------------------------------------------------- */

foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'] as $lw_key) {
    $lw_value = lw_env('WORDPRESS_' . $lw_key);
    if ($lw_value === '') {
        $lw_value = hash_hmac('sha256', $lw_key, $lw_db['password'] . '|' . $lw_db['user'] . '|' . $lw_db['name'] . '|' . $table_prefix);
    }
    define($lw_key, $lw_value);
}
unset($lw_key, $lw_value);

/* -------------------------------------------------------------------------
 * HTTPS sau reverse proxy + địa chỉ site
 * ---------------------------------------------------------------------- */

if (lw_is_forwarded_https()) {
    $_SERVER['HTTPS']       = 'on';
    $_SERVER['SERVER_PORT'] = 443;
}

$lw_home = rtrim(lw_env('WP_HOME'), '/');
if ($lw_home === '' && isset($_SERVER['HTTP_HOST']) && preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', (string) $_SERVER['HTTP_HOST'])) {
    // Chỉ dùng khi chưa đặt WP_HOME (vd. lần deploy đầu). Production nên đặt WP_HOME cố định.
    $lw_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $lw_home   = $lw_scheme . '://' . $_SERVER['HTTP_HOST'];
}
if ($lw_home !== '') {
    define('WP_HOME', $lw_home);
    define('WP_SITEURL', rtrim(lw_env('WP_SITEURL', $lw_home), '/'));
}

// Ép HTTPS cho trang quản trị khi site chạy https. Chỉ an toàn khi proxy gửi
// X-Forwarded-Proto; đặt WORDPRESS_FORCE_SSL_ADMIN=false nếu gặp vòng lặp chuyển hướng.
define('FORCE_SSL_ADMIN', lw_env_bool('WORDPRESS_FORCE_SSL_ADMIN', strpos($lw_home, 'https://') === 0));

/* -------------------------------------------------------------------------
 * Môi trường, lỗi và log
 * ---------------------------------------------------------------------- */

$lw_env_type = lw_env('WP_ENVIRONMENT_TYPE', 'production');
if (!in_array($lw_env_type, ['local', 'development', 'staging', 'production'], true)) {
    $lw_env_type = 'production';
}
define('WP_ENVIRONMENT_TYPE', $lw_env_type);

$lw_debug = lw_env_bool('WORDPRESS_DEBUG', false);
define('WP_DEBUG', $lw_debug);
// Không bao giờ hiển thị lỗi ra trình duyệt ở production.
define('WP_DEBUG_DISPLAY', $lw_debug && $lw_env_type !== 'production' && lw_env_bool('WORDPRESS_DEBUG_DISPLAY', false));
// Lỗi PHP ghi vào stderr của container (xem bằng docker compose logs), không tạo debug.log công khai.
define('WP_DEBUG_LOG', false);
define('SCRIPT_DEBUG', false);
if (!WP_DEBUG_DISPLAY) {
    @ini_set('display_errors', '0');
}

/* -------------------------------------------------------------------------
 * Hệ thống file: mã nguồn trong image là chỉ đọc, chỉ uploads được ghi.
 * Cập nhật plugin/theme/core bằng cách build lại image, không qua trang quản trị.
 * ---------------------------------------------------------------------- */

define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', lw_env_bool('WORDPRESS_DISALLOW_FILE_MODS', true));
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_AUTO_UPDATE_CORE', false);
define('WP_MEMORY_LIMIT', lw_env('WP_MEMORY_LIMIT', '256M'));
define('WP_MAX_MEMORY_LIMIT', lw_env('WP_MAX_MEMORY_LIMIT', '256M'));

/* -------------------------------------------------------------------------
 * Cấu hình plugin tùy chỉnh (chỉ đọc từ môi trường)
 * ---------------------------------------------------------------------- */

// Chatbox Gemini. Plugin cũng tự đọc GEMINI_API_KEY nếu biến này trống.
$lw_gemini = lw_env_first(['LUXURY_CHATBOX_GEMINI_KEY', 'GEMINI_API_KEY']);
if ($lw_gemini !== '') {
    define('LUXURY_CHATBOX_GEMINI_KEY', $lw_gemini);
}

// Danh sách origin được gọi API luxury/v1 (CORS), phân tách bằng dấu phẩy.
$lw_origins = array_values(array_filter(array_map('trim', explode(',', lw_env('LW_API_ALLOWED_ORIGINS')))));
if ($lw_origins) {
    define('LW_API_ALLOWED_ORIGINS', $lw_origins);
}

// Đăng nhập Google: theme đọc trực tiếp GOOGLE_CLIENT_ID từ môi trường.

unset($lw_db, $lw_home, $lw_scheme, $lw_env_type, $lw_debug, $lw_gemini, $lw_origins);

/* That's all, stop editing! Happy publishing. */

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once ABSPATH . 'wp-settings.php';

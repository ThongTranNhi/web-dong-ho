<?php
/**
 * Đọc cấu hình từ biến môi trường cho Docker / Railway.
 * Dùng chung cho wp-config.php và healthz.php. Nằm ngoài thư mục web (/var/www).
 *
 * Mỗi biến NAME cũng có thể đọc từ file qua NAME_FILE (Docker secrets).
 */

if (!function_exists('lw_env')) {
    function lw_env(string $name, string $default = ''): string
    {
        $file = getenv($name . '_FILE');
        if (is_string($file) && $file !== '' && is_readable($file)) {
            $value = trim((string) file_get_contents($file));
            if ($value !== '') {
                return $value;
            }
        }
        $value = getenv($name);
        return (is_string($value) && $value !== '') ? $value : $default;
    }

    /** Lấy giá trị khác rỗng đầu tiên trong danh sách biến. */
    function lw_env_first(array $names, string $default = ''): string
    {
        foreach ($names as $name) {
            $value = lw_env($name);
            if ($value !== '') {
                return $value;
            }
        }
        return $default;
    }

    function lw_env_bool(string $name, bool $default = false): bool
    {
        $value = strtolower(lw_env($name));
        if ($value === '') {
            return $default;
        }
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Cấu hình database. Ưu tiên biến WORDPRESS_DB_*, sau đó biến Railway MySQL
     * (MYSQLHOST, MYSQLPORT, MYSQLDATABASE, MYSQLUSER, MYSQLPASSWORD) và MYSQL_URL.
     */
    function lw_db_config(): array
    {
        $url = parse_url(lw_env_first(['WORDPRESS_DB_URL', 'MYSQL_URL'])) ?: [];

        $host = lw_env('WORDPRESS_DB_HOST');
        if ($host === '') {
            $railway_host = lw_env('MYSQLHOST', (string) ($url['host'] ?? ''));
            $railway_port = lw_env('MYSQLPORT', isset($url['port']) ? (string) $url['port'] : '');
            $host = $railway_host !== '' ? $railway_host . ($railway_port !== '' ? ':' . $railway_port : '') : 'db';
        }

        $config = [
            'host'     => $host,
            'name'     => lw_env_first(['WORDPRESS_DB_NAME', 'MYSQLDATABASE', 'MYSQL_DATABASE'], ltrim((string) ($url['path'] ?? ''), '/') ?: 'wordpress'),
            'user'     => lw_env_first(['WORDPRESS_DB_USER', 'MYSQLUSER', 'MYSQL_USER'], isset($url['user']) ? urldecode($url['user']) : 'wordpress'),
            'password' => lw_env_first(['WORDPRESS_DB_PASSWORD', 'MYSQLPASSWORD', 'MYSQL_PASSWORD'], isset($url['pass']) ? urldecode($url['pass']) : ''),
        ];

        // Tách host:port cho mysqli (healthcheck). WordPress tự hiểu dạng host:port.
        $config['host_only'] = $host;
        $config['port']      = 3306;
        if (preg_match('/^(.+):(\d+)$/', $host, $m)) {
            $config['host_only'] = $m[1];
            $config['port']      = (int) $m[2];
        }
        return $config;
    }

    /** Kết nối đến từ reverse proxy HTTPS (Railway, Nginx, Cloudflare…). */
    function lw_is_forwarded_https(): bool
    {
        $proto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
        $ssl   = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
        return $proto === 'https' || $ssl === 'on';
    }
}

<?php
/**
 * Healthcheck cho Docker / Railway: PHP chạy được và kết nối được database.
 * Không phụ thuộc trạng thái cài đặt WordPress, không lộ thông tin cấu hình.
 */
require '/var/www/lw-env.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$db = lw_db_config();
mysqli_report(MYSQLI_REPORT_OFF);
$link = mysqli_init();
$ok   = false;
if ($link) {
    mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    $ok = @mysqli_real_connect($link, $db['host_only'], $db['user'], $db['password'], $db['name'], $db['port']);
    if ($ok) {
        mysqli_close($link);
    }
}

http_response_code($ok ? 200 : 503);
echo json_encode(['status' => $ok ? 'ok' : 'database_unavailable']);

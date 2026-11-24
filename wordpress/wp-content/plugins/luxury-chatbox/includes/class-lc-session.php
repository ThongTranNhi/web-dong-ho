<?php
if (!defined('ABSPATH')) exit;

/**
 * Identifies the chat owner. Members are identified by their WordPress
 * account; guests by a random token kept in an HttpOnly cookie. Only an
 * HMAC of the token is stored, so a database leak does not reveal tokens.
 */
final class LC_Session {
    const COOKIE = 'lw_chat_session';

    public static function hash_token($token) {
        return hash_hmac('sha256', $token, wp_salt('auth'));
    }

    private static function cookie_token() {
        $raw = isset($_COOKIE[self::COOKIE]) && is_string($_COOKIE[self::COOKIE]) ? $_COOKIE[self::COOKIE] : '';
        return preg_match('/\A[a-f0-9]{64}\z/', $raw) ? $raw : '';
    }

    /**
     * Current owner. With $create, a guest without a valid cookie receives a
     * new token (sent as Set-Cookie on this response).
     */
    public static function owner($create = false) {
        $user_id = get_current_user_id();
        $token = self::cookie_token();
        if ($user_id > 0) return ['user_id' => $user_id, 'guest_hash' => ''];
        if ($token === '' && $create) {
            $token = bin2hex(random_bytes(32));
            self::send_cookie($token);
            $_COOKIE[self::COOKIE] = $token;
        }
        return ['user_id' => 0, 'guest_hash' => $token === '' ? '' : self::hash_token($token)];
    }

    public static function has_identity(array $owner) {
        return $owner['user_id'] > 0 || $owner['guest_hash'] !== '';
    }

    private static function send_cookie($token) {
        if (headers_sent()) return;
        $days = (int) LC_Settings::get('retention_days');
        setcookie(self::COOKIE, $token, [
            'expires' => time() + max(1, $days) * DAY_IN_SECONDS,
            'path' => COOKIEPATH ?: '/',
            'domain' => COOKIE_DOMAIN ?: '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

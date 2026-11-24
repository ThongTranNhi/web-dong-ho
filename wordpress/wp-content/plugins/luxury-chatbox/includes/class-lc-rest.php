<?php
if (!defined('ABSPATH')) exit;

/**
 * REST API: /wp-json/luxury-chat/v1/...
 *
 * Every route resolves the owner from the current session (WordPress login
 * or guest cookie). No route accepts a conversation ID from the client, so
 * knowing an ID never grants access to it.
 */
final class LC_Rest {
    const NS = 'luxury-chat/v1';

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'routes']);
    }

    public static function routes() {
        register_rest_route(self::NS, '/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [__CLASS__, 'status'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NS, '/session', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [__CLASS__, 'session'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route(self::NS, '/messages', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [__CLASS__, 'history'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [__CLASS__, 'send'],
                'permission_callback' => '__return_true',
                'args' => [
                    'message' => ['required' => true, 'type' => 'string'],
                    'request_id' => ['required' => true, 'type' => 'string'],
                ],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [__CLASS__, 'clear'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    private static function respond(LC_Result $result) {
        $body = $result->ok ? ['ok' => true, 'data' => $result->data, 'error' => null] : ['ok' => false, 'data' => null, 'error' => $result->error];
        $response = new WP_REST_Response($body, $result->status);
        $response->header('Cache-Control', 'no-store, private');
        if (!$result->ok && $result->error['retry_after'] > 0) $response->header('Retry-After', (string) $result->error['retry_after']);
        return $response;
    }

    private static function enabled() {
        return (bool) LC_Settings::get('enabled');
    }

    public static function status() {
        return self::respond(LC_Result::success([
            'enabled' => self::enabled(),
            'available' => self::enabled() && LC_Chat_Service::provider()->is_configured(),
            'logged_in' => is_user_logged_in(),
            'limits' => [
                'max_message_chars' => (int) LC_Settings::get('max_message_chars'),
                'per_minute' => (int) LC_Settings::get('per_minute'),
                'per_day' => (int) LC_Settings::get('per_day'),
            ],
        ]));
    }

    public static function session() {
        if (!self::enabled()) return self::respond(LC_Chat_Service::disabled_result());
        return self::respond(LC_Chat_Service::open_conversation(LC_Session::owner(true)));
    }

    public static function history() {
        $owner = LC_Session::owner(false);
        if (!LC_Session::has_identity($owner)) return self::respond(LC_Result::success(['conversation' => null, 'messages' => []]));
        return self::respond(LC_Chat_Service::history($owner));
    }

    public static function send(WP_REST_Request $request) {
        if (!self::enabled()) return self::respond(LC_Chat_Service::disabled_result());
        return self::respond(LC_Chat_Service::send(LC_Session::owner(true), $request->get_param('message'), $request->get_param('request_id')));
    }

    public static function clear() {
        $owner = LC_Session::owner(false);
        if (!LC_Session::has_identity($owner)) return self::respond(LC_Result::success(['deleted_conversations' => 0]));
        return self::respond(LC_Chat_Service::clear($owner));
    }
}

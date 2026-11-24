<?php
if (!defined('ABSPATH')) exit;

/** A provider failure translated into a code the frontend can act on. */
final class LC_AI_Error extends Exception {
    public $error_code;
    public $retryable;
    public $retry_after;
    public $detail;
    public $http_status;

    /**
     * @param string $error_code  Public code, e.g. ai_timeout.
     * @param string $message     Customer-facing Vietnamese message.
     * @param bool   $retryable   Whether resending the same request may succeed.
     * @param int    $retry_after Suggested wait in seconds (0 = none).
     * @param string $detail      Admin-only diagnostic (never sent to clients, never contains secrets).
     * @param int    $http_status Provider HTTP status, 0 when no HTTP response was received.
     */
    public function __construct($error_code, $message, $retryable = false, $retry_after = 0, $detail = '', $http_status = 0) {
        parent::__construct($message);
        $this->error_code = $error_code;
        $this->retryable = (bool) $retryable;
        $this->retry_after = (int) $retry_after;
        $this->detail = (string) $detail;
        $this->http_status = (int) $http_status;
    }
}

<?php
if (!defined('ABSPATH')) exit;

/**
 * Gemini generateContent API over the WordPress HTTP API.
 * REST reference https://aistudio.google.com/api-reference/generate-content
 */
final class LC_Gemini_Provider implements LC_AI_Provider {
    const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';
    const MAX_TOKENS = 4096; // replies are deliberately short; also a cost cap

    private $api_key;
    private $model;
    private $timeout;

    public function __construct($api_key, $model, $timeout) {
        $this->api_key = (string) $api_key;
        $this->model = (string) $model;
        $this->timeout = (int) $timeout;
    }

    public function is_configured() {
        return $this->api_key !== '' && preg_match('/\Agemini-[a-z0-9.\-]{1,56}\z/', $this->model) === 1;
    }

    public function generate($system, array $messages, array $schema) {
        if (!$this->is_configured()) {
            throw new LC_AI_Error('ai_not_configured', 'Trợ lý chưa được cấu hình. Vui lòng liên hệ cửa hàng.', false, 0, 'Missing API key or model.');
        }
        $contents = [];
        foreach ($messages as $message) {
            $contents[] = [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => (string) $message['content']]],
            ];
        }
        $body = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => self::MAX_TOKENS,
                'responseFormat' => ['text' => ['mimeType' => 'APPLICATION_JSON', 'schema' => $schema]],
            ],
        ];
        // Use the lowest supported reasoning level for short, grounded shop answers.
        // The retired 3.5 Flash alias is served by 3.6 Flash, which supports MINIMAL.
        $thinking_levels = [
            'gemini-3.5-flash' => 'MINIMAL',
            'gemini-3.6-flash' => 'MINIMAL',
            'gemini-3-flash-preview' => 'MINIMAL',
            'gemini-3.1-flash-lite' => 'MINIMAL',
            'gemini-3.5-flash-lite' => 'MINIMAL',
            'gemini-3.8-flash' => 'LOW',
        ];
        if (isset($thinking_levels[$this->model])) {
            $body['generationConfig']['thinkingConfig'] = ['thinkingLevel' => $thinking_levels[$this->model]];
        }
        $response = wp_remote_post(self::ENDPOINT . rawurlencode($this->model) . ':generateContent', [
            'timeout' => $this->timeout,
            'redirection' => 0,
            'headers' => [
                'content-type' => 'application/json',
                'x-goog-api-key' => $this->api_key,
            ],
            'body' => wp_json_encode($body),
            'data_format' => 'body',
        ]);

        if (is_wp_error($response)) {
            $message = $response->get_error_message();
            if (stripos($message, 'timed out') !== false || stripos($message, 'timeout') !== false) {
                throw new LC_AI_Error('ai_timeout', 'Trợ lý phản hồi quá lâu. Vui lòng gửi lại sau ít phút.', true, 5, 'HTTP timeout');
            }
            throw new LC_AI_Error('ai_unavailable', 'Không kết nối được tới trợ lý AI. Vui lòng thử lại sau.', true, 10, 'Transport error: ' . $response->get_error_code());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        if ($status !== 200) throw self::http_error($status, is_array($decoded) ? $decoded : [], $response);
        if (!is_array($decoded)) {
            throw new LC_AI_Error('ai_bad_response', 'Trợ lý trả về dữ liệu không hợp lệ. Vui lòng thử lại.', true, 2, 'Body is not JSON');
        }

        $candidate = $decoded['candidates'][0] ?? [];
        if (!is_array($candidate)) $candidate = [];
        $stop = $candidate['finishReason'] ?? '';
        if (!empty($decoded['promptFeedback']['blockReason']) || in_array($stop, ['SAFETY', 'RECITATION', 'BLOCKLIST', 'PROHIBITED_CONTENT', 'SPII', 'IMAGE_SAFETY'], true)) {
            throw new LC_AI_Error('ai_refused', 'Trợ lý không thể trả lời yêu cầu này. Bạn có thể hỏi về sản phẩm, chính sách hoặc đấu giá.', false, 0, 'Gemini safety block');
        }
        if ($stop === 'MAX_TOKENS') {
            throw new LC_AI_Error('ai_bad_response', 'Câu trả lời bị gián đoạn. Vui lòng hỏi ngắn gọn hơn.', true, 0, 'finishReason=MAX_TOKENS');
        }
        if ($stop !== 'STOP') {
            throw new LC_AI_Error('ai_bad_response', 'Trợ lý trả về dữ liệu không hợp lệ. Vui lòng thử lại.', true, 2, 'Missing completed candidate');
        }
        $text = '';
        foreach (($candidate['content']['parts'] ?? []) as $block) {
            if (is_array($block) && empty($block['thought']) && is_string($block['text'] ?? null)) $text .= $block['text'];
        }
        $data = json_decode($text, true);
        if (!is_array($data)) {
            throw new LC_AI_Error('ai_bad_response', 'Trợ lý trả về dữ liệu không hợp lệ. Vui lòng thử lại.', true, 2, 'Reply text is not JSON');
        }
        $usage = [];
        foreach (['promptTokenCount' => 'input_tokens', 'candidatesTokenCount' => 'output_tokens', 'cachedContentTokenCount' => 'cache_read_input_tokens', 'thoughtsTokenCount' => 'thinking_tokens'] as $field => $name) {
            if (isset($decoded['usageMetadata'][$field])) $usage[$name] = (int) $decoded['usageMetadata'][$field];
        }
        return [
            'data' => $data,
            'model' => (string) ($decoded['modelVersion'] ?? $this->model),
            'usage' => $usage,
        ];
    }

    private static function http_error($status, array $body, $response) {
        $type = (string) ($body['error']['status'] ?? '');
        $retry_after = (int) wp_remote_retrieve_header($response, 'retry-after');
        $detail = 'HTTP ' . $status . ($type ? ' ' . $type : '');
        if ($status === 429) {
            return new LC_AI_Error('ai_busy', 'Trợ lý đang bận. Vui lòng thử lại sau ít giây.', true, $retry_after ?: 20, $detail, $status);
        }
        if ($status >= 500) {
            return new LC_AI_Error('ai_unavailable', 'Trợ lý AI đang tạm gián đoạn. Vui lòng thử lại sau.', true, $retry_after ?: 15, $detail, $status);
        }
        if ($status === 401 || $status === 403 || $status === 404) {
            return new LC_AI_Error('ai_not_configured', 'Trợ lý chưa được cấu hình đúng. Vui lòng liên hệ cửa hàng.', false, 0, $detail, $status);
        }
        foreach (($body['error']['details'] ?? []) as $error) {
            if (is_array($error) && in_array($error['reason'] ?? '', ['API_KEY_INVALID', 'API_KEY_EXPIRED', 'API_KEY_SERVICE_BLOCKED'], true)) {
                return new LC_AI_Error('ai_not_configured', 'Trợ lý chưa được cấu hình đúng. Vui lòng liên hệ cửa hàng.', false, 0, $detail, $status);
            }
        }
        return new LC_AI_Error('ai_bad_request', 'Không thể xử lý tin nhắn này. Vui lòng diễn đạt lại.', false, 0, $detail, $status);
    }
}

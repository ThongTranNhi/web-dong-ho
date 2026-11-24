<?php
if (!defined('ABSPATH')) exit;

/**
 * Provider-neutral contract used by the chat service. Swap providers with
 * the `luxury_chatbox_provider` filter.
 */
interface LC_AI_Provider {
    /** Whether credentials and configuration are present. */
    public function is_configured();

    /**
     * @param string $system   Fixed system rules.
     * @param array  $messages [['role' => 'user'|'assistant', 'content' => string], ...]
     * @param array  $schema   JSON Schema the reply must follow.
     * @return array ['data' => array (decoded reply), 'model' => string, 'usage' => array]
     * @throws LC_AI_Error
     */
    public function generate($system, array $messages, array $schema);
}

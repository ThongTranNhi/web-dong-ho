<?php
/**
 * Plugin Name: Luxury Watches Chatbox
 * Description: Backend chatbot hỗ trợ khách hàng: tư vấn đồng hồ, chính sách và đấu giá dựa trên dữ liệu thật của website.
 * Version: 1.1.0
 * Author: Luxury Watches
 * Text Domain: luxury-chatbox
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) exit;

define('LC_VERSION', '1.1.0');
define('LC_DB_VERSION', '2'); // 2: messages.ai_calls (quota counts every AI call)
define('LC_FILE', __FILE__);
define('LC_DIR', __DIR__);

require_once LC_DIR . '/includes/class-lc-settings.php';
require_once LC_DIR . '/includes/class-lc-installer.php';
require_once LC_DIR . '/includes/class-lc-repository.php';
require_once LC_DIR . '/includes/class-lc-session.php';
require_once LC_DIR . '/includes/class-lc-rate-limiter.php';
require_once LC_DIR . '/includes/class-lc-knowledge.php';
require_once LC_DIR . '/includes/interface-lc-ai-provider.php';
require_once LC_DIR . '/includes/class-lc-ai-error.php';
require_once LC_DIR . '/includes/class-lc-gemini-provider.php';
require_once LC_DIR . '/includes/class-lc-chat-service.php';
require_once LC_DIR . '/includes/class-lc-rest.php';
require_once LC_DIR . '/includes/class-lc-cleanup.php';
require_once LC_DIR . '/includes/class-lc-admin.php';
require_once LC_DIR . '/includes/class-lc-frontend.php';

register_activation_hook(__FILE__, ['LC_Installer', 'activate']);
register_deactivation_hook(__FILE__, ['LC_Installer', 'deactivate']);

add_action('plugins_loaded', ['LC_Installer', 'maybe_upgrade']);
LC_Rest::init();
LC_Cleanup::init();
LC_Admin::init();
LC_Frontend::init();

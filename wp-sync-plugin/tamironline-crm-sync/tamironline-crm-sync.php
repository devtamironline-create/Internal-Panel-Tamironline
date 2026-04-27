<?php
/**
 * Plugin Name:       Tamironline CRM Sync
 * Plugin URI:        https://tamironline.com
 * Description:       ارسال خودکار داده‌های CRM وردپرسی (مشتری، تکنسین، تنظیمات، سفارش، مالی) به پنل لاراول Tamironline.
 * Version:           0.2.0
 * Author:            Tamironline
 * Text Domain:       tcs
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 5.8
 *
 * @package TamironlineCrmSync
 */

if (! defined('ABSPATH')) {
    exit;
}

define('TCS_VERSION', '0.2.0');
define('TCS_FILE', __FILE__);
define('TCS_DIR', plugin_dir_path(__FILE__));
define('TCS_URL', plugin_dir_url(__FILE__));

require_once TCS_DIR . 'includes/class-api-client.php';
require_once TCS_DIR . 'includes/class-settings-page.php';
require_once TCS_DIR . 'includes/class-sync-queue.php';
require_once TCS_DIR . 'includes/class-customer-sync.php';

add_action('plugins_loaded', function () {
    new TCS_Settings_Page();
    new TCS_Customer_Sync();
    TCS_Sync_Queue::register_cron();
});

add_action('tcs_retry_queue', ['TCS_Sync_Queue', 'process']);

register_activation_hook(__FILE__, ['TCS_Sync_Queue', 'on_activate']);
register_deactivation_hook(__FILE__, ['TCS_Sync_Queue', 'on_deactivate']);

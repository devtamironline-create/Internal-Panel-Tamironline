<?php
/**
 * Plugin Name:       Tamironline CRM Sync
 * Plugin URI:        https://tamironline.com
 * Description:       ارسال خودکار داده‌های CRM وردپرسی (مشتری، تکنسین، تنظیمات، سفارش، مالی) به پنل لاراول Tamironline.
 * Version:           0.11.0
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

define('TCS_VERSION', '0.8.0');
define('TCS_FILE', __FILE__);
define('TCS_DIR', plugin_dir_path(__FILE__));
define('TCS_URL', plugin_dir_url(__FILE__));

require_once TCS_DIR . 'includes/class-api-client.php';
require_once TCS_DIR . 'includes/class-settings-page.php';
require_once TCS_DIR . 'includes/class-sync-queue.php';
require_once TCS_DIR . 'includes/class-customer-sync.php';
require_once TCS_DIR . 'includes/class-technician-sync.php';
require_once TCS_DIR . 'includes/class-settings-sync.php';
require_once TCS_DIR . 'includes/class-taxonomy-sync.php';
require_once TCS_DIR . 'includes/class-order-sync.php';
require_once TCS_DIR . 'includes/class-periodic-order-sync.php';
require_once TCS_DIR . 'includes/class-financial-sync.php';
require_once TCS_DIR . 'includes/trait-inbound-hmac.php';
require_once TCS_DIR . 'includes/class-inbound-order.php';
require_once TCS_DIR . 'includes/class-inbound-technician.php';
require_once TCS_DIR . 'includes/class-inbound-customer.php';
require_once TCS_DIR . 'includes/class-inbound-financial.php';

add_action('plugins_loaded', function () {
    new TCS_Settings_Page();
    new TCS_Customer_Sync();
    new TCS_Technician_Sync();
    new TCS_Settings_Sync();
    new TCS_Taxonomy_Sync();
    new TCS_Order_Sync();
    new TCS_Periodic_Order_Sync();
    new TCS_Financial_Sync();
    new TCS_Inbound_Order();
    new TCS_Inbound_Technician();
    new TCS_Inbound_Customer();
    new TCS_Inbound_Financial();
    TCS_Sync_Queue::register_cron();
});

add_action('tcs_retry_queue', ['TCS_Sync_Queue', 'process']);

register_activation_hook(__FILE__, function () {
    TCS_Sync_Queue::on_activate();
    TCS_Periodic_Order_Sync::on_activate();
});
register_deactivation_hook(__FILE__, function () {
    TCS_Sync_Queue::on_deactivate();
    TCS_Periodic_Order_Sync::on_deactivate();
});

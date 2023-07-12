<?php
/**
 * Plugin Name: Modena Payment Gateway
 * Plugin URI: https://developer.modena.ee/en/developer-integration-woocommerce
 * Description: Modena can help you get with everything you need to start your online store checkout in Estonia. Let us know about you +372 6604144 or info@modena.ee
 * Author: Modena Estonia OÜ
 * URI: https://modena.ee/
 * Version: 2.9.3
 *
 * @package Modena
 */
if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}
if (!defined('MODENA_PLUGIN_URL')) {
  define('MODENA_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('MODENA_PLUGIN_PATH')) {
  define('MODENA_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
if (!function_exists('is_plugin_active')) {
  require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}
function modena_init() {

  static $modena_plugin;
  if (!isset($modena_plugin)) {
    if (!class_exists('Modena_Init_Handler')) {
      require_once(MODENA_PLUGIN_PATH . 'includes/class-modena-init-handler.php');
      $modena_plugin = new Modena_Init_Handler();
    }
  }
  $modena_plugin->run();
}

add_action('plugins_loaded', 'modena_init');
function check_woocommerce_activation() {

  if (!is_plugin_active('woocommerce/woocommerce.php')) {
    add_action('admin_notices', 'my_plugin_admin_notice');
  }
}

add_action('admin_init', 'check_woocommerce_activation');
function my_plugin_admin_notice() {

  ?>
    <div class="notice notice-error is-dismissible">
        <p><?php
          _e('The Modena Payment Gateway plugin requires WooCommerce to be activated. Please install and activate WooCommerce.', 'modena-payment-gateway'); ?></p>
    </div>
  <?php
}
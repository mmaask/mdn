<?php
/**
 * Plugin Name: Modena Payments
 * Plugin URI: https://developer.modena.ee/en/developer-integration-woocommerce
 * Description: WooCommerce checkout solution from Modena.
 * Author: Modena Estonia OÜ
 * Author URI: https://modena.ee/
 * Version: 3.0.0
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

add_action('plugins_loaded', 'modena_init');

function modena_init(): void
{
    static $modena_plugin;

    if (!isset($modena_plugin)) {
        require_once(MODENA_PLUGIN_PATH . 'includes/class-modena-init-handler.php');
        $modena_plugin = new Modena_Init_Handler();

        require_once(MODENA_PLUGIN_PATH . 'includes/class-modena-shipping-itella-terminals.php');
        modena_shipping_init();
    }
    $modena_plugin->run();
}
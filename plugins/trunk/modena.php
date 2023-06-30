<?php
/**
 * Plugin Name: Modena Payment Gateway
 * Plugin URI: https://developer.modena.ee/en/developer-integration-woocommerce
 * Description: Modena can help you get with everything you need to start your online store checkout in Estonia. Let us know about you +372 6604144 or info@modena.ee
 * Author: Modena Estonia OÜ
 * Author URI: https://modena.ee/
 * Version: 2.8.1
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

$php = '7.4';

if (version_compare(PHP_VERSION, $php, '<')) {
    deactivate_plugins(plugin_basename(__FILE__));
    exit(sprintf('This plugin requires PHP %s or higher.', $php));
}

$wp = '5.5';

if (version_compare(get_bloginfo('version'), $wp, '<')) {
    deactivate_plugins(plugin_basename(__FILE__));
    exit(sprintf('This plugin requires WordPress %s or higher.', $wp));
}

$wc = '6.6';


if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    deactivate_plugins(plugin_basename(__FILE__));
    exit('This plugin requires Woocommerce.');
}

register_activation_hook( __FILE__, 'modena_check_before_activation' );

function modena_check_before_activation() {
    if (class_exists('Modena_Init_Handler') && function_exists('modena_init')) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
    }
}

function modena_init()
{
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
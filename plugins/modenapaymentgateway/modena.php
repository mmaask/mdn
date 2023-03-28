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

require_once(MODENA_PLUGIN_PATH . 'includes/class-modena-shipping-self-service.php');
require_once(MODENA_PLUGIN_PATH . 'includes/class-modena-shipping-ai.php');

add_action('plugins_loaded', 'modena_init');

function modena_init(): void
{
    static $modena_plugin;

    if (!isset($modena_plugin)) {
        require_once(MODENA_PLUGIN_PATH . 'includes/class-modena-init-handler.php');
        $modena_plugin = new Modena_Init_Handler();
    }
    $modena_plugin->run();

    require_once(MODENA_PLUGIN_PATH . 'includes/class-modena-shipping-self-service.php');
    run_shipping();
}
/**
 * Initializes the Modena Shipping Self Service method if it doesn't already exist and
 * WooCommerce Shipping Method class exists.
 *
 * This function checks for the existence of the Modena_Shipping_Self_Service class and
 * the WC_Shipping_Method class. If the Modena_Shipping_Self_Service class does not exist
 * and the WC_Shipping_Method class exists, it hooks the 'woocommerce_shipping_init'
 * action to the 'initializeModenaShippingMethod' function and the 'woocommerce_shipping_methods'
 * filter to the 'add_modena_shipping_flat' function.
 *
 * Checks for the existence of the required classes and initializes the Modena Shipping Self Service method.
 * Handles errors if the required classes do not exist.
 *
 * Define a custom error message.
 * Check if the 'Modena_Shipping_Self_Service' class exists.
 * Check if the 'WC_Shipping_Method' class exists.
 * Log the error message.
 * Optionally, display an admin notice if you'd like to notify the site administrator.
 *
 * @return void
 */
function run_shipping(): void {
    if (!class_exists('Modena_Shipping_Self_Service') && class_exists('WC_Shipping_Method')) {
        add_action('woocommerce_shipping_init', 'initializeModenaShippingMethod');
        add_action('woocommerce_shipping_init', 'init_WC_estonia');


        add_filter('woocommerce_shipping_methods', 'add_modena_shipping_flat');
    } else {
        $errorMessage = "Error: ";
        if (!class_exists('Modena_Shipping_Self_Service')) {
            $errorMessage .= "The 'Modena_Shipping_Self_Service' class does not exist. ";
        }
        if (!class_exists('WC_Shipping_Method')) {
            $errorMessage .= "The 'WC_Shipping_Method' class does not exist. ";
        }
        error_log($errorMessage);
        add_action('admin_notices', 'modena_shipping_error_notice');
    }
}
/**
 * Adds the Modena Shipping Self Service method to the list of available WooCommerce shipping methods.
 *
 * This function adds the 'itella_self_service_by_modena' method to the array of available shipping
 * methods in WooCommerce. The method is an instance of the 'Modena_Shipping_Self_Service' class.
 *
 * @param array $methods Array of existing WooCommerce shipping methods.
* @return array Updated array of WooCommerce shipping methods, including the Modena Shipping Self Service method.
 */
function add_modena_shipping_flat(array $methods): array {
    $methods['itella_self_service_by_modena'] = 'Modena_Shipping_Self_Service';
    $methods['estonia_shipping'] = 'WC_Estonia_Shipping_Method';
    return $methods;
}
/**
 * Displays an admin notice with an error message.
 *
 * This function outputs an error message to the WordPress admin dashboard
 * when the required classes for the Modena Shipping plugin are not found.
 * It informs the user to ensure the necessary dependencies are installed
 * and active.
 *
 * @return void
 */
function modena_shipping_error_notice(): void {
    echo '<div class="notice notice-error"><p><strong>Modena Shipping Error:</strong> The required classes were not found. Please ensure the necessary dependencies are installed and active.</p></div>';
}



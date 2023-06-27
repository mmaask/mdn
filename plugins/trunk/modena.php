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

add_action('plugins_loaded', 'modena_init');

if (!function_exists('modena_init')) {
    function modena_init()
    {
        static $modena_plugin;

        if (!isset($modena_plugin)) {
            require_once(MODENA_PLUGIN_PATH . 'includes/class-modena-init-handler.php');
            $modena_plugin = new Modena_Init_Handler();
        }

        $modena_plugin->run();
    }
} else {
    add_action('woocommerce_init', function() {
        if (class_exists('WC_Logger')) {
            $this->logger = new WC_Logger(array(new Modena_Log_Handler()));
            $this->logger->error('Function modena_init has already been declared. Please delete deprecated versions of Modena plugin from this site and retry
             reinstalling from the Wordpress Plugin store. modena_init is initialized already: ' . function_exists('modena_init'));
        }
    });

    add_action('admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Function modena_init has already been declared. Please delete deprecated versions of Modena plugin from this site and retry reinstalling from the Wordpress Plugin store.', 'modena'); ?></p>
        </div>
        <?php
    });
}
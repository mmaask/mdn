<?php

class Modena_Load_Checkout_Assets {
    private static $instance = null;

    private function __construct() {
        // Debugging: Logging the constructor call
        error_log('Modena_Load_Checkout_Assets constructor called.');

        $this->renderAssets();
        $this->enqueue_modena_shipping_assets();
    }

    private function renderAssets() {
        // Debugging: Logging before assets are rendered
        error_log('Rendering assets.');

        wp_enqueue_style('modena_frontend_style', MODENA_PLUGIN_URL . '/assets/css/modena-checkout.css');
        wp_enqueue_script('modena_frontend_script', MODENA_PLUGIN_URL . '/assets/js/modena-checkout.js');
        wp_enqueue_script('modena_setting_script', MODENA_PLUGIN_URL . '/assets/js/modena-settings.js', array('jquery'), '6.2', true);
    }

    public static function getInstance(): Modena_Load_Checkout_Assets {
        // Debugging: Logging when getInstance is called
        error_log('Modena_Load_Checkout_Assets getInstance method called.');

        if (self::$instance === null) {
            self::$instance = new Modena_Load_Checkout_Assets();
        }

        return self::$instance;
    }

    public function enqueue_modena_shipping_assets() {
        // Debugging: Logging before enqueueing modena shipping assets
        error_log('Enqueueing modena shipping assets.');

        if (!wp_script_is('jquery')) {
            wp_enqueue_script('jquery');
        }

        wp_enqueue_style('modena_shipping_style', MODENA_PLUGIN_URL . '/assets/css/modena-shipping.css');
        wp_enqueue_script('modena_shipping_script', MODENA_PLUGIN_URL . '/assets/js/modena-shipping.js', array('jquery'), '6.2', true);


        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), true);

        wp_enqueue_style('select2');
        wp_enqueue_script('select2');
    }
}
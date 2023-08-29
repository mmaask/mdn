<?php


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class ModenaSettingsLoader {

    public function __construct() {
        if ($this->is_woocommerce_active()) {
            add_filter('woocommerce_get_settings_pages', array($this, 'createModenaSettings'));
        }
    }

    public function is_woocommerce_active(): bool {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) ||
            (is_multisite() && in_array('woocommerce/woocommerce.php', array_keys(get_site_option('active_sitewide_plugins'))));
    }

    /**
     * @param $settings
     * @return mixed
     */
    public function createModenaSettings($settings) {
        require_once MODENA_PLUGIN_PATH . '/includes/class-modena-settings.php';

        $settings[] = ModenaSettings::create();

        return $settings;
    }
}
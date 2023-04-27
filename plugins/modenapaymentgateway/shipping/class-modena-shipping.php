
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Modena_Shipping {
    public function init(): void {
        if ($this->is_woocommerce_active()) {
            $this->run_shipping();

        }
    }

    public function is_woocommerce_active(): bool {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) ||
        (is_multisite() && in_array('woocommerce/woocommerce.php', array_keys(get_site_option('active_sitewide_plugins'))));
    }

    /**
     *
     * Checks for the existence of the required classes and initializes
     * Handles errors if the required classes do not exist.
     *
     * @return void
     */

    public function run_shipping(): void {
        if (!class_exists('Modena_Shipping_Itella_Terminals') && class_exists('WC_Shipping_Method')) {
            $this->loadAssets();
            add_filter('woocommerce_shipping_methods', array($this, 'load_modena_shipping_methods'));
            add_action('woocommerce_shipping_init', array($this, 'init_WC_estonia'));
        } else {
            $errorMessage = "Error: ";
            if (!class_exists('Modena_Shipping_Itella_Terminals')) {
                $errorMessage .= "The 'WC_Estonia_Shipping_Method' class does not exist. ";
            }
            if (!class_exists('WC_Shipping_Method')) {
                $errorMessage .= "The 'WC_Shipping_Method' class does not exist. ";
            }
            error_log($errorMessage);
            add_action('admin_notices', 'modena_shipping_error_notice');
        }
    }
    /**
     * @param array $methods Array of existing WooCommerce shipping methods.
     * @return array Updated array of WooCommerce shipping methods, including the Modena Shipping Self Service method.
     */

    public function load_modena_shipping_methods(array $methods): array {
        $methods['modena-shipping-itella-terminals'] = 'Modena_Shipping_Itella_Terminals';
        return $methods;
    }

    /**
     * Displays an admin notice with an error message.

     * @return void
     */
    public function modena_shipping_error_notice(): void {
        echo '<div class="notice notice-error"><p><strong>Modena Shipping Error:</strong> The required classes were not found. Please ensure the necessary dependencies are installed and active.</p></div>';
    }

    public function init_WC_estonia(): void {
        $this->clear_debug_log();
        require_once(MODENA_PLUGIN_PATH . 'shipping/class-modena-shipping-method.php');
        require_once(MODENA_PLUGIN_PATH . 'shipping/class-modena-shipping-itella-terminals.php');

        $shipping_ai_object = new Modena_Shipping_Itella_Terminals();

    }

    public function clear_debug_log(): void
    {
        $debug_log_file = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($debug_log_file) && is_writable($debug_log_file)) {
            $file_handle = fopen($debug_log_file, 'w');
            fclose($file_handle);
        }
    }

    public function loadAssets(): void {


        wp_enqueue_style('modena_shipping_style', MODENA_PLUGIN_URL . '/shipping/assets/modena-shipping.css');
        wp_enqueue_script('modena_shipping_script', MODENA_PLUGIN_URL . 'shipping/assets/modena-shipping.js', array('jquery'), '6.2', true);

        $translations = array(
            'please_choose_parcel_terminal' => __($this->getParcelTerminalDefaultTextTranslation(), 'mdn-translations')

        );

        wp_localize_script('modena_shipping_script', 'mdnTranslations', $translations);
    }

    public function getParcelTerminalDefaultTextTranslation(): string
    {

        switch (get_locale()) {
            case 'en_GB' && 'en_US':
                return 'Select parcel terminal';
            case 'ru_RU':
                return 'Список почтовых терминалов';
            default:
                return 'Vali pakipunkt';
        }
    }
}
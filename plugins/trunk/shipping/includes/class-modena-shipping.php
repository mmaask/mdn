<?php
if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

class Modena_Shipping {
  public function init() {
    if ($this->is_woocommerce_active()) {
      $this->run_shipping();

    }
  }

  public function is_woocommerce_active(): bool {
    return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) || (is_multisite() && in_array('woocommerce/woocommerce.php', array_keys(get_site_option('active_sitewide_plugins'))));
  }

  /**
   *
   * Checks for the existence of the required classes and initializes
   * Handles errors if the required classes do not exist.
   *
   * @return void
   */

  public function run_shipping() {
    add_filter('woocommerce_shipping_methods', array($this, 'load_modena_shipping_methods'));
    add_action('woocommerce_shipping_init', array($this, 'init_WC_estonia'));
  }

  /**
   * @param array $methods Array of existing WooCommerce shipping methods.
   * @return array Updated array of WooCommerce shipping methods, including the Modena Shipping Self Service method.
   */

  public function load_modena_shipping_methods(array $methods): array {
    $methods['modena-shipping-parcels-omniva'] = 'Modena_Shipping_Parcels_Omniva';
    $methods['modena-shipping-parcels-dpd']    = 'Modena_Shipping_Parcels_DPD';
    $methods['modena-shipping-parcels-itella'] = 'Modena_Shipping_Parcels_itella';
    $methods['modena-shipping-courier-itella'] = 'Modena_Shipping_Courier_itella';
    $methods['modena-shipping-courier-dpd']    = 'Modena_Shipping_Courier_DPD';
    $methods['modena-shipping-courier-omniva'] = 'Modena_Shipping_Courier_Omniva';

    return $methods;
  }

  /**
   * Displays an admin notice with an error message.
   * @return void
   */
  public function modena_shipping_error_notice() {
    echo '<div class="notice notice-error"><p><strong>Modena Shipping Error:</strong> The required classes were not found. Please ensure the necessary dependencies are installed and active.</p></div>';
  }

  public function init_WC_estonia() {

    require_once(MODENA_PLUGIN_PATH . 'shipping/includes/class-modena-shipping-method.php');
    require_once(MODENA_PLUGIN_PATH . 'shipping/includes/class-modena-shipping-parcels.php');
    require_once(MODENA_PLUGIN_PATH . 'shipping/includes/class-modena-shipping-courier.php');

    require_once(MODENA_PLUGIN_PATH . 'shipping/includes/class-modena-shipping-courier-itella.php');
    require_once(MODENA_PLUGIN_PATH . 'shipping/includes/class-modena-shipping-courier-dpd.php');
    require_once(MODENA_PLUGIN_PATH . 'shipping/includes/class-modena-shipping-courier-omniva.php');

    require_once(MODENA_PLUGIN_PATH . 'shipping/includes/class-modena-shipping-parcels-itella.php');
    require_once(MODENA_PLUGIN_PATH . 'shipping/includes/class-modena-shipping-parcels-dpd.php');
    require_once(MODENA_PLUGIN_PATH . 'shipping/includes/class-modena-shipping-parcels-omniva.php');

  }
}
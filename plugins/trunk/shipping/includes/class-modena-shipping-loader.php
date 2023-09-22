<?php
if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

class ModenaShippingLoader {
  public function init() {
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

  public function run_shipping() {
    add_filter('woocommerce_shipping_methods', array($this, 'load_modena_shipping_methods'));
    add_action('woocommerce_shipping_init', array($this, 'modenaShippingLoader'));

  }

  /**
   * @param array $methods Array of existing WooCommerce shipping methods.
   * @return array Updated array of WooCommerce shipping methods, including the Modena Shipping Self Service method.
   */

  public function load_modena_shipping_methods(array $methods): array {
    $methods['modena-shipping-parcels-omniva']        = 'ModenaShippingOmnivaParcels';
    $methods['modena-shipping-parcels-dpd']           = 'ModenaShippingDpdParcels';
    $methods['modena-shipping-parcels-itella']        = 'ModenaShippingItellaParcels';
    $methods['modena-shipping-parcels-omniva-office'] = 'ModenaShippingOmnivaOffice';
    $methods['modena-shipping-courier-itella']        = 'ModenaShippingItellaCourier';
    $methods['modena-shipping-courier-dpd']           = 'ModenaShippingDpdCourier';
    $methods['modena-shipping-courier-omniva']        = 'ModenaShippingOmnivaCourier';

    return $methods;
  }

  public function modenaShippingLoader() {

    require_once(MODENA_PLUGIN_PATH . 'shipping/includes/class-modena-shipping-base.php');
    require_once(MODENA_PLUGIN_PATH . 'shipping/includes/class-modena-shipping-itella-courier.php');
    require_once(MODENA_PLUGIN_PATH . 'shipping/includes/class-modena-shipping-dpd-courier.php');
    require_once(MODENA_PLUGIN_PATH . 'shipping/includes/class-modena-shipping-omniva-courier.php');
    require_once(MODENA_PLUGIN_PATH . 'shipping/includes/class-modena-shipping-itella-parcels.php');
    require_once(MODENA_PLUGIN_PATH . 'shipping/includes/class-modena-shipping-parcels-dpd.php');
    require_once(MODENA_PLUGIN_PATH . 'shipping/includes/class-modena-shipping-omniva-parcels.php');
    require_once(MODENA_PLUGIN_PATH . 'shipping/includes/class-modena-shipping-omniva-office.php');

  }
}
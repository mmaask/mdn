<?php

use Modena\Payment\Modena;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

abstract class Modena_Shipping_Method extends WC_Shipping_Method {
  const PLUGIN_VERSION = '2.10.0';
  public    $cost;
  protected $environment;
  protected $is_test_mode;
  protected $modena_shipping;
  protected $client_id;
  protected $client_secret;
  protected $max_weight_for_modena_shipping_method;
  protected $modena_shipping_request;
  protected $shipping_logger;
  protected $modena_shipping_type;
  protected $modena_shipping_service;

  public function __construct($instance_id = 0) {

    $this->instance_id = absint($instance_id);
    $this->supports    = array('shipping-zones', 'instance-settings', 'instance-settings-modal',);

    $this->init_form_fields();
    $this->init_settings();

    $this->environment   = $this->get_option('environment');
    $this->client_id     = $this->get_option('client_id');
    $this->client_secret = $this->get_option('client_secret');
    $this->is_test_mode  = $this->environment === 'sandbox';
    $this->title         = $this->get_option('title');
    $this->cost          = $this->get_option('cost');

    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));

    parent::__construct($instance_id);
  }

  public function get_order_total_weight($order) {
    $total_weight = 0;

    foreach ($order->get_items() as $item) {
      if ($item instanceof WC_Order_Item_Product) {
        $quantity       = $item->get_quantity();
        $product        = $item->get_product();
        $product_weight = $product->get_weight();
        $total_weight   += $product_weight * $quantity;
      }
    }
    error_log("Total weight: " . $total_weight);

    return $total_weight;
  }

  public function is_cart_shippable_by_modena_weight($order) {

    $wc_cart_weight = $this->get_order_total_weight($order);

    if ($wc_cart_weight <= $this->get_maximum_modena_shipping_method_weight()) {
      return true;
    }
  }

  public function deactivate_modena_shipping_if_cart_larger_than_spec($rates, $order) {
    if (!$this->get_modena_shipping_setting_if_no_measurements()) {
      if ($this->is_cart_shippable_by_modena_weight($order)) {
        unset($rates[$this->id]);
      }
    }

    return $rates;
  }

  public function do_products_have_modena_dimensions($package) {

    foreach ($package['contents'] as $values) {
      $_product                          = $values['data'];
      $wooCommerceOrderProductDimensions = $_product->get_dimensions(false);

      if (empty($wooCommerceOrderProductDimensions['length']) || empty($wooCommerceOrderProductDimensions['width']) || empty($wooCommerceOrderProductDimensions['height'])) {
        return false;
      }
    }
  }

  public function deactivate_modena_shipping_no_measurements($rates, $package) {
    if ($this->get_modena_shipping_setting_if_no_measurements() || $this->get_package_measurement_check_modena_shipping_setting()) {
      if (!$this->do_products_have_modena_dimensions($package)) {
        unset($rates[$this->id]);
      }
    }

    return $rates;
  }

  public function get_quantity_of_products_per_modena_cart() {
    global $woocommerce;

    return $woocommerce->cart->get_cart_contents_count();
  }

  public function calculate_shipping($package = array()) {
    if ($this->get_modena_shipping_method_free_shipping_setting() || $this->get_quantity_based_modena_shipping_setting()) {
      if ($this->get_modena_shipping_method_free_shipping_treshold() <= $this->get_cost() || $this->get_quantity_based_shipping_treshold() < $this->get_quantity_of_products_per_modena_cart()) {
        $rate = array('id' => $this->id, 'label' => $this->title, 'cost' => 0,);
        $this->add_rate($rate);
      }
    }
    else {
      $rate = array('id' => $this->id, 'label' => $this->title, 'cost' => $this->cost,);
      $this->add_rate($rate);
    }
  }

  public function compile_data_for_modena_shipping_request($order_id) {
    $order = wc_get_order($order_id);

    if (empty($order->get_meta('_selected_parcel_terminal_id_mdn'))) {
      error_log('Veateade - Tellimusel puudub salvestatud pakipunkti ID, et alustada POST päringut' . $order->get_meta('_selected_parcel_terminal_id_mdn'));
    }

    $result         = $this->get_order_total_weight($order);
    $weight         = $result['total_weight'];
    $packageContent = $result['packageContent'];

    return array(
       'orderReference'           => $order->get_order_number(),
       'packageContent'           => $packageContent,
       'weight'                   => $weight,
       'recipient_name'           => $order->get_billing_first_name() . " class-modena-shipping-method.php" . $order->get_billing_last_name(),
       'recipient_phone'          => $order->get_billing_phone(),
       'recipientEmail'           => $order->get_billing_email(),
       '$wcOrderParcelTerminalID' => $order->get_meta('_selected_parcel_terminal_id_mdn'),);
  }

  public abstract function process_modena_shipping_request($order_id);

  public function process_modena_shipping_status($rates) {
    try {
      $modena_shipping_status = $this->modena_shipping->get_modena_shipping_api_status();
      error_log("modena shipping method status: " . $modena_shipping_status);
      if (!$modena_shipping_status) {
        unset($rates[$this->id]);
      }
    } catch (Exception $exception) {
      $this->shipping_logger->error('Exception occurred when authing to modena: ' . $exception->getMessage());
      $this->shipping_logger->error($exception->getTraceAsString());
    }

    return $rates;
  }


  public function parse_shipping_methods($shippingMethodID, $order_id) {
    $order            = wc_get_order($order_id);
    $shipping_methods = $order->get_shipping_methods();

    if (empty($shipping_methods)) {
      //error_log("Metadata not saved since order no shipping method: ");
      return False;
    }

    $first_shipping_method = reset($shipping_methods);
    $orderShippingMethodID = $first_shipping_method->get_method_id();

    //error_log("Comparing methods... " . $shippingMethodID . " with:  " . $orderShippingMethodID);

    if (empty($orderShippingMethodID)) {
      //error_log("Metadata not saved since order no shipping method with id: " . $orderShippingMethodID);
      return False;
    }

    if ($orderShippingMethodID == $shippingMethodID) {
      //error_log("win, because methods are same named. saned.");
      return True;
    }
    else {
      //error_log("Metadata not saved: " . $shippingMethodID);
      return False;
    }
  }

  public function is_order_pending($order) {
    if ($order->get_status() == 'pending') {
      return True;
    }
    else {
      return False;
    }
  }

  public function sanitize_costs($shippingMethodCost): float {
    $sanitizedshippingMethodCost = floatval($shippingMethodCost);
    if ($sanitizedshippingMethodCost < 0) {
      $sanitizedshippingMethodCost = $shippingMethodCost;
    }

    return $sanitizedshippingMethodCost;
  }

  public function get_modena_shipping_method_id() {
    return $this->id;
  }

  public function get_cost() {
    return $this->get_option('cost');
  }

  public function get_modena_shipping_method_free_shipping_setting() {
    return $this->get_option('modena_free_shipping_treshold');
  }

  public function get_modena_shipping_method_free_shipping_treshold() {
    return $this->get_option('modena_free_shipping_treshold_sum');
  }

  public function get_quantity_based_modena_shipping_setting() {
    return $this->get_option('modena_quantity_free_shipping_treshold');
  }

  public function get_quantity_based_shipping_treshold() {
    return $this->get_option('modena_quantity_free_shipping_treshold_sum');
  }

  public function get_package_measurement_check_modena_shipping_setting() {
    return $this->get_option('modena_package_measurement_checks');
  }

  public function get_maximum_modena_shipping_method_weight() {
    return $this->get_option('modena_package_maximum_weight');
  }

  public function get_modena_shipping_setting_if_no_measurements() {
    return $this->get_option('modena_no_measurement_package');
  }


  public function init_form_fields() {
    $this->form_fields = [
       'title'                         => ['title' => __('Transpordivahendi nimetus makselehel', 'modena'), 'type' => 'text', 'default' => $this->title, 'desc_tip' => true,],
       'cost'                          => [
          'title'             => __('Transpordivahendi maksumus'),
          'type'              => 'price',
          'placeholder'       => '',
          'description'       => 'Shipping Method Cost',
          'default'           => $this->cost,
          'desc_tip'          => true,
          'sanitize_callback' => array($this, 'sanitize_costs'),],
       'modena_free_shipping_treshold' => [
          'title'    => __('Tasuta saatmise funktsionaalsus', 'modena'),
          'type'     => 'checkbox',
          'desc_tip' => 'Enable or disable the shipping method in checkout. Customers will not be able to see the shipping method if disabled.',
          'default'  => 'no',],

       'modena_free_shipping_treshold_sum'          => [
          'title'             => __('Piirmäär tasuta saatmisele'),
          'type'              => 'price',
          'placeholder'       => '',
          'description'       => 'Select amount that this shipping method is free.',
          'default'           => 50,
          'desc_tip'          => true,
          'sanitize_callback' => array($this, 'sanitize_costs'),],
       'modena_quantity_free_shipping_treshold'     => [
          'title'    => __('Ostukorvi koguse põhine tasuta saatmine', 'modena'),
          'type'     => 'checkbox',
          'desc_tip' => 'Enable or disable the shipping method in checkout. Customers will not be able to see the shipping method if disabled.',
          'default'  => 'no',],
       'modena_quantity_free_shipping_treshold_sum' => [
          'title'             => __('Piirmäär ostukorvi toodete kogusele'),
          'type'              => 'price',
          'placeholder'       => '',
          'description'       => 'Select amount of quantity of product that this shipping method is free.',
          'default'           => 10,
          'desc_tip'          => true,
          'sanitize_callback' => array($this, 'sanitize_costs'),],
       'modena_package_measurement_checks'          => [
          'title'    => __('Toote mõõtmete kontroll transpordivahendile', 'modena'),
          'type'     => 'checkbox',
          'desc_tip' => 'Enable or disable the shipping method in checkout. Customers will not be able to see the shipping method if disabled.',
          'default'  => 'no',],
       'modena_package_maximum_weight'              => [
          'title'             => __('Ostukorvi maksimum kaal'),
          'type'              => 'float',
          'placeholder'       => '',
          'description'       => 'Select amount of quantity of product that this shipping method is free.',
          'default'           => $this->max_weight_for_modena_shipping_method,
          'desc_tip'          => true,
          'sanitize_callback' => array($this, 'sanitize_costs'),],
       'modena_no_measurement_package'              => [
          'title'    => __('Peida transpordivahend mõõtmete puudumisel', 'modena'),
          'type'     => 'checkbox',
          'desc_tip' => 'Enable or disable the shipping method in checkout. Customers will not be able to see the shipping method if disabled.',
          'default'  => 'no',],
       'client_id'                                  => ['title' => __('Modena: API ID', 'modena'), 'type' => 'text', 'desc_tip' => true,],
       'client_secret'                              => ['title' => __('Modena: API Secret', 'modena'), 'type' => 'text', 'desc_tip' => true,],
       'partner_api_id'                             => ['title' => __($this->modena_shipping_service . ': API Key', 'modena'), 'type' => 'text', 'desc_tip' => true,],
       'partner_api_secret'                         => ['title' => __($this->modena_shipping_service . ': API Secret', 'modena'), 'type' => 'text', 'desc_tip' => true,],];
  }
}
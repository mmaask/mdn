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

  public function __construct($instance_id = 0) {
    parent::__construct($instance_id);

    $this->instance_id = absint($instance_id);
    $this->supports = array('shipping-zones', 'instance-settings', 'instance-settings-modal',);

    require_once MODENA_PLUGIN_PATH . 'autoload.php';
    require ABSPATH . WPINC . '/version.php';

    $this->init();
    $this->init_form_fields();
    $this->initialize_modena_shipping_hooks();

    Modena_Load_Checkout_Assets::getInstance();
  }

  public function init() {

    $this->environment = $this->get_option('environment');
    $this->client_id = $this->get_option('client_id');
    $this->client_secret = $this->get_option('client_secret');
    $this->is_test_mode = $this->environment === 'sandbox';
    $this->cost = floatval($this->get_option('cost'));
    $this->modena_shipping = new Modena_Shipping($this->client_id, $this->client_secret, self::PLUGIN_VERSION, $this->is_test_mode);
    $this->shipping_logger = new WC_Logger(array(new Modena_Log_Handler()));

  }

  public function initialize_modena_shipping_hooks() {
    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    //add_action('woocommerce_checkout_update_order_review', array($this, 'process_modena_shipping_status'));
    //add_action('woocommerce_review_order_before_payment', array($this, 'render_modena_select_box_in_checkout'));
    //add_action(
    //   'woocommerce_checkout_update_order_review', array(
    //   $this,
    //   'deactivate_modena_shipping_if_cart_larger_than_spec'
    //));
    //add_action('woocommerce_checkout_update_order_review', array($this, 'deactivate_modena_shipping_no_measurements'));
    //add_action('woocommerce_get_order_item_totals', array($this, 'add_shipping_to_checkout_details'));
    //add_action('woocommerce_thankyou', array($this, 'process_modena_shipping_request'));
    //add_filter('woocommerce_order_actions', array($this, 'add_print_label_custom_order_action'));
    //add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'render_shipping_destination_in_admin_order_view'));
  }


  public function get_order_total_weight($order) {
    $total_weight = 0;

    foreach ($order->get_items() as $item) {
      if ($item instanceof WC_Order_Item_Product) {
        $quantity = $item->get_quantity();
        $product = $item->get_product();
        $product_weight = $product->get_weight();
        $total_weight += $product_weight * $quantity;
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
      $_product = $values['data'];
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

  protected abstract function get_modena_shipping_status();

  protected abstract function get_modena_parcel_terminal_list();

  //todo
  //only show modena namespace shipping methods

  public function render_modena_select_box_in_checkout($modena_shipping_method) {

    if (empty($this->parcelMachineList)) {
      $this->parcelMachineList = $this->get_modena_parcel_terminal_list($modena_shipping_method);
    }

    ?>
      <div class="modena-shipping-select-wrapper-<?php
      echo $this->id ?>" style="margin-bottom: 15px">
          <label for="mdn-shipping-select-box-itella"><?php
            echo $this->get_select_box_placeholder_for_modena_shipping() ?></label>
          <select name="userShippingSelection-<?php
          echo $this->id ?>" id="mdn-shipping-select-box-itella" data-method-id="<?php
          echo $this->id; ?>" style="width: 100%; height: 400px;">
              <option disabled selected="selected"></option>
            <?php
            $cities = array();
            foreach ($this->parcelMachineList as $terminal) {
              $cities[$terminal->{'city'}][] = $terminal;
            }

            foreach ($cities as $city => $terminals) {
              echo "<optgroup label='$city'>";
              foreach ($terminals as $terminal) {
                $terminalID = $terminal->{'place_id'};
                echo "<option value='$terminalID' >" . $terminal->{'name'} . "</option>";
              }
              echo "</optgroup>";
            }
            ?>
          </select>
      </div>
    <?php
  }

  public function compile_data_for_modena_shipping_request($order_id) {
    $order = wc_get_order($order_id);

    if (empty($order->get_meta('_selected_parcel_terminal_id_mdn'))) {
      error_log('Veateade - Tellimusel puudub salvestatud pakipunkti ID, et alustada POST päringut' . $order->get_meta('_selected_parcel_terminal_id_mdn'));
    }

    $result = $this->get_order_total_weight($order);
    $weight = $result['total_weight'];
    $packageContent = $result['packageContent'];

    return array('orderReference' => $order->get_order_number(), 'packageContent' => $packageContent, 'weight' => $weight, 'recipient_name' => $order->get_billing_first_name() . " " . $order->get_billing_last_name(), 'recipient_phone' => $order->get_billing_phone(), 'recipientEmail' => $order->get_billing_email(), '$wcOrderParcelTerminalID' => $order->get_meta('_selected_parcel_terminal_id_mdn'),);
  }

  public function process_modena_shipping_status($rates) {
    try {
      $modena_shipping_status = $this->modena_shipping->get_modena_shipping_method_status();
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

  public function process_modena_shipping_request($order_id) {
    try {
      $modena_shipping_response = $this->modena_shipping->get_modena_shipping_barcode_id($this->compile_data_for_modena_shipping_request($order_id));
      $this->add_label_url_to_order_meta_data($modena_shipping_response, $order_id);
    } catch (Exception $exception) {
      $this->shipping_logger->error('Exception occurred when processing data: ' . $exception->getMessage());
      $this->shipping_logger->error($exception->getTraceAsString());
    }
  }

  public function add_shipping_to_checkout_details($totals, $order_id) {
    $order = wc_get_order($order_id);
    if (!$this->has_order_got_package_point_meta_data($order))
      return $totals;
    if ($this->parse_shipping_methods($this->id, $order_id)) {
      $parcel_terminal = $this->get_selected_shipping_destination($order);

      foreach ($totals as $key => $total) {
        if ($key === 'shipping') {
          $totals['shipping']['value'] = $totals['shipping']['value'] . " (" . $parcel_terminal . ")";
        }
      }

    }

    return $totals;
  }

  public function parse_shipping_methods($shippingMethodID, $order_id) {
    $order = wc_get_order($order_id);
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

  public function has_order_got_package_point_meta_data($order) {
    if (empty($this->get_selected_shipping_destination_barcode_id())) {
      error_log("oh no, order does not yet have a terminal.");

      return False;
    }
    else {
      return True;
    }
  }


  public function add_label_url_to_order_meta_data($label_url, $order_id) {
    $order = wc_get_order($order_id);
    $order->add_meta_data('_selected_modena_shipping_label_url', $label_url, true);
    $order->save();
  }

  public function add_print_label_custom_order_action($actions) {
    error_log("Create label print custom action: " . $this->id);
    $actions['custom_order_action'] = __($this->get_placeholderPrintLabelInAdmin(), 'woocommerce');

    return $actions;
  }

  public function process_print_label_custom_order_action($order) {
    $order_note = $this->get_placeholderPrintLabelInAdmin() . " " . $this->get_selected_shipping_destination($order) . ".";
    $order->add_order_note($order_note);
    error_log("this is the url to the label: " . $this->get_selected_shipping_label_url($order));
    $this->modena_shipping->save_modena_shipping_label_PDF_in_User($this->get_selected_shipping_destination_barcode_id($order));
  }

  public function render_shipping_destination_in_admin_order_view($order_id) {
    //error_log("Trying to run into admin orders");

    $order = wc_get_order($order_id);

    if ($this->is_order_pending($order))
      return;

    if ($this->parse_shipping_methods($this->id, $order_id)) {

      ?>
        <tr class="selected-terminal">
            <th>
                <h3>
                  <?php
                  echo $this->title ?>
                </h3>
            </th>
            <td>
                <p>
                  <?php
                  echo $this->get_selected_shipping_destination(); ?>

                </p>
                <button id="buttonForClicking" onClick="startUpdatingOrderParcel()" class="button grant-access"><?php
                  _e($this->get_placeholderPrintLabelInAdmin()) ?></button>

                <script>
                    document.getElementById("buttonForClicking").addEventListener("click", startUpdatingOrderParcel);

                    function startUpdatingOrderParcel() {


                      <?php
                      //todo implement download correctly
                      //$this->updateParcelTerminalForOrder($order, $order_id);
                      ?>
                    }
                </script>
            </td>
        </tr>
      <?php
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

  public function get_placeholderPrintLabelInAdmin() {
    return __('Download ' . $this->get_title() . ' Parcel Label');
  }

  public function get_printLabelPlaceholderInBulkActions() {
    return __('Download ' . $this->get_title() . ' Parcel Labels');
  }

  public function get_adjustParcelTerminalInAdminPlaceholder() {
    return __('Update: ');
  }

  public function get_select_box_placeholder_for_modena_shipping() {
    return __('Select parcel terminal ');
  }

  public function get_createOrderParcelMetaDataPlaceholderText() {
    return __('Parcel terminal is selected for the order: ');
  }

  public function get_updateParcelTerminalNewTerminalNote() {
    return __('New parcel terminal has been selected for the order: ');
  }

  public function get_selected_shipping_destination_barcode_id($order) {
    return $this->$order->get_meta('_selected_modena_shipping_destination_barcode_id');
  }

  public function get_selected_shipping_destination($order) {
    return $this->$order->get_meta('_selected_modena_shipping_destination_id');
  }

  public function get_selected_shipping_label_url($order) {
    return $this->$order->get_meta('_selected_modena_shipping_label_url');
  }

  public function init_form_fields() {
    parent::init_form_fields();
    $this->form_fields = ['credentials_title_line'                                                                                                                  => ['type' => 'title', 'description' => 'Technical Support: +372 6604144 & info@modena.ee'], 'environment' => array('title' => __('Environment', 'modena'), 'type' => 'select', 'options' => array('sandbox' => __('Sandbox mode', 'modena'), 'live' => __('Live mode', 'modena'),), 'description' => __('<div id="environment_alert_desc"></div>', 'modena'), 'default' => 'sandbox', 'desc_tip' => __(
       'Choose Sandbox mode to test payment using test API keys. Switch to live mode to accept payments with Modena using live API keys.', 'modena'),), 'client_id' => ['title' => __('Client ID', 'modena'), 'type' => 'text', 'desc_tip' => true,], 'client_secret' => ['title' => __('Client Secret', 'modena'), 'type' => 'text', 'desc_tip' => true,], 'title' => ['title' => __('Shipping Method Title', 'modena'), 'type' => 'text', 'default' => $this->get_title(), 'desc_tip' => true,], 'cost' => ['title' => __('Shipping Method Cost'), 'type' => 'float', 'placeholder' => '', 'description' => 'Shipping Method Cost', 'default' => $this->get_cost(), 'desc_tip' => true, 'sanitize_callback' => array($this, 'sanitize_costs'),], 'modena_free_shipping_treshold' => ['title' => __('Enable or disable free shipping treshold.', 'modena'), 'type' => 'checkbox', 'desc_tip' => 'Enable or disable the shipping method in checkout. Customers will not be able to see the shipping method if disabled.', 'default' => 'no',], 'modena_free_shipping_treshold_sum' => ['title' => __('Free shipping treshold'), 'type' => 'float', 'placeholder' => '', 'description' => 'Select amount that this shipping method is free.', 'default' => 50, 'desc_tip' => true, 'sanitize_callback' => array($this, 'sanitize_costs'),], 'modena_quantity_free_shipping_treshold' => ['title' => __('Enable or disable quantity free shipping.', 'modena'), 'type' => 'checkbox', 'desc_tip' => 'Enable or disable the shipping method in checkout. Customers will not be able to see the shipping method if disabled.', 'default' => 'no',], 'modena_quantity_free_shipping_treshold_sum' => ['title' => __('Quantity based free shipping treshold'), 'type' => 'int', 'placeholder' => '', 'description' => 'Select amount of quantity of product that this shipping method is free.', 'default' => 10, 'desc_tip' => true, 'sanitize_callback' => array($this, 'sanitize_costs'),], 'modena_package_measurement_checks' => ['title' => __('Package measurement checks are enabled.', 'modena'), 'type' => 'checkbox', 'desc_tip' => 'Enable or disable the shipping method in checkout. Customers will not be able to see the shipping method if disabled.', 'default' => 'no',], 'modena_package_maximum_weight' => ['title' => __('Maximum weight of the shipping package'), 'type' => 'float', 'placeholder' => '', 'description' => 'Select amount of quantity of product that this shipping method is free.', 'default' => $this->max_weight_for_modena_shipping_method, 'desc_tip' => true, 'sanitize_callback' => array($this, 'sanitize_costs'),], 'modena_no_measurement_package' => ['title' => __('Hide shipping if product has no measurements.', 'modena'), 'type' => 'checkbox', 'desc_tip' => 'Enable or disable the shipping method in checkout. Customers will not be able to see the shipping method if disabled.', 'default' => 'no',],];
  }
}
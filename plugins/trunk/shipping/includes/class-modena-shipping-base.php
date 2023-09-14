<?php

use Modena\Payment\Modena;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

abstract class ModenaShippingBase extends WC_Shipping_Method
{
    const PLUGIN_VERSION = '2.10.0';
    public $cost;
    protected $environment;
    protected $is_test_mode;
    protected $client_id;
    protected $client_secret;
    protected $max_weight_for_modena_shipping_method;
    protected $parcelMachineList;
    protected $modena_shipping_type;
    protected $modena_shipping_service;
    protected $modena_shipping;
    protected $shipping_logger;

    public function __construct($instance_id = 0)
    {
        require_once MODENA_PLUGIN_PATH . 'autoload.php';

        $this->instance_id = absint($instance_id);
        $this->supports = array('shipping-zones', 'instance-settings', 'instance-settings-modal',);

        $this->init_form_fields();
        $this->init_settings();

        $this->method_description =
            __('Name in checkout: <b>' .
                $this->title .
                '</b><br />Price: <b>' .
                $this->cost .
                '</b> <br /> Free shipping sum: <b>' .
                $this->get_option('modena_free_shipping_treshold_sum') .
                '</b>. Is free shipping enabled? <b>' .
                $this->get_option('modena_free_shipping_treshold') .
                '</b><br /> Free shipping quantity: <b>' .
                $this->get_option('modena_quantity_free_shipping_treshold_sum') .
                '</b>. Is free shipping by quantity enabled? <b>' .
                $this->get_option('modena_quantity_free_shipping_treshold') .
                '</b><br />Product dimension check? <b>' .
                $this->get_option('modena_package_measurement_checks') .
                '</b><br />Hide if product has no dimensions? <b>' .
                $this->get_option('modena_no_measurement_package') .
                '</b>');

        $this->environment = get_option('modena_environment');
        $this->client_id = get_option('modena_' . $this->environment . '_client_id');
        $this->client_secret = get_option('modena_' . $this->environment . '_client_secret');
        $this->is_test_mode = $this->environment === 'sandbox';
        error_log("About to add actions and filters.");

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        add_filter('woocommerce_package_rates', array($this, 'filterShippingRatesBySettings'));
        add_action('woocommerce_package_rates', array($this, 'canShipByWeight'), 10,2);


        add_action('woocommerce_package_rates', array($this, 'canShipByMeasurement'), 10, 2);
        error_log("Finished adding actions and filters.");

        Modena_Load_Checkout_Assets::getInstance();

        parent::__construct($instance_id);
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'title' => ['title' => __('Transpordivahendi nimetus makselehel', 'modena'), 'type' => 'text', 'default' => $this->title, 'desc_tip' => true,],
            'cost' => [
                'title' => __('Transpordivahendi maksumus'),
                'type' => 'number',
                'placeholder' => '',
                'default' => $this->cost,//'sanitize_callback' => array($this, 'sanitize_costs'),
            ],
            'modena_free_shipping_treshold' => [
                'title' => __('Tasuta saatmise funktsionaalsus', 'modena'),
                'type' => 'checkbox',
                'description' => 'Ostukorvi summa põhiselt võimaldatakse tasuta saatmine.',
                'default' => 'no',],

            'modena_free_shipping_treshold_sum' => [
                'title' => __('Piirmäär tasuta saatmisele'),
                'type' => 'number',
                'placeholder' => '',
                'default' => 50,//'sanitize_callback' => array($this, 'sanitize_costs'),
            ],
            'modena_quantity_free_shipping_treshold' => [
                'title' => __('Ostukorvi koguse põhine tasuta saatmine', 'modena'),
                'type' => 'checkbox',
                'description' => 'Ostukorvi toodete koguse põhine tasuta saatmine.',
                'default' => 'no',],
            'modena_quantity_free_shipping_treshold_sum' => [
                'title' => __('Piirmäär ostukorvi toodete kogusele'),
                'type' => 'number',
                'placeholder' => '',
                'default' => 10,
                'desc_tip' => true,//'sanitize_callback' => array($this, 'sanitize_costs'),
            ],
            'modena_package_measurement_checks' => [
                'title' => __('Toote mõõtmete kontroll transpordivahendile', 'modena'),
                'type' => 'checkbox',
                'description' => 'Toote mõõtmete kontroll ostukorvile.',
                'default' => 'no',],
            'modena_package_maximum_weight' => [
                'title' => __('Ostukorvi maksimum kaal'),
                'type' => 'number',
                'placeholder' => '',
                'default' => $this->max_weight_for_modena_shipping_method,//'sanitize_callback' => array($this, 'sanitize_costs'),
            ],
            'modena_no_measurement_package' => [
                'title' => __('Peida transpordivahend mõõtmete puudumisel', 'modena'),
                'type' => 'checkbox',
                'description' => 'Toote mõõtmete ületamisel peidetakse transpordivahend.',
                'default' => 'no',],];
    }

    public function process_admin_options()
    {
        parent::process_admin_options();

        error_log("Processing admin settings - saving manually for " . $this->id);
        // Save settings manually
        update_option($this->get_option_key(), $this->settings);

        // Debug output to see if options are updated
        $option_name = $this->get_option_key();
        $option_values = get_option($option_name);

        error_log('Option Name: ' . $option_name);
        error_log('Option Values: ' . print_r($option_values, true));
        error_log('Instance ID: ' . $this->instance_id);
    }

    public function filterShippingRatesBySettings($rates)
    {
        error_log("filterShippingRatesBySettings called for " . $this->id);

        if (get_option('modena_shipping_enabled') === 'no') {
            unset($rates[$this->id]);
            error_log("Modena shipping is disabled. Removing its rates. OPTION SETTING IS: " . get_option('modena_shipping_enabled'));
        } else {
            error_log("Modena shipping is enabled. OPTION SETTING IS: " . get_option('modena_shipping_enabled'));
        }

        error_log("Found shipping rates of mdn: " . print_r($rates[$this->id], true));

        return $rates;
    }

    public function calculate_shipping($package = array())
    {
        // Log that the function has been called
        error_log("calculate_shipping function called for " . $this->id);

        global $woocommerce;

        // Log the cart total
        $cartTotal = $woocommerce->cart->get_cart_contents_total();
        error_log("Cart total is: " . $cartTotal);

        // Log the cart item count
        $cartItemCount = $this->getCartItemCount();
        error_log("Cart item count is: " . $cartItemCount);

        // Log the shipping options being checked
        error_log("Free shipping threshold enabled? " . $this->get_option('modena_free_shipping_treshold'));
        error_log("Free shipping by quantity threshold enabled? " . $this->get_option('modena_quantity_free_shipping_treshold'));

        if ($this->get_option('modena_free_shipping_treshold') === 'yes' || $this->get_option('modena_quantity_free_shipping_treshold') === 'yes') {
            // Log the specific threshold values
            error_log("Free shipping threshold sum: " . $this->get_option('modena_free_shipping_treshold_sum'));
            error_log("Free shipping by quantity threshold sum: " . $this->get_option('modena_quantity_free_shipping_tresholdsum'));

            if ($this->get_option('modena_free_shipping_treshold_sum') <= $cartTotal || $this->get_option('modena_quantity_free_shipping_tresholdsum') < $cartItemCount) {
                $rate = array('id' => $this->id, 'label' => $this->title, 'cost' => 0,);
                $this->add_rate($rate);
                // Log that free shipping rate was added
                error_log("Free shipping rate added.");
            }
        } else {
            $rate = array('id' => $this->id, 'label' => $this->title, 'cost' => $this->cost,);
            $this->add_rate($rate);
            // Log that the standard shipping rate was added
            error_log("Standard shipping rate added with cost: " . $this->cost);
        }
    }

    public function getOrderTotalWeight($package) {
        // Initialize total weight to 0
        $totalWeight = 0;
        error_log("getOrderTotalWeight function called.");

        // Check if package is empty
        if(empty($package['contents'])) {
            error_log("Package contents are empty.");
            return $totalWeight;
        }

        // Loop through each cart item
        foreach ($package['contents'] as $item) {
            $product = $item['data'];
            $quantity = $item['quantity'];

            // Debugging: Log product and quantity details
            error_log("Processing item with ID: " . $product->get_id());
            error_log("Item quantity: " . $quantity);

            // Get weight of a single product
            $productWeight = $product->get_weight();

            // Debugging: Log product weight
            error_log("Item weight: " . $productWeight);

            // Add to total weight
            $totalWeight += ($productWeight * $quantity);
        }

        // Debugging: Log total weight
        error_log("Order total weight: " . $totalWeight);

        return $totalWeight;
    }

    public function canShipByWeight($rates, $package) {
        // Debugging: Log entry into function
        error_log("canShipByWeight called");

        // Check for invalid or empty arguments
        if (!$rates || !$package) {
            error_log("Rates or package are missing. Exiting canShipByWeight.");
            return $rates; // Return the original rates if parameters are not provided
        }

        // Get max weight from shipping method settings
        $maxWeight = $this->get_option('max_weight_for_modena_shipping_method', 35); // default value is 35
        // Debugging: Log maximum allowed weight
        error_log("Max weight from settings: " . $maxWeight);

        // Get total weight of the cart
        $totalWeight = $this->getOrderTotalWeight($package);

        // Debugging: Log total cart weight
        error_log("Total cart weight: " . $totalWeight);

        // Check if total weight exceeds max weight
        if ($totalWeight > $maxWeight) {
            // Debugging: Log removal action
            error_log("Total weight exceeds max weight. Removing this shipping method: " . $this->id);

            // Remove this shipping method
            unset($rates[$this->id]);
        } else {
            error_log("Total weight does not exceed max weight. Shipping method." . $this->id);
        }

        return $rates;  // Return modified rates
    }

    // todo

    public function canShipByMeasurement($rates, $package)
    {

        // Log that the function has been called
        error_log("canShipByMeasurement function called.");

        if (!$this->get_option('modena_package_measurement_checks')) {
            foreach ($package['contents'] as $values) {
                $_product = $values['data'];
                $wooCommerceOrderProductDimensions = $_product->get_dimensions(false);

                // Log the dimensions of each product in the order
                error_log("Product dimensions: " . json_encode($wooCommerceOrderProductDimensions));

                if (empty($wooCommerceOrderProductDimensions['length']) || empty($wooCommerceOrderProductDimensions['width']) || empty($wooCommerceOrderProductDimensions['height'])) {
                    // Log that the product did not meet the measurement checks
                    error_log("Product does not meet measurement requirements. Removing shipping method.");

                    unset($rates[$this->id]);
                    return false;
                }
            }
        }
        // Log that all products met the measurement checks
        error_log("All products meet measurement requirements.");

        return $rates;
    }


    public function getCartItemCount()
    {
        global $woocommerce;

        $count = $woocommerce->cart->get_cart_contents_count();

        // Log the current cart item count
        error_log("Current cart item count: " . $count);

        return $count;
    }


    public function getOrderStatus($order)
    {
        $status = $order->get_status();

        // Log the current order status
        error_log("Current order status: " . $status);

        if ($status == 'pending') {
            // Log that the order is pending
            error_log("Order is pending.");

            return True;
        } else {
            // Log that the order is not pending
            error_log("Order is not pending.");

            return False;
        }
    }
}

class Modena_Shipping_Service_Calls
{

    public function displayParcelSelectBox()
    {
        if ($this->modena_shipping_type != 'parcels') {
            return;
        }

        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        $chosen_shipping = $chosen_methods[0]; // Get the first chosen shipping method

        if ($this->id != $chosen_shipping) {
            return;
        }

        $this->parcelMachineList = $this->modena_shipping->get_modena_parcel_terminal_list($this->id);

        ?>
        <div class="modena-shipping-select-wrapper-<?php
        echo $this->id ?>" style="margin-bottom: 15px">
            <label for="mdn-shipping-select-box-<?php echo $this->id; ?>"><?php
                echo $this->get_select_box_placeholder_for_modena_shipping() ?></label>
            <select name="userShippingSelection-<?php
            echo $this->id ?>" id="mdn-shipping-select-box-<?php echo $this->id; ?>" data-method-id="<?php
            echo $this->id; ?>" style="width: 100%;">
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

    public function parseShippingMethods($shippingMethodId, $order_id)
    {
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

        if ($orderShippingMethodID == $shippingMethodId) {
            //error_log("win, because methods are same named. saned.");
            return True;
        } else {
            //error_log("Metadata not saved: " . $shippingMethodID);
            return False;
        }
    }

    public function has_order_got_package_point_meta_data($order)
    {
        if (empty($this->get_selected_shipping_destination_barcode_id())) {
            error_log("oh no, order does not yet have a terminal.");

            return False;
        } else {
            return True;
        }
    }

    //public function appendParcelInfoToShippingTotal($totals, $order_id)
    //{
    //    $order = wc_get_order($order_id);
    //    if (!$this->hasOrderPackagePointMetaData($order)) {
    //        return $totals;
    //    }
    //    if ($this->parseShippingMethods($this->id, $order_id)) {
    //        $parcelTerminal = $this->getSelectedShippingDestination($order);
//
    //        foreach ($totals as $key => $total) {
    //            if ($key === 'shipping') {
    //                $totals['shipping']['value'] .= " (" . $parcelTerminal . ")";
    //            }
    //        }
    //    }
//
    //    return $totals;
    //}


    /*

    public function render_shipping_destination_in_admin_order_view($order_id)
    {

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
                    <button id="buttonForClicking" onClick="startUpdatingOrderParcel()"
                            class="button grant-access"><?php
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
*/

    public function get_placeholderPrintLabelInAdmin()
    {
        return __('Download ' . $this->get_title() . ' Parcel Label');
    }

    public function get_printLabelPlaceholderInBulkActions()
    {
        return __('Download ' . $this->get_title() . ' Parcel Labels');
    }

    public function get_adjustParcelTerminalInAdminPlaceholder()
    {
        return __('Update: ');
    }

    public function get_select_box_placeholder_for_modena_shipping()
    {
        return __('Select parcel terminal ');
    }

    public function get_createOrderParcelMetaDataPlaceholderText()
    {
        return __('Parcel terminal is selected for the order: ');
    }

    public function get_updateParcelTerminalNewTerminalNote()
    {
        return __('New parcel terminal has been selected for the order: ');
    }

    public function get_selected_shipping_destination_barcode_id($order)
    {
        return $this->$order->get_meta('_selected_modena_shipping_destination_barcode_id');
    }

    public function get_selected_shipping_destination($order)
    {
        return $this->$order->get_meta('_selected_modena_shipping_destination_id');
    }

    public function get_selected_shipping_label_url($order)
    {
        return $this->$order->get_meta('_selected_modena_shipping_label_url');
    }

    public function compile_data_for_modena_shipping_request($order_id)
    {
        $order = wc_get_order($order_id);

        if (empty($order->get_meta('_selected_parcel_terminal_id_mdn'))) {
            error_log('Veateade - Tellimusel puudub salvestatud pakipunkti ID, et alustada POST päringut' . $order->get_meta('_selected_parcel_terminal_id_mdn'));
        }

        $result = $this->getOrderTotalWeight($order);
        $weight = $result['total_weight'];
        $packageContent = $result['packageContent'];

        return array(
            'orderReference' => $order->get_order_number(),
            'packageContent' => $packageContent,
            'weight' => $weight,
            'recipient_name' => $order->get_billing_first_name() . " class-modena-shipping-base.php" . $order->get_billing_last_name(),
            'recipient_phone' => $order->get_billing_phone(),
            'recipientEmail' => $order->get_billing_email(),
            '$wcOrderParcelTerminalID' => $order->get_meta('_selected_parcel_terminal_id_mdn'),);
    }


    //    public abstract function process_modena_shipping_request($order_id);

    public function process_modena_shipping_status()
    {

        $modena_shipping_status = False;

        try {
            $modena_shipping_status = $this->modena_shipping->get_modena_shipping_api_status();

            error_log("modena shipping method status: " . $modena_shipping_status);
        } catch (Exception $exception) {
            $this->shipping_logger->error('Exception occurred when authing to modena: ' . $exception->getMessage());
            $this->shipping_logger->error($exception->getTraceAsString());
        }
        if ($modena_shipping_status = 1) {
            error_log("modena shipping method status: True....");

            return True;
        }
    }

    public function add_label_url_to_order_meta_data($label_url, $order_id)
    {
        $order = wc_get_order($order_id);
        $order->add_meta_data('_selected_modena_shipping_label_url', $label_url, true);
        $order->save();
    }

    public function add_print_label_custom_order_action($actions)
    {
        error_log("Create label print custom action: " . $this->id);
        $actions['print_modena_shipping_label'] = __('Print shipping label', 'modena');
        return $actions;
    }

    public function process_print_label_custom_order_action($order)
    {
        $order_note = $this->get_placeholderPrintLabelInAdmin() . " class-modena-shipping-base.php" . $this->get_selected_shipping_destination($order) . ".";
        $order->add_order_note($order_note);
        error_log("this is the url to the label: " . $this->get_selected_shipping_label_url($order));
        $this->modena_shipping->save_modena_shipping_label_PDF_in_User($this->get_selected_shipping_destination_barcode_id($order));
    }

    public function addBulkPrintLabelCustomOrderAction($bulk_actions)
    {
        $bulk_actions['print_modena_shipping_label'] = __('Print shipping labels', 'modena');
        return $bulk_actions;
    }

    public function handleBulkPrintLabelCustomOrderAction($redirect_to, $action, $post_ids)
    {
        if ($action !== 'print_modena_shipping_label') {
            return $redirect_to;
        }

        foreach ($post_ids as $post_id) {
            // perform action for each post
            $order = wc_get_order($post_id);
            $order_note = __('printed label');
            $order->add_order_note($order_note);
        }

        // add updated query param to the redirect url
        return add_query_arg('bulk_printed_labels', count($post_ids), $redirect_to);
    }

    public function bulkActionAdminNotice()
    {
        if (!empty($_REQUEST['bulk_printed_labels'])) {
            $processed_count = intval($_REQUEST['bulk_printed_labels']);
            printf('<div id="message" class="updated fade">' .
                _n('%s order processed.', '%s orders processed.', $processed_count, 'modena') .
                '</div>', $processed_count);
        }
    }
}
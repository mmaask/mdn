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

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        add_filter('woocommerce_package_rates', array($this, 'hide_shipping_method_if_shipping_is_disabled_in_settings'));
        add_action('woocommerce_checkout_update_order_review', array($this, 'is_cart_shippable_by_modena_weight'));

        add_action('woocommerce_review_order_before_payment', array($this, 'render_modena_select_box_in_checkout'));
        add_action('woocommerce_get_order_item_totals', array($this, 'addParcelTerminalToCheckoutDetails'));
        add_filter('woocommerce_order_actions', array($this, 'add_print_label_custom_order_action'));
        add_action('woocommerce_order_action_print_modena_shipping_label', array($this, 'process_print_label_custom_order_action'));
        add_filter('bulk_actions-edit-shop_order', array($this, 'addBulkPrintLabelCustomOrderAction'));
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handleBulkPrintLabelCustomOrderAction'), 10, 3);
        add_action('admin_notices', array($this, 'bulkActionAdminNotice'));
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

        // Save settings manually
        update_option($this->get_option_key(), $this->settings);

        // Debug output to see if options are updated
        $option_name = $this->get_option_key();
        $option_values = get_option($option_name);

        error_log('Option Name: ' . $option_name);
        error_log('Option Values: ' . print_r($option_values, true));
        error_log('Instance ID: ' . $this->instance_id);
    }

    public function hide_shipping_method_if_shipping_is_disabled_in_settings($rates)
    {
        if (get_option('modena_shipping_enabled') == 'no') {
            unset($rates[$this->id]);
        }
        return $rates;
    }
    public function calculate_shipping($package = array())
    {
        global $woocommerce;
        $cart_total = $woocommerce->cart->get_cart_contents_total();
        if ($this->get_option('modena_free_shipping_treshold') === 'yes' || $this->get_option('modena_quantity_free_shipping_treshold') === 'yes') {
            if ($this->get_option('modena_free_shipping_treshold_sum') <= $cart_total || $this->get_option('modena_quantity_free_shipping_tresholdsum') < $this->get_quantity_of_products_per_modena_cart()) {
                $rate = array('id' => $this->id, 'label' => $this->title, 'cost' => 0,);
                $this->add_rate($rate);
            }
        } else {
            $rate = array('id' => $this->id, 'label' => $this->title, 'cost' => $this->cost,);
            $this->add_rate($rate);
        }
    }

    public function get_order_total_weight($order)
    {
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

    public function is_cart_shippable_by_modena_weight($order)
    {

        $wc_cart_weight = $this->get_order_total_weight($order);

        if ($wc_cart_weight <= $this->get_option('max_weight_for_modena_shipping_method')) {
            error_log("found that we can ship this...");

            return true;
        }
    }

    public function deactivate_modena_shipping_if_cart_larger_than_spec($rates, $order)
    {
        if (!$this->get_option('modena_package_measurement_checks')) {
            if ($this->is_cart_shippable_by_modena_weight($order)) {
                unset($rates[$this->id]);
            }
        }

        return $rates;
    }

    public function do_products_have_modena_dimensions($package)
    {

        foreach ($package['contents'] as $values) {
            $_product = $values['data'];
            $wooCommerceOrderProductDimensions = $_product->get_dimensions(false);

            if (empty($wooCommerceOrderProductDimensions['length']) || empty($wooCommerceOrderProductDimensions['width']) || empty($wooCommerceOrderProductDimensions['height'])) {
                return false;
            }
        }
    }

    public function deactivate_modena_shipping_no_measurements($rates, $package)
    {
        if ($this->get_option('modena_package_measurement_checks') || $this->get_option('modena_package_measurement_checks')) {
            if (!$this->do_products_have_modena_dimensions($package)) {
                unset($rates[$this->id]);
            }
        }

        return $rates;
    }

    public function get_quantity_of_products_per_modena_cart()
    {
        global $woocommerce;

        return $woocommerce->cart->get_cart_contents_count();
    }

    public function get_cart_total($order)
    {
        return $order->get_total();
    }


    public function compile_data_for_modena_shipping_request($order_id)
    {
        $order = wc_get_order($order_id);

        if (empty($order->get_meta('_selected_parcel_terminal_id_mdn'))) {
            error_log('Veateade - Tellimusel puudub salvestatud pakipunkti ID, et alustada POST päringut' . $order->get_meta('_selected_parcel_terminal_id_mdn'));
        }

        $result = $this->get_order_total_weight($order);
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

    public function parse_shipping_methods($shippingMethodID, $order_id)
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

        if ($orderShippingMethodID == $shippingMethodID) {
            //error_log("win, because methods are same named. saned.");
            return True;
        } else {
            //error_log("Metadata not saved: " . $shippingMethodID);
            return False;
        }
    }

    public function is_order_pending($order)
    {
        if ($order->get_status() == 'pending') {
            return True;
        } else {
            return False;
        }
    }



    public function render_modena_select_box_in_checkout()
    {
        if ($this->modena_shipping_type != 'parcels') {
            return;
        }

// Check if the current shipping method is not the selected one.

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

    public function add_shipping_to_checkout_details($totals, $order_id)
    {
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

    public function has_order_got_package_point_meta_data($order)
    {
        if (empty($this->get_selected_shipping_destination_barcode_id())) {
            error_log("oh no, order does not yet have a terminal.");

            return False;
        } else {
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
}

class Modena_Shipping_Service_Calls {
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
}
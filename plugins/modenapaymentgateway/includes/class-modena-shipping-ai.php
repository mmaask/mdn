<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Estonia_Shipping_Method extends WC_Shipping_Method {
    private $cost;

    public function __construct($instance_id = 0) {
        parent::__construct($instance_id);
        $this->instance_id = absint($instance_id);
        $this->supports = array('shipping-zones', 'instance-settings', );
        $this->id = 'modena-shipping-itella-terminals';
        $this->method_title = __('Itella pakiterminalid', 'woocommerce');
        $this->method_description = __('Itella pakiterminalide lahendus Modenalt', 'woocommerce');
        $this->cost = 5;

        $this->init_form_fields();
        $this->register_settings();
        $this->register_hooks();

    }
    public function init_form_fields(): void {
        $cost_desc = __('Enter a cost (excl. tax) or sum, e.g. <code>10.00 * [qty]</code>.') . '<br/><br/>' . __('Use <code>[qty]</code> for the number of items, <br/><code>[cost]</code> for the total cost of items, and <code>[fee percent="10" min_fee="20" max_fee=""]</code> for percentage based fees.');

        $this->instance_form_fields = array(
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default' => $this->method_title,
                'desc_tip' => true,
            ),
            'cost' => array(
                'title' => __('Cost'),
                'type' => 'int',
                'placeholder' => '',
                'description' => $cost_desc,
                'default' => '0',
                'desc_tip' => true,
                'sanitize_callback' => array($this, 'sanitize_cost'),
            ),
        );
    }

    public function register_settings() {
        $this->title = $this->get_option('title');
        $this->cost = $this->get_option('cost');
    }

    public function is_available($package): bool {
        $max_capacity = 35;
        $min_dimensions1 = [1, 15, 15];
        $max_dimensions1 = [60, 36, 60];

        $cart_weight = WC()->cart->get_cart_contents_weight();
        $destination_country = $package['destination']['country'] ?? '';
        if ($destination_country !== 'EE') {
            return false;
        }

        if ($cart_weight > $max_capacity) {
            return false;
        }

        $min_dimensions = $this->sanitize_dimensions($min_dimensions1);
        $max_dimensions = $this->sanitize_dimensions($max_dimensions1);

        foreach ($package['contents'] as $item_id => $values) {
            $_product = $values['data'];
            $dimensions = $_product->get_dimensions(false);

            if (empty($dimensions['length']) || empty($dimensions['width']) || empty($dimensions['height'])) {
                return true;
            }

            if ($dimensions['length'] < $min_dimensions[0] || $dimensions['width'] < $min_dimensions[1] || $dimensions['height'] < $min_dimensions[2]) {
                return false;
            }

            if ($dimensions['length'] > $max_dimensions[0] || $dimensions['width'] > $max_dimensions[1] || $dimensions['height'] > $max_dimensions[2]) {
                return false;
            }
        }
        return true;
    }

    public function calculate_shipping($package = array()) {

        $rate = array(
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $this->cost,
        );

        $this->add_rate($rate);
    }

    public function get_terminal_list()
    {
        return json_decode(file_get_contents('https://monte360.com/itella/index.php?action=displayParcelsList'))->item;
    }

    private function sanitize_dimensions($dimensions): array
    {
        return array_map(function ($dimension) {
            return max(0, (float) $dimension);
        }, $dimensions);
    }

    public function filter_available_shipping($rates, $package)
    {
        if (!$this->is_available($package)) {
            unset($rates[$this->id]);
        }
        return $rates;
    }


    /**
     * @throws Exception
     */
    public function prepare_shipping_selection_post($order_id) {

        static $rendered = false;

        if (!$rendered) {
            $order = wc_get_order($order_id);
            $orderReference = $order->get_order_number();
            $packageContent = '';
            $total_weight = 0;

            $recipientName = WC()->customer->get_billing_first_name() . ' ' . WC()->customer->get_billing_last_name();
            $recipientPhone = WC()->customer->get_shipping_phone();
            $recipientEmail = WC()->customer->get_billing_email();


            $placeId = 110;
            //$placeId = $order->get_shipping_location_id();

            foreach ($order->get_items() as $item_id => $item) {
                if ($item instanceof WC_Order_Item_Product) {
                    $product_name = $item->get_name();
                    $quantity = $item->get_quantity();
                    $packageContent .= $quantity . ' x ' . $product_name . "\n";

                    $product = $item->get_product();
                    $product_weight = $product->get_weight();
                    $total_weight += $product_weight * $quantity;
                }
            }
            $weight = $total_weight;

            $data = array(
                'orderReference' => $orderReference,
                'packageContent' => $packageContent,
                'weight' => $weight,
                'recipient_name' => $recipientName,
                'recipient_phone' => $recipientPhone,
                'recipientEmail' => $recipientEmail,
                'placeId' => $placeId,

            );
            $this->post_shipping_selection($data, $order);
        }
        $rendered = True;
    }

    /**
     * @throws Exception
     */
    public function post_shipping_selection($data, $order) {
        $curl = curl_init('https://monte360.com/itella/index.php?action=createShipment');

        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5); // Add a 5-second wait time for the response
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            error_log('Error in POST response: ' . curl_error($curl));
        } else {
            $this->parse_barcode_json($response, $order);
        }
        curl_close($curl);
    }

    /**
     * @throws Exception
     */
    public function parse_barcode_json($response, $order) {

        if (is_null($response)) {
            error_log('Response is NULL. Exiting get_label function.');
            return;
        }

        $response = trim($response, '"');
        $array = json_decode($response, true);

        if (is_null($array) || !isset($array['item']['barcode'])) {
            error_log('Cannot access barcode_id. Invalid JSON or missing key in array.');
            return Null;
        }

        $barcode_id = $array['item']['barcode'];
        $this->save_barcode_to_order($barcode_id, $order);
    }

    /**
     * @throws Exception
     */
    public function save_barcode_to_order($barcode_id, $order) {
        if (!$order instanceof WC_Order) {
            $order = wc_get_order($order);
        }
        if ($order instanceof WC_Order) {
            $order->add_meta_data('_barcode_id', $barcode_id, true);
            $order->save();
        } else {
            error_log('Could not fetch the order with the provided order ID.');
            throw new Exception("Could not fetch the order with the provided order ID.");
        }
    }

    public function display_selected_terminal_in_orders($order_id) {

        static $is_rendered = False;

        //$terminal_id = get_post_meta($order_id, 'selected_terminal', true); // Get the selected terminal id from the order meta
        $terminal_id = 110;

        if(!$is_rendered) {
            if (!empty($terminal_id)) {
                $terminalList = $this->get_terminal_list();
                $selected_terminal = array_filter($terminalList, function ($terminal) use ($terminal_id) {
                    return $terminal->{'place_id'} == $terminal_id;
                });

                if (!empty($selected_terminal)) {
                    $selected_terminal = array_shift($selected_terminal);
                    if (function_exists('is_order_received_page') && is_order_received_page()) {
                        ?>
                        <tr class="selected-terminal">
                            <th>
                                <p>
                                    <?php _e('Valitud pakiterminal', 'woocommerce'); ?>
                                </p>
                            </th>
                            <td>
                                <p>
                                    <?php echo $selected_terminal->{'name'}; ?>
                                </p>
                            </td>
                        </tr>
                        <?php
                    } else {
                        ?>
                        <tr class="selected-terminal">
                            <th>
                                <h3>
                                    <?php _e('Saadetise pakiterminal', 'woocommerce'); ?>
                                </h3>
                            </th>
                            <td>
                                <p>
                                    <?php echo $selected_terminal->{'name'}; ?>
                                </p>
                                <p>
                                    <b><a href="#" onclick="event.preventDefault(); <?php echo 'getLabel(' . $order_id . ')'; ?>">Prindi pakikaart</a></b>

                                </p>
                            </td>
                        </tr>
                        <?php
                    }
                }
            }
        }
        $is_rendered = True;
    }

    public function get_label($barcode_id) {
        $curl = curl_init('https://monte360.com/itella/index.php?action=getLabel&barcode=' . $barcode_id);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            error_log('Error in GET response: ' . curl_error($curl));
        } else {
            return $response;
        }
        curl_close($curl);
    }

    public function register_hooks() {
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_checkout_update_order_review', array($this, 'filter_available_shipping'));
        add_action('woocommerce_after_shipping_rate', array($this, 'update_checkout_assets'));
        add_action('woocommerce_thankyou', array($this, 'prepare_shipping_selection_post'));
        add_action('woocommerce_order_details_after_order_table_items', array($this, 'display_selected_terminal_in_orders'));
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_selected_terminal_in_orders'));

        // TODO save terminal_id from user selection

    }

    public function update_checkout_assets() {
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
        if (is_array($chosen_shipping_methods) && in_array($this->id, $chosen_shipping_methods)) {
            add_action('woocommerce_review_order_after_shipping', array($this, 'render_checkout_select_box'));
        }
    }

    public function render_checkout_select_box() {

        error_log('Rendered times - x +1');
        ?>
        <label for="mdn-shipping-select-box"></label>
        <select name="userShippingSelection" id="mdn-shipping-select-box">
            <option disabled selected="selected">
                <?php _e('-- Palun vali pakiautomaat --', 'woocommerce'); ?>
            </option>
            <?php
            $terminalList = $this->get_terminal_list();
            foreach ($terminalList as $terminal) {
                $terminalID = $terminal->{'place_id'};
                echo "<option value='$terminalID' >" . $terminal->{'name'} . " - " . $terminal->{'address'} . "</option>";
            }
            ?>
        </select>
        <?php
    }
}
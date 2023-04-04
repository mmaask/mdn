<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_Estonia_Shipping_Method extends WC_Shipping_Method {
    private string $userShippingSelection;
    public string $domain;
    private mixed $cost;

    public function __construct($instance_id = 0) {
        parent::__construct($instance_id);

        $this->id = 'estonia_shipping';
        $this->domain = 'modena';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Estonia Shipping', 'woocommerce');
        $this->method_description = __('Custom shipping method for Estonia', 'woocommerce');

        $this->supports = array(
            'shipping-zones',
            'instance-settings',
        );

        $this->instance_form_fields = include __DIR__ . '/class-modena-shipping-settings.php';
        $this->title = $this->get_option('title');
        $this->cost = $this->get_option('cost');

        $this->register_hooks();

    }
    public function register_hooks() {
        try {
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_after_shipping_rate', array($this, 'update_checkout_assets'));
            add_action('woocommerce_checkout_process', array($this, 'save_terminal_in_session'));


            add_action('wp_ajax_save_user_shipping_selection', array($this, 'save_user_shipping_selection'));
            add_action('wp_ajax_nopriv_save_user_shipping_selection', array($this, 'save_user_shipping_selection'));
            add_action('woocommerce_checkout_update_order_meta', array($this, 'save_terminal_to_order_meta'));

            add_action('woocommerce_thankyou', array($this, 'post_shipping_selection'));
            add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_selected_terminal_in_orders'));
            add_action('woocommerce_order_details_after_order_table_items', array($this, 'display_selected_terminal_in_orders'));


        }  catch (Exception $e) {
            $errorMessage = "Error registering hooks: " . $e->getMessage();
            error_log($errorMessage);
            add_action('admin_notices', 'modena_shipping_error_notice');
        }
    }


    public function save_terminal_in_session() {
        if (isset($_POST['userShippingSelection'])) {
            $userShippingSelection = intval($_POST['userShippingSelection']);
            error_log("User Shipping Selection: " . $userShippingSelection);
            WC()->session->set('selected_terminal', $userShippingSelection);
            WC()->session->set('user_shipping_selection', $userShippingSelection); // Save the value to the session
            $this->userShippingSelection = $userShippingSelection; // Set the class variable
        }
    }

    public function save_terminal_to_order_meta($order_id) {
        if (WC()->session->__isset('selected_terminal')) {
            update_post_meta($order_id, 'selected_terminal', WC()->session->get('selected_terminal'));
            WC()->session->__unset('selected_terminal');
        }
    }


    public function save_user_shipping_selection() {
        check_ajax_referer('save_user_shipping_selection', 'security');

        if (isset($_POST['userShippingSelection'])) {
            WC()->session->set('user_shipping_selection', sanitize_text_field($_POST['userShippingSelection']));
        }

        wp_die();
    }

    public function render_checkout_select_box() {

        static $rendered = false;

            if (!$rendered) {
            $selected_user_shipping_selection = $this->get_user_shipping_selection_from_session();
            ?>
                <tr class="woocommerce-table__line-item mdn-shipping-selection-wrapper custom-width">
                <td class="woocommerce-table__product-name">
                    <label for="userShippingSelectionChoice"></label>
                    <select class="mdn-shipping-selection" name="userShippingSelection" id="userShippingSelectionChoice">
                        <option disabled selected="selected"><?php _e('-- Palun vali pakiautomaat --', 'woocommerce'); ?></option>
                        <?php
                        $terminalList = $this->get_terminal_list();
                        foreach ($terminalList as $terminal) {
                            $terminalID = $terminal->{'place_id'};
                            $selected = ($selected_user_shipping_selection == $terminalID) ? 'selected' : '';
                            echo "<option value='$terminalID' $selected>" . $terminal->{'name'} . " - " . $terminal->{'address'} . "</option>";
                        }
                        ?>
                    </select>
                </td>
            </tr>

            <script>
                jQuery(document).ready(function($) {
                    $('form.checkout').on('submit', function() {
                        let userShippingSelection = $('#userShippingSelectionChoice').val();

                        // Save userShippingSelection to the session using AJAX
                        $.ajax({
                            type: 'POST',
                            url: '<?php echo admin_url("admin-ajax.php"); ?>',
                            data: {
                                action: 'save_user_shipping_selection',
                                userShippingSelection: userShippingSelection,
                                security: '<?php echo wp_create_nonce("save_user_shipping_selection"); ?>'
                            },
                            async: false
                        });
                    });
                });
            </script>
            <?php

            $rendered = true;
            }
    }

    public function display_selected_terminal_in_orders($order_id) {

        static $rendered = false;

        if (!$rendered) {

            //$terminal_id = get_post_meta($order_id, 'selected_terminal', true); // Get the selected terminal id from the order meta
            $terminal_id = 110;

            if (!empty($terminal_id)) {
                $terminalList = $this->get_terminal_list();
                $selected_terminal = array_filter($terminalList, function ($terminal) use ($terminal_id) {
                    return $terminal->{'place_id'} == $terminal_id;
                });

                if (!empty($selected_terminal)) {
                    $selected_terminal = array_shift($selected_terminal);
                    if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
                        ?>
                        <tr class="selected-terminal">
                            <th><p><?php _e('Valitud pakiterminal', 'woocommerce'); ?></p></th>
                            <td><p><?php echo $selected_terminal->{'name'}; ?></p></td>
                        </tr>
                        <?php
                    } else {
                    ?>
                        <tr class="selected-terminal">
                            <th><h3><?php _e('Valitud pakiterminal', 'woocommerce'); ?></h3></th>
                            <td><p><?php echo $selected_terminal->{'name'}; ?></p></td>
                        </tr>
                        <?php
                    }
                }
            }
        }
        $rendered = true;
    }

    public function get_user_shipping_selection_from_session(): int|array|string
    {
        if (WC()->session->get('user_shipping_selection')) {
            $this->userShippingSelection = WC()->session->get('user_shipping_selection');
            return $this->userShippingSelection;
        } else {
            return 0;
        }
    }

    public function get_terminal_list() {
        return json_decode(file_get_contents('https://monte360.com/itella/index.php?action=displayParcelsList'))->item;
    }

    public function calculate_shipping($package = array())
    {
        $rate = array(
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $this->cost
        );

        $this->add_rate($rate);
    }

    private function sanitize_dimensions($dimensions): array
    {
        return array_map(function ($dimension) {
            return max(0, (float) $dimension);
        }, $dimensions);
    }

    public function is_available($package): bool {
        $max_capacity = 35;
        $min_dimensions1 = [1,15,15];
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

    public function filter_available_shipping($rates, $package) {
        if (!$this->is_available($package)) {
            unset($rates[$this->id]);
        }
        return $rates;
    }

    public function update_checkout_assets() {
        add_action('woocommerce_checkout_update_order_review', array($this, 'filter_available_shipping'));
        add_action('woocommerce_review_order_before_order_total', function () {
            $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
            if (is_array($chosen_shipping_methods) && in_array($this->id, $chosen_shipping_methods)) {
                add_action('woocommerce_review_order_after_order_total', array($this, 'render_checkout_select_box'));
            }
        });
    }

    public function post_shipping_selection($order) {

//        $order_number           =   $order->get_id();
        $order_number           =   126;
        $customer_name          =   WC()->customer->get_billing_first_name() . ' class-modena-shipping-ai.php' . WC()->customer->get_billing_last_name();
        $customer_phone         =   WC()->customer->get_shipping_phone();
        $customer_email         =   WC()->customer->get_billing_email();

        $shippingterminal       =   $this->get_selected_shipping_terminal_id();

        $this->title            =   $this->get_option('title');
        $this->cost             =   $this->get_option('cost');
        $client_id              =   $this->get_option('client-id');
        $client_secret          =   $this->get_option('client-secret');
        $sender_name            =   $this->get_option('sender_name');
        $sender_email           =   $this->get_option('sender_email');
        $sender_phone           =   $this->get_option('sender_phone');

        $url = 'https://monte360.com/itella/index.php?action=createShipment';

        $data = array(
            $order_number,
            $customer_name,
            $customer_phone,
            $customer_email,
            $shippingterminal,
            $client_id,
            $client_secret,
            $sender_name,
            $sender_email,
            $sender_phone
        );

        $options = array(
            'http' => array(
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        $this->render_error_log($result);

    }

    public function render_error_log($result): void {
        ob_start();
        var_dump($result);
        $output = ob_get_clean();
        error_log($output);
    }

    public function get_selected_shipping_terminal_id(): int
    {
        return 110;
    }
}
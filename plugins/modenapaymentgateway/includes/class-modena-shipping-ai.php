<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_Estonia_Shipping_Method extends WC_Shipping_Method {

    private $cost;

    public function __construct($instance_id = 0) {
        parent::__construct($instance_id);

        $this->instance_id          = absint($instance_id);
        $this->id                   = 'modena-shipping-itella-terminals';
        $this->method_title         = __('Itella pakiterminalid', 'woocommerce');
        $this->method_description   = __('Itella pakiterminalide lahendus Modenalt', 'woocommerce');

        $this->supports             = array('shipping-zones', 'instance-settings',);
        $this->instance_form_fields = include __DIR__ . '/class-modena-shipping-settings.php';
        $this->register_hooks();

    }
    public function register_hooks() {
        try {
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_after_shipping_rate', array($this, 'update_checkout_assets'));
            add_action('woocommerce_checkout_process', array($this, 'save_terminal_in_session'));

            add_action('woocommerce_product_options_general_product_data', array($this, 'add_free_shipping_to_product'));
            add_action('woocommerce_process_product_meta', array($this, 'save_free_shipping_to_product_meta'));

            add_action('wp_ajax_save_user_shipping_selection', array($this, 'save_user_shipping_selection'));
            add_action('wp_ajax_nopriv_save_user_shipping_selection', array($this, 'save_user_shipping_selection'));
            add_action('woocommerce_checkout_update_order_meta', array($this, 'save_terminal_to_order_meta'));

            add_action('woocommerce_thankyou', array($this, 'post_shipping_selection'));
            add_action('woocommerce_order_details_after_order_table_items', array($this, 'display_selected_terminal_in_orders'));
            add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_selected_terminal_in_orders'));
            add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'render_label_orders'));

        }  catch (Exception $e) {
            $errorMessage = "Error registering hooks: " . $e->getMessage();
            error_log($errorMessage);
            add_action('admin_notices', 'modena_shipping_error_notice');
        }
    }

    public function post_shipping_selection($order_id) {

        static $rendered = false;

        if (!$rendered) {
            $contactEmail = $this->get_option('sender_email');
            $order = wc_get_order($order_id);
            $orderReference = $order->get_order_number();
            $packageContent = '';

            foreach ($order->get_items() as $item_id => $item) {
                $product_name = $item->get_name();
                $quantity = $item->get_quantity();
                $packageContent .= $quantity . ' x ' . $product_name . "\n";

                $product = $item->get_product();
                $product_weight = $product->get_weight();
                $total_weight += $product_weight * $quantity;
            }
            $weight = $total_weight;

            $recipientName = WC()->customer->get_billing_first_name() . ' ' . WC()->customer->get_billing_last_name();
            $recipientPhone = WC()->customer->get_shipping_phone();
            $recipientEmail = WC()->customer->get_billing_email();

            $placeId = $this->get_selected_shipping_terminal_id();
            //$placeId = $order->get_shipping_location_id();

            $data = array(
                '$contactEmail' => $contactEmail,
                'orderReference' => $orderReference,
                'packageContent' => $packageContent,
                'weight' => $weight,
                'recipient_name' => $recipientName,
                'recipient_phone' => $recipientPhone,
                'recipientEmail' => $recipientEmail,
                'placeId' => $placeId,

            );

            error_log('Preparing to Post...');
            $data_string = print_r($data, true);
            error_log($data_string);

            $response = wp_remote_post('https://webhook.site/d2977714-e0c8-4023-ac8c-35b3cf7bd1ba', array(
                'method' => 'POST',
                'headers' => array('Content-Type' => 'application/x-www-form-urlencoded'),
                'body' => http_build_query($data)
            ));

            $result = wp_remote_retrieve_body($response);

            $this->render_error_log($result);
        }
    }

    public function render_error_log($result): void {
        ob_start();
        var_dump($result);
        $output = ob_get_clean();

        error_log('POST response...');
        error_log($output);
    }

    public function save_free_shipping_to_product_meta($post_id) {
        // Check if the request is a product update or creation
        if (isset($_POST['action']) && $_POST['action'] == 'editpost' && isset($_POST['post_type']) && $_POST['post_type'] == 'product') {
            // Verify nonce for security
            if (!isset($_POST['_free_shipping_nonce']) || !wp_verify_nonce($_POST['_free_shipping_nonce'], '_free_shipping_action')) {
                return;
            }

            $free_shipping = isset($_POST['_free_shipping']) ? 'yes' : 'no';
            update_post_meta($post_id, '_free_shipping', $free_shipping);
        }
    }


    public function add_free_shipping_to_product() {
        static $rendered = false;

        if (!$rendered) {
            global $post;

            if ($post->post_type !== 'product') {
                return;
            }

            $product_id = $post->ID;
            $free_shipping = get_post_meta($product_id, '_free_shipping', true);

            // Add nonce for security
            echo '<input type="hidden" name="_free_shipping_nonce" id="_free_shipping_nonce" value="' . wp_create_nonce('_free_shipping_action') . '" />';

            woocommerce_wp_checkbox(
                array(
                    'id' => '_free_shipping',
                    'label' => __('Tasuta saatmine', 'woocommerce'),
                    'description' => __('Luba selle toote ostukorvi lisamisel tasuta saatmine kogu ostukorvi sisule Itella pakiterminalipunkti.', 'woocommerce'),
                    'value' => $free_shipping,
                )
            );
        }
        $rendered = true;
    }

    public function calculate_shipping($package = array())
    {
        $free_shipping = false;
        foreach (WC()->cart->get_cart_contents() as $item) {
            $product_id = $item['product_id'];
            $free_shipping_meta = get_post_meta($product_id, '_free_shipping', true);
            if ($free_shipping_meta === 'yes') {
                $free_shipping = true;
                break;
            }
        }

        $rate = array(
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $free_shipping ? 0 : $this->cost
        );

        $this->add_rate($rate);
    }


    public function save_terminal_in_session() {
        if (isset($_POST['userShippingSelection'])) {
            $userShippingSelection = intval($_POST['userShippingSelection']);
            error_log("User Shipping Selection: " . $userShippingSelection);
            WC()->session->set('selected_terminal', $userShippingSelection);
            WC()->session->set('user_shipping_selection', $userShippingSelection); // Save the value to the session

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
    public function get_selected_shipping_terminal_id(): int
    {
        return 110;
    }

}
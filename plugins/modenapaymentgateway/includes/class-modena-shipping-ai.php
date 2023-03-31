<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class WC_Estonia_Shipping_Method extends WC_Shipping_Method {
    protected   int  $max_capacity;

    protected Array $min_dimensions;
    protected Array $max_dimensions;

    protected string $userShippingSelection;


    public function __construct($instance_id = 0) {
        parent::__construct($instance_id);

        $this->id = 'estonia_shipping';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Estonia Shipping', 'woocommerce');
        $this->method_description = __('Custom shipping method for Estonia', 'woocommerce');

        $this->supports = array(
            'shipping-zones',
            'instance-settings',
        );
        $this->init();
    }

    public function init() {
        $this->instance_form_fields = array(
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('Shipping title displayed to customers', 'woocommerce'),
                'default' => $this->method_title,
                'desc_tip' => true,
            ),
            'cost' => array(
                'title' => __('Cost', 'woocommerce'),
                'type' => 'number',
                'description' => __('Shipping cost for Estonia', 'woocommerce'),
                'default' => '0',
                'desc_tip' => true,
                'placeholder' => '0.00',
            ),
        );

        $this->title = $this->get_option('title');
        $this->cost = $this->get_option('cost');

        $this->register_hooks();
    }




    public function register_hooks() {
        try {
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_after_shipping_rate', array($this, 'update_checkout_assets'));
            add_action('woocommerce_checkout_process', array($this, 'save_terminal_in_session'));


            add_action('woocommerce_checkout_update_order_meta', array($this, 'save_terminal_to_order_meta'));

            add_action('woocommerce_order_details_after_order_table_items', array($this, 'display_selected_terminal'));

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



    public function display_selected_terminal($order_id) {
        $order = wc_get_order($order_id);
        $terminal_id = $order->get_meta('selected_terminal');
        if (!empty($terminal_id)) {
            $terminalList = $this->get_terminal_list();
            $selected_terminal = array_filter($terminalList, function ($terminal) use ($terminal_id) {
                return $terminal->{'place_id'} == $terminal_id;
            });

            if (!empty($selected_terminal)) {
                $selected_terminal = array_shift($selected_terminal);
                ?>
                <tr class="selected-terminal">
                    <th><?php _e('Selected Terminal', 'woocommerce'); ?>:</th>
                    <td><?php echo $selected_terminal->{'name'} . ' - ' . $selected_terminal->{'address'} . ' - ' . $selected_terminal->{'place_id'}; ?></td>
                </tr>
                <?php
            }
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

    public function get_user_shipping_selection_from_session(): int|array|string
    {
        if (WC()->session->get('user_shipping_selection')) {
            $this->userShippingSelection = WC()->session->get('user_shipping_selection');
            return $this->userShippingSelection;
        } else {
            return 0;
        }
    }

    public function render_checkout_select_box() {
        $selected_user_shipping_selection = $this->get_user_shipping_selection_from_session();
        ?>
        <tr class="woocommerce-table__line-item mdn-shipping-selection-wrapper">
            <td class="woocommerce-table__product-name">
                <label for="userShippingSelectionChoice"><?php _e('Vali pakiautomaat:', 'woocommerce'); ?></label>
                <select class="mdn-shipping-selection" name="userShippingSelection" id="userShippingSelectionChoice">
                    <option disabled selected="selected"><?php _e('-- Palun vali pakiautomaat --', 'woocommerce'); ?></option>
                    <?php
                    $terminalList = $this->get_terminal_list();
                    foreach ($terminalList as $terminal) {
                        $terminalID = $terminal->{'place_id'};
                        $selected = ($selected_user_shipping_selection == $terminalID) ? 'selected' : '';
                        echo "<option value='$terminalID' $selected>" . $terminal->{'name'} . " - " . $terminal->{'address'} . " - " . $terminal->{'place_id'} . "</option>";
                    }
                    ?>
                </select>
            </td>
        </tr>
        <?php
    }

    private function sanitize_dimensions($dimensions): array
    {
        return array_map(function ($dimension) {
            return max(0, (float) $dimension);
        }, $dimensions);
    }

    public function is_available($package): bool {
        $this->max_capacity    = 35;
        $this->min_dimensions = [1,15,15];
        $this->max_dimensions = [60, 36, 60];

        $cart_weight = WC()->cart->get_cart_contents_weight();
        $destination_country = $package['destination']['country'] ?? '';
        if ($destination_country !== 'EE') {
            return false;
        }

        if ($cart_weight > $this->max_capacity) {
            return false;
        }

        $min_dimensions = $this->sanitize_dimensions($this->min_dimensions);
        $max_dimensions = $this->sanitize_dimensions($this->max_dimensions);

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

}


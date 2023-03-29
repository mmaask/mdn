<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

run_shipping();

function init_WC_estonia(): void {
    class WC_Estonia_Shipping_Method extends WC_Shipping_Method {
        protected   int  $max_capacity;

        public function __construct($instance_id = 0) {
            parent::__construct($instance_id);

            $this->id = 'estonia_shipping';
            $this->instance_id = absint($instance_id);
            $this->method_title = __('Estonia Shipping', 'woocommerce');
            $this->max_capacity    = 35;
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

            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));

            $this->register_hooks();
        }

        public function register_hooks() {
            try {
                add_action('woocommerce_checkout_update_order_review', array($this, 'filter_available_shipping'), 10, 2);

                add_action('woocommerce_after_shipping_rate', array($this, 'update_checkout_assets'));
                add_action('woocommerce_after_checkout_form', array($this, 'update_checkout_assets'));

                add_action('woocommerce_checkout_process', array($this, 'save_terminal_in_session'));
                add_action('woocommerce_checkout_update_order_meta', array($this, 'save_terminal_to_order_meta'));
                add_action('woocommerce_thankyou', array($this, 'display_selected_terminal'));
            }  catch (Exception $e) {
                $errorMessage = "Error registering hooks: " . $e->getMessage();
                error_log($errorMessage);
                add_action('admin_notices', 'modena_shipping_error_notice');
            }
        }

        public function update_checkout_assets() {
            error_log('loading_checkout_assets');
            add_action('woocommerce_review_order_before_order_total', array($this, 'render_checkout_select_box'));
        }

        public function save_terminal_in_session() {
            if (isset($_POST['userShippingSelection'])) {
                WC()->session->set('selected_terminal', intval($_POST['userShippingSelection']));
            }
        }

        public function save_terminal_to_order_meta($order_id) {
            if (WC()->session->__isset('selected_terminal')) {
                update_post_meta($order_id, 'selected_terminal', WC()->session->get('selected_terminal'));
                WC()->session->__unset('selected_terminal');
            }
        }

        public function render_checkout_select_box() {
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
                            echo "<option value='$terminalID'>" . $terminal->{'name'} . " - " . $terminal->{'address'} . " - " . $terminal->{'place_id'} . "</option>";
                        }
                        ?>
                    </select>
                </td>
                </tr>
                <?php
        }

        public function display_selected_terminal($order_id)
        {
            $order = wc_get_order($order_id);
            $terminal_id = $order->get_meta('selected_terminal');
            if (!empty($terminal_id)) {
                $terminalList = $this->get_terminal_list();
                $selected_terminal = array_filter($terminalList, function ($terminal) use ($terminal_id) {
                    return $terminal->{'place_id'} == $terminal_id;
                });

                if (!empty($selected_terminal)) {
                    $selected_terminal = array_shift($selected_terminal);
                    echo '<p><strong>' . __('Selected Terminal:', 'woocommerce') . '</strong> ' . $selected_terminal->{'name'} . ' - ' . $selected_terminal->{'address'} . ' - ' . $selected_terminal->{'place_id'} . '</p>';
                }
            }
        }

        public function filter_available_shipping($rates, $package) {
            if (!$this->is_available($package)) {
                unset($rates[$this->id]);
            }
            $this->update_checkout_assets();
            return $rates;
        }

        public function is_available($package): bool {
            $cart_weight = WC()->cart->get_cart_contents_weight();
            $destination_country = $package['destination']['country'] ?? '';
            if ($destination_country !== 'EE') {
                return false;
            }
            return $cart_weight <= $this->max_capacity;
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
    }
}

/**
 *
 * Checks for the existence of the required classes and initializes
 * Handles errors if the required classes do not exist.
 *
 * @return void
 */


function run_shipping(): void {


    if (!class_exists('WC_Estonia_Shipping_Method') && class_exists('WC_Shipping_Method')) {
        add_filter('woocommerce_shipping_methods', 'load_modena_shipping_methods');
        add_action('woocommerce_shipping_init', 'init_WC_estonia');

    } else {
        $errorMessage = "Error: ";
        if (!class_exists('WC_Estonia_Shipping_Method')) {
            $errorMessage .= "The 'WC_Estonia_Shipping_Method' class does not exist. ";
        }
        if (!class_exists('WC_Shipping_Method')) {
            $errorMessage .= "The 'WC_Shipping_Method' class does not exist. ";
        }
        error_log($errorMessage);
        add_action('admin_notices', 'modena_shipping_error_notice');
    }
}
/**
 * @param array $methods Array of existing WooCommerce shipping methods.
 * @return array Updated array of WooCommerce shipping methods, including the Modena Shipping Self Service method.
 */
function load_modena_shipping_methods(array $methods): array {
    $methods['estonia_shipping'] = 'WC_Estonia_Shipping_Method';
    return $methods;
}
/**
 * Displays an admin notice with an error message.

 * @return void
 */
function modena_shipping_error_notice(): void {
    echo '<div class="notice notice-error"><p><strong>Modena Shipping Error:</strong> The required classes were not found. Please ensure the necessary dependencies are installed and active.</p></div>';
}
/**
 * Call the function to clear the debug.log file
 *
**/




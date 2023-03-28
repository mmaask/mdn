<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
/*
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */

run_shipping();

function init_WC_estonia(): void {
    class WC_Estonia_Shipping_Method extends WC_Shipping_Method {
        protected   int  $max_capacity;
        private     WC_Logger           $shipping_logger;

        public function __construct($instance_id = 0) {
            parent::__construct($instance_id);

            $this->id = 'estonia_shipping';
            $this->instance_id = absint($instance_id);
            $this->method_title = __('Estonia Shipping', 'woocommerce');
            $this->max_capacity    = 35;
            $this->method_description = __('Custom shipping method for Estonia', 'woocommerce');

            require_once(MODENA_PLUGIN_PATH . 'includes/class-modena-log-handler.php');
            $this->shipping_logger        = new WC_Logger(array(new Modena_Log_Handler()));

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

                add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options')); // this hook is working
                add_filter('woocommerce_package_rates', array($this, 'filter_package_rates'), 10, 2);
                add_action('woocommerce_review_order_after_shipping', array($this, 'render_checkout_select_box'), 10);

                add_action('woocommerce_checkout_process', array($this, 'save_terminal_in_session'));
                add_action('woocommerce_checkout_update_order_meta', array($this, 'save_terminal_to_order_meta'));
                add_action('woocommerce_thankyou', array($this, 'display_selected_terminal'));
            }  catch (Exception $e) {
                $errorMessage = "Error registering hooks: " . $e->getMessage();
                error_log($errorMessage);
                add_action('admin_notices', 'modena_shipping_error_notice');
            }
        }

        public function render_checkout_select_box() {
            $chosen_methods = WC()->session->get('chosen_shipping_methods');
            $chosen_method = $chosen_methods[0] ?? '';

            if ($chosen_method === $this->id) {
                $this->shipping_logger->debug('trying to render checkout_select_box... ' . $chosen_method);

                $terminalList = $this->get_terminal_list(); ?>

                    // does this return the list? why select box is not shown? hook is working fine.

                <tr class="mdn-shipping-selection-wrapper">
                    <th><?php _e('Pakiautomaat', 'woocommerce'); ?></th>
                    <td>
                        <label for="userShippingSelectionChoice"></label><select class="mdn-shipping-selection" name="userShippingSelection" id="userShippingSelectionChoice">
                            <option disabled value="110" selected="selected"><?php _e('-- Palun vali pakiautomaat --', 'woocommerce'); ?></option>
                            <?php
                            for ($x = 0; $x <= count($terminalList) - 1; $x++) {
                                $terminalID = $terminalList[$x]->{'place_id'};
                                echo "<option value=$terminalID>" . $terminalList[$x]->{'name'} . " - " . $terminalList[$x]->{'address'} . " - " . $terminalList[$x]->{'place_id'} . "</option>";
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        function showHideSelectBox() {
                            let shipping_method = $('input[name="shipping_method[0]"]:checked').val();
                            if (shipping_method === 'estonia_shipping') {
                                $('.mdn-shipping-selection-wrapper').show();
                            } else {
                                $('.mdn-shipping-selection-wrapper').hide();
                            }
                        }
                        showHideSelectBox();
                        $(document.body).on('change', 'input[name="shipping_method[0]"]', showHideSelectBox);
                    });
                </script><?php
            }
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

        public function filter_package_rates($rates, $package) {
            if (!$this->is_available($package)) {
                $this->shipping_logger->debug('shipping not avail' . $this->id);
                unset($rates[$this->id]);
            }
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
                'cost' => $this->cost,
            );

            $this->add_rate($rate);
        }
    }
}

/**
 * Initializes the Modena Shipping Self Service method if it doesn't already exist and
 * WooCommerce Shipping Method class exists.
 *
 * This function checks for the existence of the Modena_Shipping_Self_Service class and
 * the WC_Shipping_Method class. If the Modena_Shipping_Self_Service class does not exist
 * and the WC_Shipping_Method class exists, it hooks the 'woocommerce_shipping_init'
 * action to the 'initializeModenaShippingMethod' function and the 'woocommerce_shipping_methods'
 * filter to the 'add_modena_shipping_flat' function.
 *
 * Checks for the existence of the required classes and initializes the Modena Shipping Self Service method.
 * Handles errors if the required classes do not exist.
 *
 * Define a custom error message.
 * Check if the 'Modena_Shipping_Self_Service' class exists.
 * Check if the 'WC_Shipping_Method' class exists.
 * Log the error message.
 * Optionally, display an admin notice if you'd like to notify the site administrator.
 *
 * @return void
 */


function run_shipping(): void {

    //clear_debug_log();

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
 * Adds the Modena Shipping Self Service method to the list of available WooCommerce shipping methods.
 *
 * This function adds the 'itella_self_service_by_modena' method to the array of available shipping
 * methods in WooCommerce. The method is an instance of the 'Modena_Shipping_Self_Service' class.
 *
 * @param array $methods Array of existing WooCommerce shipping methods.
 * @return array Updated array of WooCommerce shipping methods, including the Modena Shipping Self Service method.
 */
function load_modena_shipping_methods(array $methods): array {
    $methods['estonia_shipping'] = 'WC_Estonia_Shipping_Method';
    return $methods;
}
/**
 * Displays an admin notice with an error message.
 *
 * This function outputs an error message to the WordPress admin dashboard
 * when the required classes for the Modena Shipping plugin are not found.
 * It informs the user to ensure the necessary dependencies are installed
 * and active.
 *
 * @return void
 */
function modena_shipping_error_notice(): void {
    echo '<div class="notice notice-error"><p><strong>Modena Shipping Error:</strong> The required classes were not found. Please ensure the necessary dependencies are installed and active.</p></div>';
}
/**
 * Call the function to clear the debug.log file
 *
**/
function clear_debug_log(): void
{
    $log_path = WP_CONTENT_DIR . '/debug.log';

    if (file_exists($log_path)) {
        $file_handle = fopen($log_path, 'w');
        if ($file_handle) {
            fclose($file_handle);
            echo 'The debug.log file has been cleared.';
        } else {
            echo 'Unable to open the debug.log file.';
        }
    } else {
        echo 'The debug.log file does not exist.';
    }
}



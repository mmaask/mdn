<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
function init_WC_estonia(): void {
    require_once(MODENA_PLUGIN_PATH . 'includes/class-modena-log-handler.php');
    class WC_Estonia_Shipping_Method extends WC_Shipping_Method
    {
        protected   int  $max_capacity;
        private     WC_Logger           $shipping_logger;

        public function __construct($instance_id = 0)
        {
            parent::__construct();

            $this->id = 'estonia_shipping';
            $this->instance_id = absint($instance_id);
            $this->method_title = __('Estonia Shipping', 'woocommerce');
            $this->max_capacity    = 35;
            $this->method_description = __('Custom shipping method for Estonia', 'woocommerce');
            $this->shipping_logger        = new WC_Logger(array(new Modena_Log_Handler()));
            $this->supports = array(
                'shipping-zones',
                'instance-settings',
            );

            $this->shipping_logger->debug('method constructed: ' . $this->id);

            $this->init();
            $this->register_hooks();
        }

        public function init()
        {
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

        }

        public function register_hooks() {
            $this->shipping_logger->debug('registering hooks... ');

            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
            add_filter('woocommerce_shipping_' . $this->id . '_is_available', array($this, 'is_available'));

            // below is for testing hooks

            add_filter('woocommerce_review_order_before_order_total','render_checkout_select_box');
            add_action('woocommerce_after_checkout_shipping_form', array($this, 'render_checkout_select_box'));
            add_action('woocommerce_review_order_before_order_total', array($this, 'render_checkout_select_box'));
            add_filter('woocommerce_review_order_before_order_total', array($this, 'render_checkout_select_box'));

            // above is for testing hooks

            add_action('woocommerce_checkout_process', array($this, 'save_terminal_in_session'));
            add_action('woocommerce_checkout_update_order_meta', array($this, 'save_terminal_to_order_meta'));
            add_action('woocommerce_thankyou', array($this, 'display_selected_terminal'));
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

        public function is_available($package): bool
        {
            $this->shipping_logger->debug('is available... ');

            $cart_weight = WC()->cart->get_cart_contents_weight();
            $destination_country = $package['destination']['country'] ?? '';

            if ($destination_country !== 'EE') {
                $this->shipping_logger->debug('current destination country: ' . $destination_country);
                return false;
            }
            $this->shipping_logger->debug('is available if result not negative: ' . $cart_weight <= $this->max_capacity);
            return $cart_weight <= $this->max_capacity;
        }

        public function get_terminal_list() {
            return json_decode(file_get_contents('https://monte360.com/itella/index.php?action=displayParcelsList'))->item;
        }

        public function render_checkout_select_box_test(): void {
            $this->shipping_logger->debug('trying to render checkout_test_select_box... ');

            $terminalList = $this->get_terminal_list();
            echo '
                    <form name="terminalnamepost" method="post" action="select-shipping-method">
                    <select class="mdn-shipping-selection" name="userShippingSelection" id="userShippingSelectionChoice" onselect="">
                    <option disabled value="110" selected="selected">-- Palun vali pakiautomaat --</option>';
            for ($x = 0; $x <= count($terminalList) - 1; $x++) {
                $terminalID = $terminalList[$x]->{'place_id'};
                print_r("<option value=$terminalID>" . $terminalList[$x]->{'name'} . " - " . $terminalList[$x]->{'address'} . " - " . $terminalList[$x]->{'place_id'} . "<br></option>");
            }
            echo '</select></form>';
        }

        public function render_checkout_select_box(): void {
            $this->shipping_logger->debug('trying to render checkout_select_box... ');

            $terminalList = json_decode(file_get_contents('https://monte360.com/itella/index.php?action=displayParcelsList'))->item; ?>

            <tr class="mdn-shipping-selection-wrapper" style="display:none;">
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


    }
}
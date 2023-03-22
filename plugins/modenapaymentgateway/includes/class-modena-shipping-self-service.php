<?php

if (!defined('ABSPATH')) {
    exit;
}

function run_shipping(): void {
    require_once(MODENA_PLUGIN_PATH . 'includes/class-modena-log-handler.php');
    add_action('woocommerce_shipping_init', 'initializeModenaShippingMethod');
    add_filter('woocommerce_shipping_methods', 'add_modena_shipping_flat');
}

function add_modena_shipping_flat($methods) {
    $methods['modena_mock_shipping_flat'] = 'Modena_Shipping_Self_Service';
    return $methods;
}

function initializeModenaShippingMethod(): void {
    class Modena_Shipping_Self_Service extends WC_Shipping_Method {

        protected   string              $modena_id;
        protected   string              $modena_secret;
        protected   string              $shipping_label_url;
        protected   string              $sender_name;
        protected   string              $sender_email;
        protected   string              $sender_phone;
        protected   string              $itella_api_key;
        protected   string              $itella_api_secret;
        protected   int                 $terminal_max_weight;
        protected   int                 $shipping_terminal_id;
        protected   int                 $order_weight;
        protected   mixed               $free_shipping_treshold;
        protected   mixed               $cost;
        private     string              $domain;
        private     WC_Logger           $shipping_logger;

        public function __construct($instance_id = 0) {
            parent::__construct();

            $this->instance_id            = absint($instance_id);
            $this->domain                 = 'modena';
            $this->id                     = 'itella_self_service_by_modena';
            $this->method_title           = __('Itella parcel terminals by Modena', $this->domain);
            $this->method_description     = 'Modena allows consumers to receive their purchases via Itella terminals';
            $this->title                  = __('Itella Terminals by Modena', $this->domain);
            $this->supports               = array('shipping-zones', 'instance-settings', 'instance-settings-modal');

            $this->modena_id              = $this->get_option( 'client-id' );
            $this->modena_secret          = $this->get_option( 'client-secret' );
            $this->cost                   = $this->get_option( 'cost' );
            $this->free_shipping_treshold = $this->get_option( 'free-shipping-treshold' );
            $this->sender_name            = $this->get_option( 'sender_name' );
            $this->sender_email           = $this->get_option( 'sender_email' );
            $this->sender_phone           = $this->get_option( 'sender_phone' );
            $this->itella_api_key        = $this->get_option( 'itella_api_key' );
            $this->itella_api_secret     = $this->get_option( 'itella_api_secret' );

            $this->shipping_label_url     = $this->get_shipping_label_url();
            $this->order_weight           = $this->get_total_order_weight();
            $this->shipping_terminal_id   = $this->get_selected_shipping_terminal_id();
            $this->terminal_max_weight    = $this->get_terminal_max_weight();
            $this->shipping_logger        = new WC_Logger(array(new Modena_Log_Handler()));

            $this->init_form_fields();
            $this->init_settings();

            add_action('woocommerce_update_options_shipping_methods', array(&$this, 'process_admin_options'));
            add_filter('woocommerce_shipping_' . $this->id . '_is_available', array($this, 'check_if_allowed_zone_for_shipping'));
        }
        public function is_available($package): bool
        {
            if ($this->check_if_allowed_zone_for_shipping($package) === false) {
                return false;
            }
            return parent::is_available($package);
        }

        public function check_if_allowed_zone_for_shipping($package): bool
        {
            if (isset($package['destination']['country']) && $package['destination']['country'] === 'EE') {
                return true;
            } else {
                return false;
            }
        }


        protected function get_cart_total_weight(): int {
            try {
                if(is_checkout()) {
                    $order_weight = WC()->cart->get_cart_contents_weight();
                    $this->shipping_logger->error('OK: Is checkout page and total order weight is ' . $order_weight);
                    $this->set_total__order_weight($order_weight);
                    return $order_weight;
                }
            } catch(Exception $e) {
                $this->shipping_logger->error('Error: Function is called upon outside of checkout page. ' . $e->getMessage());
            }
            return 'something';
        }

        protected function check_package_weight(): bool {
            $orderweight = $this->get_cart_total_weight();
            if($orderweight) {
                if ($orderweight <= $this->get_shipping_method_max_weight()) {
                    $this->shipping_logger->error('OK. Order weight under max limit' . $orderweight);
                    return True;
                } else {
                    $this->shipping_logger->error('OK. Order weight over max limit' . $orderweight);
                    return False;
                }
            } else {
                $this->shipping_logger->error('Error: order weight not found ' . $orderweight);
                return False;
            }
        }

        protected function populate_itella_terminal_list() {
            $json = file_get_contents('https://monte360.com/itella/index.php?action=displayParcelsList');
            $obj = json_decode($json);
            return $obj->{'item'};
        }

        protected function render_checkout_select_box_test(): void {
            $terminalList = $this->populate_itella_terminal_list();

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

        protected function post_shipping_selection($order) {
            $content            =   'Test package';
            $name               =   WC()->customer->get_billing_first_name() . ' class-modena-shipping-itella-terminals.php' . WC()->customer->get_billing_last_name();
            $phone              =   WC()->customer->get_shipping_phone();
            $email              =   WC()->customer->get_billing_email();
            $reference          =   $order->get_id();
            $shippingterminal   =   $this->get_selected_shipping_terminal_id();
            $weight             =   $this->get_total_order_weight();
            $client_id          =   $this->modena_id;
            $client_secret      =   $this->modena_secret;

            $url = 'https://monte360.com/itella/index.php?action=createShipment';

            $data = array(
                $reference,
                $content,
                $weight,
                $name,
                $phone,
                $email,
                $shippingterminal,
                $client_id,
                $client_secret,
                $this->sender_name,
                $this->sender_email,
                $this->sender_phone);

            $options = array(
                'http' => array(
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query($data)
                )
            );
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);

            // add the received label url to the current order. this can be improved upon to specify which part of the result we want to pass onto. A new concept in OOP
            $this->set_shipping_label_url($result);
            $order->add_meta_data($this->get_shipping_label_url(), true);
            var_dump($result);

        }

        public function add_shipping_method_column_in_woocommerce_orders($columns)
        {
            add_filter('manage_edit-shop_order_columns', [$this, 'add_shipping_method_column_in_woocommerce_orders']);

            if (isset($this->id)) {
                return $columns;
            }

            $columns[$this->id] = __('Shipping');
            return $columns;
        }

        public function populate_shipping_method_column_in_woocommerce_orders($columnName, $postId) {
            if ($columnName !== $this->id) {
                return;
            }

            echo get_post_meta($postId, $this->get_shipping_label_url(), true);
        }

        protected function add_label_to_order_page($label_url) {
            print_r("Shipping Label: " . $label_url);
        }

        protected function get_shipping_method_max_weight(): int {
            return $this->terminal_max_weight;
        }



        protected function set_total__order_weight($order_weight) {
            $this->order_weight = $order_weight;
        }

        protected function set_shipping_label_url($shipping_label_url): void {
            $this->shipping_label_url = $shipping_label_url;
        }

        protected function get_total_order_weight(): int {
            return 0;
        }
        protected function get_selected_shipping_terminal_id(): int {
            return 110;
        }
        protected function get_shipping_label_url(): string {
            return 'https://google.ee';
        }

        protected function get_terminal_max_weight(): int {
            return 35;
        }

        /**
         * @var string $fee_cost
         */

        protected $fee_cost = '';

        /**
         * Evaluate a cost from a sum/string.
         *
         * @param  string $sum Sum of shipping.
         * @param  array  $args Args, must contain `cost` and `qty` keys. Having `array()` as default is for back compat reasons.
         * @return string
         */
        protected function evaluate_cost( $sum, $args = array() ) {
            // Add warning for subclasses.
            if ( ! is_array( $args ) || ! array_key_exists( 'qty', $args ) || ! array_key_exists( 'cost', $args ) ) {
                wc_doing_it_wrong( __FUNCTION__, '$args must contain `cost` and `qty` keys.', '4.0.1' );
            }

            include_once WC()->plugin_path() . '/includes/libraries/class-wc-eval-math.php';

            // Allow 3rd parties to process shipping cost arguments.
            $args           = apply_filters( 'woocommerce_evaluate_shipping_cost_args', $args, $sum, $this );
            $locale         = localeconv();
            $decimals       = array( wc_get_price_decimal_separator(), $locale['decimal_point'], $locale['mon_decimal_point'], ',' );
            $this->fee_cost = $args['cost'];

            // Expand shortcodes.
            add_shortcode( 'fee', array( $this, 'fee' ) );

            $sum = do_shortcode(
                str_replace(
                    array(
                        '[qty]',
                        '[cost]',
                    ),
                    array(
                        $args['qty'],
                        $args['cost'],
                    ),
                    $sum
                )
            );

            remove_shortcode( 'fee', array( $this, 'fee' ) );

            // Remove whitespace from string.
            $sum = preg_replace( '/\s+/', '', $sum );

            // Remove locale from string.
            $sum = str_replace( $decimals, '.', $sum );

            // Trim invalid start/end characters.
            $sum = rtrim( ltrim( $sum, "\t\n\r\0\x0B+*/" ), "\t\n\r\0\x0B+-*/" );

            // Do the math.
            return $sum ? WC_Eval_Math::evaluate( $sum ) : 0;
        }

        /**
         * Work out fee (shortcode).
         *
         * @param  array $atts Attributes.
         * @return string
         */
        public function fee( $atts ) {
            $atts = shortcode_atts(
                array(
                    'percent' => '',
                    'min_fee' => '',
                    'max_fee' => '',
                ),
                $atts,
                'fee'
            );

            $calculated_fee = 0;

            if ( $atts['percent'] ) {
                $calculated_fee = $this->fee_cost * ( floatval( $atts['percent'] ) / 100 );
            }

            if ( $atts['min_fee'] && $calculated_fee < $atts['min_fee'] ) {
                $calculated_fee = $atts['min_fee'];
            }

            if ( $atts['max_fee'] && $calculated_fee > $atts['max_fee'] ) {
                $calculated_fee = $atts['max_fee'];
            }

            return $calculated_fee;
        }

        /**
         * Get items in package.
         *
         * @param  array $package Package of items from cart.
         * @return int
         */
        public function get_package_item_qty( $package ) {
            $total_quantity = 0;
            foreach ( $package['contents'] as $item_id => $values ) {
                if ( $values['quantity'] > 0 && $values['data']->needs_shipping() ) {
                    $total_quantity += $values['quantity'];
                }
            }
            return $total_quantity;
        }

        /**
         * Calculate the shipping costs.
         *
         * @param array $package Package of items from cart.
         */
        public function calculate_shipping( $package = array() ) {
            $rate = array(
                'id'      => $this->get_rate_id(),
                'label'   => $this->title,
                'cost'    => 0,
                'package' => $package,
            );

            // Calculate the costs.
            $has_costs = false; // True when a cost is set. False if all costs are blank strings.
            $cost      = $this->get_option( 'cost' );

            if ( '' !== $cost ) {
                $has_costs    = true;
                $rate['cost'] = $this->evaluate_cost(
                    $cost,
                    array(
                        'qty'  => $this->get_package_item_qty( $package ),
                        'cost' => $package['contents_cost'],
                    )
                );
            }

            // Add shipping class costs.
            $shipping_classes = WC()->shipping()->get_shipping_classes();

            if ( ! empty( $shipping_classes ) ) {
                $found_shipping_classes = $this->find_shipping_classes( $package );
                $highest_class_cost     = 0;

                foreach ( $found_shipping_classes as $shipping_class => $products ) {
                    // Also handles BW compatibility when slugs were used instead of ids.
                    $shipping_class_term = get_term_by( 'slug', $shipping_class, 'product_shipping_class' );
                    $class_cost_string   = $shipping_class_term && $shipping_class_term->term_id ? $this->get_option( 'class_cost_' . $shipping_class_term->term_id, $this->get_option( 'class_cost_' . $shipping_class, '' ) ) : $this->get_option( 'no_class_cost', '' );

                    if ( '' === $class_cost_string ) {
                        continue;
                    }

                    $has_costs  = true;
                    $class_cost = $this->evaluate_cost(
                        $class_cost_string,
                        array(
                            'qty'  => array_sum( wp_list_pluck( $products, 'quantity' ) ),
                            'cost' => array_sum( wp_list_pluck( $products, 'line_total' ) ),
                        )
                    );

                    if ( 'class' === $this->type ) {
                        $rate['cost'] += $class_cost;
                    } else {
                        $highest_class_cost = max($class_cost, $highest_class_cost);
                    }
                }

                if ( 'order' === $this->type && $highest_class_cost ) {
                    $rate['cost'] += $highest_class_cost;
                }
            }

            if ( $has_costs ) {
                $this->add_rate( $rate );
            }

            /**
             * Developers can add additional flat rates based on this one via this action since @version 2.4.
             *
             * Previously there were (overly complex) options to add additional rates however this was not user.
             * friendly and goes against what Flat Rate Shipping was originally intended for.
             */
            do_action( 'woocommerce_' . $this->id . '_shipping_add_rate', $this, $rate );
        }

        /**
         * Finds and returns shipping classes and the products with said class.
         *
         * @param mixed $package Package of items from cart.
         * @return array
         */
        public function find_shipping_classes( $package ) {
            $found_shipping_classes = array();

            foreach ( $package['contents'] as $item_id => $values ) {
                if ( $values['data']->needs_shipping() ) {
                    $found_class = $values['data']->get_shipping_class();

                    if ( ! isset( $found_shipping_classes[ $found_class ] ) ) {
                        $found_shipping_classes[ $found_class ] = array();
                    }

                    $found_shipping_classes[ $found_class ][ $item_id ] = $values;
                }
            }

            return $found_shipping_classes;
        }

        /**
         * Sanitize the cost field.
         *
         * @since 3.4.0
         * @param string $value Unsanitized value.
         * @throws Exception Last error triggered.
         * @return string
         */
        public function sanitize_cost( $value )
        {
            $value = is_null($value) ? '' : $value;
            $value = wp_kses_post(trim(wp_unslash($value)));
            $value = str_replace(array(get_woocommerce_currency_symbol(), html_entity_decode(get_woocommerce_currency_symbol())), '', $value);
            // Thrown an error on the front end if the evaluate_cost will fail.
            $dummy_cost = $this->evaluate_cost(
                $value,
                array(
                    'cost' => 1,
                    'qty' => 1,
                )
            );
            if (false === $dummy_cost) {
                throw new Exception(WC_Eval_Math::$last_error);
            }
            return $value;
        }
        public function init_form_fields() {

            $cost_desc = __( 'Enter a cost (excl. tax) or sum, e.g. <code>10.00 * [qty]</code>.', $this->domain ) . '<br/><br/>' . __( 'Use <code>[qty]</code> for the number of items, <br/><code>[cost]</code> for the total cost of items, and <code>[fee percent="10" min_fee="20" max_fee=""]</code> for percentage based fees.', $this->domain );

            $this->instance_form_fields = array(
                'title'            => array(
                    'title'       => __( 'Title', 'woocommerce' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default'     => $this->method_title,
                    'desc_tip'    => true,
                ),
                'cost'       => array(
                    'title'             => __( 'Cost', $this->domain ),
                    'type'              => 'text',
                    'placeholder'       => '',
                    'description'       => $cost_desc,
                    'default'           => '0',
                    'desc_tip'          => true,
                    'sanitize_callback' => array( $this, 'sanitize_cost' ),
                ),
                'free-shipping-treshold'      => array(
                    'title'       => __( 'Free shipping over sum of' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the amount needed for free shipping.'),
                    'default'     => __(0 ),
                    'desc_tip'    => true,
                ),
                'sender-name'       => array(
                    'title'       => __( 'Sender name' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the parcel sender name that is required and sent to Itella.'),
                    'default'     => __( ''),
                    'desc_tip'    => true,
                ),
                'sender-email'       => array(
                    'title'       => __( 'Sender email' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the parcel sender email that is required and sent to Itella.'),
                    'default'     => __( ''),
                    'desc_tip'    => true,
                ),
                'sender-phone'       => array(
                    'title'       => __( 'Sender phone' ),
                    'type'        => 'number',
                    'description' => __( 'This controls the parcel sender phone number that is required and sent to Itella.'),
                    'default'     => __('' ),
                    'desc_tip'    => true,
                ),
                'itella_api_key'      => array(
                    'title'       => __( 'Itella API Key' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the API key Secret from Modena'),
                    'default'     => __( 'thisistheitellaapikeyfor1444221!'),
                    'desc_tip'    => true,
                ),
                'itella_api_secret'      => array(
                    'title'       => __( 'Itella API Secret' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the API key Secret from Itella'),
                    'default'     => __( 'secretcustomerapisecretfromparterportal112.xxxa4!'),
                    'desc_tip'    => true,
                ),
                'client-id'      => array(
                    'title'       => __( 'Modena API ID' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the API key ID from Modena'),
                    'default'     => __( 'idofcustomersecretkeyfromparterportal112.yyyy66!'),
                    'desc_tip'    => true,
                ),
                'client-secret'      => array(
                    'title'       => __( 'Modena API Secret' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the API key Secret from Modena'),
                    'default'     => __( 'secretcustomerapisecretfromparterportal112.xxxa4!'),
                    'desc_tip'    => true,
                )
            );

        }
    }
}
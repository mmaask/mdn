<?php
use Automattic\WooCommerce\Utilities\NumberUtil;

/**
 * Exit if accessed directly and if woocom is active
 */

if (!defined('ABSPATH')) { exit;}
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {


    add_action('woocommerce_shipping_init', 'initializeModenaShippingMethod');
    add_filter('woocommerce_shipping_methods', 'add_modena_shipping_flat');

    function add_modena_shipping_flat($methods) {

        $methods['modena_mock_shipping_flat'] = 'Modena_Shipping_Self_Service';
        return $methods;
    }

function initializeModenaShippingMethod(): void {
    class Modena_Shipping_Self_Service extends WC_Shipping_Method {
        public function __construct($instance_id = 0) {
            $this->instance_id          = absint($instance_id);
            $this->domain               = 'modena';
            $this->id                   = 'itella_self_service_by_modena';
            $this->method_title         = __('Itella parcel terminals by Modena', $this->domain);
            $this->method_description   = __('Itella Parcel Terminals by Modena', $this->domain);
            $this->title                = __('Itella Terminals by Modena', $this->domain);
            $this->supports             = array('shipping-zones', 'instance-settings', 'instance-settings-modal');
            $this->packagemaxweight     = 35;
            $this->init();
    }

        public function init() {
            $this->init_form_fields();
            $this->enabled              = $this->get_option('enabled');
            $this->title                = $this->get_option( 'title' );
            $this->cost                 = $this->get_option( 'cost' );
            $this->type                 = $this->get_option( 'type', 'class' );
            $this->packagemaxweight     = $this->get_option('packagemaxweight');
        }

        public function validatePackageWeight(): void {

            // access created shipping method max weight. Try avoiding hardcode, since it is adjustable in the admin setting? Should it be adjustable?
            // where do we go and initialize this code??

            $shippingmethodmaxweight = 35;
            $orderweight = WC()->cart->get_cart_contents_weight();

            if($orderweight > $shippingmethodmaxweight) {
                print_r("Total package weight: " . $orderweight);
            } else {
                print_r("Total package weight: " . $orderweight);
            }
        }

        /**
         * @var string $fee_cost
         */

    protected $fee_cost = '';

        /**
         * Get setting form fields for instances of this shipping method within zones.
         *
         * @return array
         */
    public function get_instance_form_fields() {
        return parent::get_instance_form_fields();
    }

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
                        $highest_class_cost = $class_cost > $highest_class_cost ? $class_cost : $highest_class_cost;
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
        public function sanitize_cost( $value ) {
            $value = is_null( $value ) ? '' : $value;
            $value = wp_kses_post( trim( wp_unslash( $value ) ) );
            $value = str_replace( array( get_woocommerce_currency_symbol(), html_entity_decode( get_woocommerce_currency_symbol() ) ), '', $value );
            // Thrown an error on the front end if the evaluate_cost will fail.
            $dummy_cost = $this->evaluate_cost(
                $value,
                array(
                    'cost' => 1,
                    'qty'  => 1,
                )
            );
            if ( false === $dummy_cost ) {
                throw new Exception( WC_Eval_Math::$last_error );
            }
            return $value;
        }
        /**
         * Initialize form fields. täiesti teistmoodi ju. allpool pole accessible aga slice objektil on accessible form fields, et manipulateda objekti dataga mootorit
         * tee ümber
         */
        public function init_form_fields() {


            $cost_desc = __( 'Enter a cost (excl. tax) or sum, e.g. <code>10.00 * [qty]</code>.', $this->domain ) . '<br/><br/>' . __( 'Use <code>[qty]</code> for the number of items, <br/><code>[cost]</code> for the total cost of items, and <code>[fee percent="10" min_fee="20" max_fee=""]</code> for percentage based fees.', $this->domain );

            $this->instance_form_fields = array(
                'enabled' => array(
                    'title'         => __('Enable/Disable'),
                    'type'             => 'checkbox',
                    'label'         => __('Enable this shipping method'),
                    'default'         => 'yes',
                ),
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
                'packagemaxweight'       => array(
                    'title'             => __( 'Package max weight', $this->domain ),
                    'type'              => 'number',
                    'placeholder'       => '',
                    'description'       => 'This controls the maximum weight that Itella package container holds.',
                    'default'           => 35,
                    'desc_tip'          => true,
                    'sanitize_callback' => array( $this, 'sanitize_cost' ),
                ),
            );

            $this->instance_form_fields['shipping_method_max_weight'] = [
                'title'       => __('Package max weight **', 'modena'),
                'type'        => 'number',
                'description' => 'Package max weight **',
                'default'     => 35,
            ];


        }
    }
}
}

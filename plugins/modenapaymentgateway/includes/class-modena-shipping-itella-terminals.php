<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

require_once ('class-modena-log-handler.php');

function init_modena_itella_terminals(): void
{
    class Modena_Shipping_Itella_Terminals extends WC_Shipping_Method {
        protected   string                  $modena_id;
        protected   string                  $modena_secret;
        protected   string                  $shipping_label_url;
        protected   string                  $sender_name;
        protected   string                  $sender_email;
        protected   string                  $sender_phone;
        protected   int                     $terminal_max_weight;
        protected   int                     $shipping_terminal_id;
        protected   string                     $cost;
        protected   int                     $order_weight;
        private     WC_Logger               $shipping_logger;

        public function __construct($instance_id = 0) {
            parent::                      __construct();
            $this->instance_id            = absint($instance_id);
            $this->shipping_terminal_id   = $this->get_selected_shipping_terminal_id();
            $this->terminal_max_weight    = $this->get_terminal_max_weight();

            $this->title                  = $this->get_option( 'title' );
            $this->modena_id              = $this->get_option( 'client-id' );
            $this->modena_secret          = $this->get_option( 'client-secret' );
            $this->cost                   = $this->get_option( 'cost' );
            $this->sender_name            = $this->get_option( 'sender_name' );
            $this->sender_email           = $this->get_option( 'sender_email' );
            $this->sender_phone           = $this->get_option( 'sender_phone' );
            $this->id                     = 'modena_shipping_itella_terminals';
            $this->method_title           = 'Itella terminals by Modena';
            $this->method_description     = 'Modena allows consumers to receive their purchases via Itella terminals';
            $this->supports               = array('shipping-zones', 'instance-settings', 'instance-settings-modal');

            $this->shipping_label_url     = $this->get_shipping_label_url();
            $this->order_weight           = $this->get_total_order_weight();

            $this->shipping_logger        = new WC_Logger(array(new Modena_Log_Handler()));

            $this->shipping_logger->debug('OK. New Modena Shipping Method has been constructed.' );
            add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

        }

        protected function check_package_weight(): bool {
            $orderweight = $this->get_cart_total_weight();
            if($orderweight) {
                if (intval($orderweight) <= $this->get_shipping_method_max_weight()) {
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

        protected function add_shipping_method_select_field( $method, $index ): void {
            add_action( 'woocommerce_after_shipping_rate', 'add_shipping_method_select_field', 10, 2 );

            $options = array(
                'option1' => __( 'Option 1', 'woocommerce' ),
                'option2' => __( 'Option 2', 'woocommerce' ),
                'option3' => __( 'Option 3', 'woocommerce' )
                );

                // Output the select field HTML
                echo '<select name="shipping_method_options[' . $method->get_id() . ']">';
                foreach ( $options as $key => $value ) {
                    echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $value ) . '</option>';
                }
                echo '</select>';
            }

        protected function save_shipping_method_select_field( $item, $package_key, $package, $order ): void
        {
            add_action( 'woocommerce_checkout_create_order_shipping_item', 'save_shipping_method_select_field', 10, 4 );
            $shipping_method_id = $item->get_method_id();
            $shipping_method_option = isset( $_POST['shipping_method_options'][$shipping_method_id] ) ? sanitize_text_field( $_POST['shipping_method_options'][$shipping_method_id] ) : '';

            if ( ! empty( $shipping_method_option ) ) {
                $item->add_meta_data( __( 'Shipping Method Option', 'woocommerce' ), $shipping_method_option, true );
            }
        }

        protected function post_shipping_selection($order) {
            $content            =   'Test package';
            $name               =   WC()->customer->get_billing_first_name(). ' ' . WC()->customer->get_billing_last_name();
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

        // need to be improved upon to add the shipping label url to the order meta data to access it later and add it to woocommerce orders page.

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

        public function init_form_fields() {
            $this->instance_form_fields = array(
                'title'      => array(
                    'title'       => __( 'Title' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default'     => __( 'Itella Shipping', 'woocommerce' ),
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
                ),
                'cost'       => array(
                    'title'             => __( 'Cost' ),
                    'type'              => 'number',
                    'placeholder'       => '',
                    'description'       => 'This controls the cost componenent of the shipping method',
                    'default'           => 0,
                    'desc_tip'          => true,
                    'sanitize_callback' => array( $this, 'sanitize_cost' ),
                ),
                'sender-name'       => array(
                    'title'       => __( 'Sender name' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the parcel sender name that is sent to Itella.'),
                    'default'     => __( ''),
                    'desc_tip'    => true,
                ),
                'sender-email'       => array(
                    'title'       => __( 'Sender email' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the parcel sender email that is sent to Itella.'),
                    'default'     => __( ''),
                    'desc_tip'    => true,
                ),
                'sender-phone'       => array(
                    'title'       => __( 'Sender phone' ),
                    'type'        => 'number',
                    'description' => __( 'This controls the parcel sender phone number that is sent to Itella.'),
                    'default'     => __('' ),
                    'desc_tip'    => true,
                )
            );
        }
    }
}

const SHIPPING_MODULES = [
    'Modena_Shipping_Itella_Terminals' => 'terminals',
];

function add_modena_shipping_modules($methods): array
{
    return array_merge($methods, array_keys(SHIPPING_MODULES));
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    function modena_shipping_init(): void {
        foreach (SHIPPING_MODULES as $className => $fileName) {
            require_once(MODENA_PLUGIN_PATH . 'includes/class-modena-shipping-itella-' . $fileName . '.php');
        }
        add_filter('woocommerce_shipping_methods', 'add_modena_shipping_modules');
    }
}

add_action('woocommerce_shipping_init', 'init_modena_itella_terminals');
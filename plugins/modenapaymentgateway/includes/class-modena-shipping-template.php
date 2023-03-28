<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    function add_custom_shipping_method_class($methods)
    {
        $methods['custom_shipping_method'] = 'WC_Custom_Shipping_Method';
        return $methods;
    }

    add_filter('woocommerce_shipping_methods', 'add_custom_shipping_method_class');

    function init_custom_shipping_method_class()
    {
        if (!class_exists('WC_Custom_Shipping_Method')) {
            class WC_Custom_Shipping_Method extends WC_Shipping_Method
            {
                public function __construct()
                {
                    $this->id = 'custom_shipping_method';
                    $this->method_title = __('Custom Shipping Method', 'woocommerce');
                    $this->method_description = __('A custom shipping method for demonstration purposes.', 'woocommerce');

                    $this->supports = array(
                        'shipping-zones',
                        'instance-settings',
                    );

                    $this->init();
                    $this->init_form_fields();
                }

                public function init_form_fields()
                {
                    $this->form_fields = array(
                        'title' => array(
                            'title' => __('Title', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('The title shown to customers during checkout.', 'woocommerce'),
                            'default' => $this->method_title,
                            'desc_tip' => true,
                        ),
                        'cost' => array(
                            'title' => __('Cost', 'woocommerce'),
                            'type' => 'number',
                            'description' => __('The cost of this shipping method.', 'woocommerce'),
                            'default' => '0',
                            'desc_tip' => true,
                            'placeholder' => '0.00',
                        ),
                    );
                }

                public function init()
                {
                    $this->title = $this->get_option('title');
                    $this->cost = $this->get_option('cost');
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
    }

    add_action('woocommerce_shipping_init', 'init_custom_shipping_method_class');
}
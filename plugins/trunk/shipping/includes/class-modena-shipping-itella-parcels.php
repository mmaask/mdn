<?php

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

class ModenaShippingItellaParcels extends ModenaShippingBase {

  public function __construct($instance_id = 0) {
    $this->id                                    = 'modena-shipping-parcels-itella';
    $this->modena_shipping_type                  = 'parcels';
    $this->modena_shipping_service               = 'Itella';
    $this->title                                 = __('Itella Smartpost');
    $this->method_title                          = __('Modena - Itella Smartpost');
    #$this->cost                                  = 2.99;
    $this->max_weight_for_modena_shipping_method = 35;

    parent::__construct($instance_id);
  }

    public function init_form_fields() {
        parent::init_form_fields();
        $this->form_fields += array(
            'dynamic_pricing' => array(
                'title'       => __('Aktiveeri pakisuuruse põhine hinnastamine', 'modena-for-woocommerce'),
                'type'        => 'checkbox',
                'description' => __('Vastavalt pakimõõtmele hinnastamine', 'modena-for-woocommerce'),
                'default'     => 'yes',
                'desc_tip'    => "no",
            ),
            'xs_cost' => array(
                'title'       => __('XS pakihind', 'modena-for-woocommerce'),
                'type'        => 'number',
                'default'     => '2.59',
            ),
            's_cost' => array(
                'title'       => __('S pakihind', 'modena-for-woocommerce'),
                'type'        => 'number',
                'default'     => '2.99',
            ),
            'm_cost' => array(
                'title'       => __('M pakihind', 'modena-for-woocommerce'),
                'type'        => 'number',
                'default'     => '3.99',
            ),
            'l_cost' => array(
                'title'       => __('L pakihind', 'modena-for-woocommerce'),
                'type'        => 'number',
                'default'     => '4.89',
            ),
            'xl_cost' => array(
                'title'       => __('XL pakihind', 'modena-for-woocommerce'),
                'type'        => 'number',
                'default'     => '6.49',
            ),
        );
    }

    public function calculate_shipping($package = array()) {
        $rate = array(
            'id' => $this->id,
            'label' => $this->title,
        );

        $size = $this->get_package_size($package);  // Assuming you've implemented get_package_size

        if ($size === 'XS') {
            $rate['cost'] = $this->get_option('xs_cost');
        } elseif ($size === 'S') {
            $rate['cost'] = $this->get_option('s_cost');
        }
        // Add conditions for other sizes M, L, XL

        $this->add_rate($rate);
    }

    // Implement a function to determine package size based on dimensions and weight
    public function get_package_size($package) {
        // Your logic here to determine the size (XS, S, M, L, XL) based on package dimensions and weight.
        // Return the calculated size

        return 'XS';
    }
}
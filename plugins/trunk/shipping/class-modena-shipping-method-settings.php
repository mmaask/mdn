<?php

Class Modena_Shipping_Method_Settings {

  public static function init_form_fields($title, $cost) {
    $form_fields = [
       'credentials_title_line' => [
          'type'        => 'title',
          'description' => 'Technical Support: +372 6604144 & info@modena.ee'
       ],
       'environment'            => array(
          'title'       => __('Environment', 'modena'),
          'type'        => 'select',
          'options'     => array(
             'sandbox' => __('Sandbox mode', 'modena'),
             'live'    => __('Live mode', 'modena'),
          ),
          'description' => __('<div id="environment_alert_desc"></div>', 'modena'),
          'default'     => 'sandbox',
          'desc_tip'    => __(
             'Choose Sandbox mode to test payment using test API keys. Switch to live mode to accept payments with Modena using live API keys.', 'modena'),
       ),
       'client_id'              => [
          'title'    => __('Client ID', 'modena'),
          'type'     => 'text',
          'desc_tip' => true,
       ],
       'client_secret'          => [
          'title'    => __('Client Secret', 'modena'),
          'type'     => 'text',
          'desc_tip' => true,
       ],
       'title'          => [
          'title'    => __('Shipping Method Title', 'modena'),
          'type'     => 'text',
          'default' => $title,
          'desc_tip' => true,
       ],
       'cost'                   => [
          'title'             => __('Shipping Method Cost'),
          'type'              => 'float',
          'placeholder'       => '',
          'description'       => 'Shipping Method Cost',
          'default'           => $cost,
          'desc_tip'          => true,
          'sanitize_callback' => 'sanitizeshippingMethodCost',
       ],
       'modena_free_shipping_treshold'                => [
          'title'    => __('Enable or disable free shipping treshold.', 'modena'),
          'type'     => 'checkbox',
          'desc_tip' => 'Enable or disable the shipping method in checkout. Customers will not be able to see the shipping method if disabled.',
          'default'  => 'no',
       ],
       'modena_free_shipping_treshold_sum'                   => [
          'title'             => __('Free shipping treshold'),
          'type'              => 'float',
          'placeholder'       => '',
          'description'       => 'Select amount that this shipping method is free.',
          'default'           => 0,
          'desc_tip'          => true,
          'sanitize_callback' => 'sanitizeshippingMethodCost',
       ],
       'modena_quantity_free_shipping_treshold'                => [
          'title'    => __('Enable or disable quantity free shipping.', 'modena'),
          'type'     => 'checkbox',
          'desc_tip' => 'Enable or disable the shipping method in checkout. Customers will not be able to see the shipping method if disabled.',
          'default'  => 'no',
       ],
       'modena_quantity_free_shipping_treshold_sum'                   => [
          'title'             => __('Quantity based free shipping treshold'),
          'type'              => 'float',
          'placeholder'       => '',
          'description'       => 'Select amount of quantity of product that this shipping method is free.',
          'default'           => 50,
          'desc_tip'          => true,
          'sanitize_callback' => 'sanitizeshippingMethodCost',
       ],
       'modena_package_measurement_checks'                => [
          'title'    => __('Package measurement checks are enabled.', 'modena'),
          'type'     => 'checkbox',
          'desc_tip' => 'Enable or disable the shipping method in checkout. Customers will not be able to see the shipping method if disabled.',
          'default'  => 'no',
       ],
       'modena_package_maximum_weight'                   => [
          'title'             => __('Maximum weight of the shipping package'),
          'type'              => 'float',
          'placeholder'       => '',
          'description'       => 'Select amount of quantity of product that this shipping method is free.',
          'default'           => 35,
          'desc_tip'          => true,
          'sanitize_callback' => 'sanitizeshippingMethodCost',
       ],
       'modena_no_measurement_package'                => [
          'title'    => __('Hide shipping if product has no measurements.', 'modena'),
          'type'     => 'checkbox',
          'desc_tip' => 'Enable or disable the shipping method in checkout. Customers will not be able to see the shipping method if disabled.',
          'default'  => 'no',
       ],
    ];

    return $form_fields;
  }

  public function sanitizeshippingMethodCost($shippingMethodCost): float {
    $sanitizedshippingMethodCost = floatval($shippingMethodCost);
    if ($sanitizedshippingMethodCost < 0) {
      $sanitizedshippingMethodCost = $shippingMethodCost;
    }
    return $sanitizedshippingMethodCost;
  }

}
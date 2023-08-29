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
    $this->cost                                  = 2.99;
    $this->max_weight_for_modena_shipping_method = 35;

    parent::__construct($instance_id);
  }
}
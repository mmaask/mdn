<?php

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

class Modena_Shipping_Courier_DPD extends Modena_Shipping_Courier {

  public function __construct($instance_id = 0) {

    $this->id                                    = 'modena-shipping-courier-dpd';
    $this->modena_shipping_type                  = 'courier';
    $this->modena_shipping_service               = 'dpd';
    $this->title                                 = __('DPD kuller');
    $this->method_title                          = __('Modena - DPD Courier');
    $this->method_description                    = __('DPD Courier');
    $this->cost                                  = 12.99;
    $this->max_weight_for_modena_shipping_method = 35;

  }
}
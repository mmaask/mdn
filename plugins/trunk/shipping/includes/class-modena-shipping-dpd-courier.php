<?php

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

class ModenaShippingDpdCourier extends ModenaShippingBase {

  public function __construct($instance_id = 0) {
    $this->id                                    = 'modena-shipping-courier-dpd';
    $this->modena_shipping_type                  = 'courier';
    $this->modena_shipping_service               = 'DPD';
    $this->title                                 = __('DPD kuller');
    $this->method_title                          = __('Modena - DPD Courier');
    $this->cost                                  = 12.99;
    $this->max_weight_for_modena_shipping_method = 35;

    parent::__construct($instance_id);
  }
}
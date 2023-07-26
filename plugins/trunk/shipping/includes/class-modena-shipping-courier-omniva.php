<?php

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

class Modena_Shipping_Courier_Omniva extends Modena_Shipping_Courier {

  public function __construct($instance_id = 0) {

    $this->id                                    = 'modena-shipping-courier-omniva';
    $this->modena_shipping_type                  = 'courier';
    $this->modena_shipping_service               = 'Omniva';
    $this->title                                 = __('Omniva kuller');
    $this->method_title                          = __('Modena - Omniva Courier');
    $this->cost                                  = 13.99;
    $this->max_weight_for_modena_shipping_method = 35;

    parent::__construct($instance_id);
  }
}
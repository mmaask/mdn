<?php

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

class Modena_Shipping_Courier_Itella extends Modena_Shipping_Courier {

  public function __construct($instance_id = 0) {



    $this->id                                    = 'modena-shipping-courier-itella';
    $this->modena_shipping_type                  = 'courier';
    $this->modena_shipping_service               = 'itella';
    $this->title                                 = __('Itella kuller');
    $this->method_title                          = __('Modena - Itella Courier');
    $this->method_description                    = __('Itella Courier');
    $this->cost                                  = 11.99;
    $this->max_weight_for_modena_shipping_method = 35;


  }
}
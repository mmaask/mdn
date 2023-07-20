<?php

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

class Modena_Shipping_Itella_Smartpost extends Modena_Shipping_Method {

  public function __construct($instance_id = 0) {
    parent::__construct($instance_id);

    $this->id = 'modena-shipping-itella-terminals';
    $this->title = __('Itella Smartpost');
    $this->method_title = __('Modena Itella Smartpost');
    $this->method_description = __('Itella Smartpost parcel terminals');
    $this->cost = 2.99;
    $this->max_weight_for_modena_shipping_method = 35;
  }

  public function get_modena_parcel_terminal_list() {
    return $this->modena_shipping->get_modena_parcel_terminal_list($this->id);
  }

  protected function get_modena_shipping_status() {
    return $this->modena_shipping->get_itella_shipping_api_status();
  }
}
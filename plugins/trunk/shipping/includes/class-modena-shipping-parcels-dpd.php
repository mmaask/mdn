<?php

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

class Modena_Shipping_Parcels_DPD extends Modena_Shipping_Parcels {

  public function __construct($instance_id = 0) {

    $this->id                                    = 'modena-shipping-parcels-dpd';
    $this->modena_shipping_type                  = 'parcels';
    $this->modena_shipping_service               = 'dpd';
    $this->title                                 = __('DPD pakiautomaat');
    $this->method_title                          = __('Modena - DPD Parcel Terminal');
    $this->method_description                    = __('DPD Parcel Terminals');
    $this->cost                                  = 3.99;
    $this->max_weight_for_modena_shipping_method = 31.5;

  }
}
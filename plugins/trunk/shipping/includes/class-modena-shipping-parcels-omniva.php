<?php

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

class Modena_Shipping_Parcels_Omniva extends Modena_Shipping_Parcels {

  public function __construct($instance_id = 0) {

    $this->id                                    = 'modena-shipping-parcels-omniva';
    $this->modena_shipping_type                  = 'parcels';
    $this->modena_shipping_service               = 'omniva';
    $this->title                                 = __('Omniva pakiautomaat');
    $this->method_title                          = __('Modena - Omniva Parcel Terminal');
    $this->method_description                    = __('Omniva Parcel Terminals');
    $this->cost                                  = 4.99;
    $this->max_weight_for_modena_shipping_method = 35;

  }
}
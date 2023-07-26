<?php

use Modena\Payment\Modena;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

abstract class Modena_Shipping_Courier extends Modena_Shipping_Method {
  public function __construct($instance_id = 0) {
    parent::__construct($instance_id);
  }

  public function process_modena_shipping_request($order_id) {
    //try {
    //  $modena_shipping_response = $this->modena_shipping->post_modena_shipping($this->modena_shipping_type, $this->modena_shipping_service, $this->compile_data_for_modena_shipping_request($order_id));
    //} catch (Exception $exception) {
    //  $this->shipping_logger->error('Exception occurred when processing data: ' . $exception->getMessage());
    //  $this->shipping_logger->error($exception->getTraceAsString());
    //}
  }
}
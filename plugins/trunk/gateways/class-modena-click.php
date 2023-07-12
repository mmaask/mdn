<?php
if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

class Modena_Click_Payment extends Modena_Base_Payment {
  public function __construct() {
    $this->id = 'modena_click';
    $this->hide_title = false;
    $this->enabled = $this->get_option('disabled');
    $this->method_title = __('Modena Try Now Pay Later', 'modena');
    $this->initialize_variables_with_translations($this->id);
    parent::__construct();
  }

  public function init_form_fields() {
    parent::init_form_fields();
    $this->form_fields['product_page_banner_enabled'] = [
      'title' => __('Enable Product Page Banner', 'modena'),
      'type' => 'checkbox',
      'description' => '',
      'default' => 'yes',
    ];
  }

  protected function postPaymentOrderInternal($request) {
    return $this->modena->postClickPaymentOrder($request);
  }

  protected function getPaymentApplicationStatus($applicationId) {
    return $this->modena->getClickPaymentApplicationStatus($applicationId);
  }
}
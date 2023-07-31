<?php
if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

class Modena_Settings extends WC_Settings_Page {

  public function __construct() {
    $this->id    = 'modena_shipping_settings';
    $this->label = __('Modena', 'modena-for-woocommerce');

    require_once MODENA_PLUGIN_PATH . 'autoload.php';
    parent::__construct();
  }

  public function get_option($name) {
    return get_option($name);
  }

  public static function create() {
    return new self();
  }

  public function get_settings_for_default_section() {

    $countries     = array('' => '-- Choose country --');
    $countries     = array_merge($countries, (new WC_Countries())->get_countries());
    $orderStatuses = wc_get_order_statuses();

    return array(

       array(
          'title' => __('Technical Support: +372 6604144 & info@modena.ee', 'modena-for-woocommerce'),
          'type'  => 'title',
          'id'    => 'modena_shipping_general'),
       array(
          'title'    => __('Environment', 'modena'),
          'id'       => 'modena_environment',
          'type'     => 'select',
          'class'    => 'wc-enhanced-select',
          'options'  => array(
             'sandbox' => __('Sandbox mode', 'modena'),
             'live'    => __('Live mode', 'modena')),
          'desc'     => __('Get Modena ID and Secret key from <a target="_blank" href="https://partner.modena.ee">Modena Partner Portal</a>.', 'modena-for-woocommerce'),
          'default'  => 'sandbox',
          'desc_tip' => __('Choose Sandbox mode to test payment using test API keys. Switch to live mode to accept payments with Modena using live API keys.', 'modena')),
       array(
          'title'    => __('Sandbox Client ID', 'modena'),
          'id'       => 'modena_sandbox_client_id',
          'type'     => 'text',
          'desc_tip' => true,),
       array(
          'title'    => __('Sandbox Client Secret', 'modena'),
          'id'       => 'modena_sandbox_client_secret',
          'type'     => 'text',
          'desc_tip' => true,),
       array(
          'title'    => __('Live Client ID', 'modena'),
          'id'       => 'modena_live_client_id',
          'type'     => 'text',
          'desc_tip' => true,),
       array(
          'title'    => __('Live Client Secret', 'modena'),
          'id'       => 'modena_live_client_secret',
          'type'     => 'text',
          'desc_tip' => true,),
       array(
          'type' => 'sectionend',
          'id'   => 'modena_shipping_general'),
       array(
          'title' => __("General Settings", 'modena-for-woocommerce'),
          'type'  => 'title',
          'id'    => 'modena_shipping_payment'),
       array(
          'title'   => __('Enable Modena payment gateways', 'modena-for-woocommerce'),
          'label'   => __('Enabled', 'modena-for-woocommerce'),
          'type'    => 'checkbox',
          'default' => 'no',
          'id'      => 'modena_payments_enabled'),
       array(
          'title'   => __('Enable Modena shipping', 'modena-for-woocommerce'),
          'label'   => __('Enabled', 'modena-for-woocommerce'),
          'type'    => 'checkbox',
          'default' => 'no',
          'id'      => 'modena_shipping_enabled'),
       array(
          'type'    => 'select',
          'title'   => __('Order status when shipping label printed', 'modena-for-woocommerce'),
          'class'   => 'wc-enhanced-select',
          'default' => isset($orderStatuses['wc-mon-label-printed']) ? 'wc-mon-label-printed' : 'no-change',
          'desc'    => __('What status should order be changed to in Woocommerce when label is printed?<br>
                    Status will only be changed when order\'s current status is "Processing".',
                          'modena-for-woocommerce'),
          'options' => array_merge(array(
                                      'no-change' => __('-- Do not change status --', 'modena-for-woocommerce')),
                                   $orderStatuses),
          'id'      => 'modena_shipping_orderStatusWhenLabelPrinted'),
       array(
          'type' => 'sectionend',
          'id'   => 'modena_shipping_general'),
       array(
          'title' => __("Sender's information", 'modena-for-woocommerce'),
          'type'  => 'title',
          'id'    => 'modena_shipping_sender_info'),
       array(
          'title'             => __("Sender's phone", 'modena-for-woocommerce'),
          'type'              => 'text',
          'default'           => get_option('woocommerce_store_phone'),
          'custom_attributes' => array('required' => 'required'),
          'id'                => 'modena_shipping_senderPhone'),
       array(
          'title'             => __("Sender's street address", 'modena-for-woocommerce'),
          'type'              => 'text',
          'default'           => get_option('woocommerce_store_address'),
          'custom_attributes' => array('required' => 'required'),
          'id'                => 'modena_shipping_senderStreetAddress1'),
       array(
          'title'   => __("Sender's street address 2", 'modena-for-woocommerce'),
          'type'    => 'text',
          'default' => get_option('woocommerce_store_address_2'),
          'id'      => 'modena_shipping_senderStreetAddress2'),
       array(
          'title'             => __("Sender's city", 'modena-for-woocommerce'),
          'type'              => 'text',
          'default'           => get_option('woocommerce_store_city'),
          'custom_attributes' => array('required' => 'required'),
          'id'                => 'modena_shipping_senderLocality'),
       array(
          'title'             => __("Sender's county", 'modena-for-woocommerce'),
          'type'              => 'text',
          'default'           => get_option('woocommerce_store_county'),
          'custom_attributes' => array('required' => 'required'),
          'id'                => 'modena_shipping_senderRegion'),
       array(
          'title'             => __("Sender's postal code", 'modena-for-woocommerce'),
          'type'              => 'text',
          'default'           => get_option('woocommerce_store_postcode'),
          'custom_attributes' => array('required' => 'required'),
          'id'                => 'modena_shipping_senderPostalCode'),
       array(
          'title'             => __("Sender's country", 'modena-for-woocommerce'),
          'type'              => 'select',
          'options'           => $countries,
          'default'           => get_option('woocommerce_default_country'),
          'custom_attributes' => array('required' => 'required'),
          'id'                => 'modena_shipping_senderCountry'),
       array(
          'type' => 'sectionend',
          'id'   => 'modena_shipping_sender_info'),);
  }


}
<?php
if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}
require_once(MODENA_PLUGIN_PATH . 'includes/class-modena-admin-handler.php');
require_once(MODENA_PLUGIN_PATH . 'includes/class-home-url.php');
require_once(MODENA_PLUGIN_PATH . 'includes/class-modena-translations.php');

class Modena_Init_Handler {
  const PAYMENT_GATEWAYS
     = [
        'Modena_Direct_Payment'   => 'direct',
        'Modena_Slice_Payment'    => 'slice',
        'Modena_Credit_Payment'   => 'credit',
        'Modena_Business_Leasing' => 'business-leasing',
        'Modena_Click_Payment'    => 'click',
     ];

  const SHIPPING_METHODS
     = [
        'Modena_Shipping_Parcels_Omniva'   => 'omniva',
        'Modena_Shipping_Parcels_DPD'    => 'dpd',
        'Modena_Shipping_Parcels_itella'   => 'itella',
     ];

  public function run() {

    new Modena_Admin_Handler();
    add_action('plugins_loaded', [$this, 'maybe_init'], 99);
  }

  public function maybe_init() {

    if (!$this->is_woocommerce_active() || !class_exists('WC_Payment_Gateway')) {
      return;
    }
    $this->init();
  }

  public function is_woocommerce_active() {

    return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))
       || (is_multisite()
          && in_array('woocommerce/woocommerce.php', array_keys(get_site_option('active_sitewide_plugins'))));
  }

  public function init() {

    $this->modena_gateways_init();
    add_action('woocommerce_shipping_init', array($this, 'modena_shipping_init'));
    add_action(
       'woocommerce_single_product_summary', [
       $this,
       'display_product_installments'
    ], apply_filters('modena_product_installments_priority', 15));
    add_filter(
       'woocommerce_available_variation', [
       $this,
       'display_variation_installments'
    ], apply_filters('modena_variation_installments_priority', 10));
  }

  function modena_gateways_init() {

    if (!class_exists('Modena_Base_Payment')) {
      require_once(MODENA_PLUGIN_PATH . 'gateways/class-modena-base.php');
    }
    foreach (self::PAYMENT_GATEWAYS as $className => $fileName) {
      if (!class_exists($className)) {
        require_once(MODENA_PLUGIN_PATH . 'gateways/class-modena-' . $fileName . '.php');
      }
    }
    add_filter('woocommerce_payment_gateways', array($this, 'add_modena_payment_gateways'));
  }

  function add_modena_payment_gateways($methods) {

    return array_merge($methods, array_keys(self::PAYMENT_GATEWAYS));
  }

  function modena_shipping_init() {

    if (!class_exists('Modena_Shipping_Method')) {
      require_once(MODENA_PLUGIN_PATH . '/shipping/includes/class-modena-shipping-method.php');
      require_once(MODENA_PLUGIN_PATH . '/shipping/includes/class-modena-shipping-parcels.php');
    }

    foreach (self::SHIPPING_METHODS as $className => $fileName) {
      if (!class_exists($className)) {
        require_once(MODENA_PLUGIN_PATH . 'shipping/includes/class-modena-shipping-parcels-' . $fileName . '.php');
      }
    }
    add_filter('woocommerce_shipping_methods', array($this, 'add_modena_shipping_methods'));
  }

  function add_modena_shipping_methods($methods) {
    return array_merge($methods, array_keys(self::SHIPPING_METHODS));
  }

  private function is_slice_enabled(): bool {

    $setting = get_option('woocommerce_modena_slice_settings')['enabled'] ?? false;

    return $setting === 'yes';
  }

  private function is_credit_enabled(): bool {

    $setting = get_option('woocommerce_modena_credit_settings')['enabled'] ?? false;

    return $setting === 'yes';
  }

  private function is_leasing_enabled(): bool {

    $setting = get_option('woocommerce_modena_business_leasing_settings')['enabled'] ?? false;

    return $setting === 'yes';
  }

  private function is_click_enabled() {

    $setting = get_option('woocommerce_modena_click_settings')['enabled'] ?? false;

    return $setting === 'yes';
  }

  public function display_product_installments() {

    global $product;

    if ($product->is_type('variable')) {
      return;
    }

    $sale_price = $product->get_price();
    $active_price = apply_filters('advanced_woo_discount_rules_get_product_discount_price', $sale_price, $product);

    echo $this->get_slice_banner_html($active_price);
    echo $this->get_credit_banner_html($active_price);
    echo $this->get_leasing_banner_html($active_price);
    echo $this->get_click_banner_html();
  }

  private function get_slice_banner_html($active_price): string {
    $modena_gateway = 'slice';
    if (!$this->is_slice_enabled() || !$this->is_slice_product_banner_enabled()) {
      return '';
    }

    return $this->get_installment_price_html($modena_gateway, $this->get_slice_banner_text($active_price));
  }

  private function get_credit_banner_html($active_price): string {
    $modena_gateway = 'credit';
    if (!$this->is_credit_enabled() || !$this->is_credit_product_banner_enabled()) {
      return '';
    }

    return $this->get_installment_price_html($modena_gateway, $this->get_credit_banner_text($active_price));
  }

  private function get_leasing_banner_html($active_price): string {
    $modena_gateway = 'leasing';
    if (!$this->is_leasing_enabled() || !$this->is_leasing_product_banner_enabled()) {
      return '';
    }

    return $this->get_installment_price_html($modena_gateway, $this->get_leasing_banner_text($active_price));
  }

  private function get_click_banner_html(): string {
    $modena_gateway = 'click';
    if (!$this->is_click_enabled() || !$this->is_click_product_banner_enabled()) {
      return '';
    }

    return $this->get_installment_price_html($modena_gateway, $this->get_click_banner_text());
  }

  private function is_slice_product_banner_enabled(): bool {

    $setting = get_option('woocommerce_modena_slice_settings')['product_page_banner_enabled'] ?? false;

    return $setting === 'yes';
  }

  private function is_credit_product_banner_enabled(): bool {

    $setting = get_option('woocommerce_modena_credit_settings')['product_page_banner_enabled'] ?? false;

    return $setting === 'yes';
  }

  private function is_leasing_product_banner_enabled(): bool {

    $setting = get_option('woocommerce_modena_business_leasing_settings')['product_page_banner_enabled'] ?? false;

    return $setting === 'yes';
  }

  private function is_click_product_banner_enabled(): bool {

    $setting = get_option('woocommerce_modena_click_settings')['product_page_banner_enabled'] ?? false;

    return $setting === 'yes';
  }

  private function get_slice_banner_text($active_price): string {

    $modena_payment_method_name = 'slice';

    return sprintf(
       __(Modena_Translations::get_modena_banner_text($modena_payment_method_name), "woocommerce"), $this->get_installment_number(
       $active_price / 3.0));
  }

  private function get_credit_banner_text($active_price): string {

    $modena_payment_method_name = 'credit';

    return sprintf(
       __(Modena_Translations::get_modena_banner_text($modena_payment_method_name), "woocommerce"), $this->get_installment_number(
       $active_price * 0.0325));
  }

  private function get_leasing_banner_text($active_price): string {

    $modena_payment_method_name = 'leasing';

    return sprintf(
       __(Modena_Translations::get_modena_banner_text($modena_payment_method_name), "woocommerce"), $this->get_installment_number(
       $active_price * 0.0325));
  }

  private function get_click_banner_text(): string {

    $modena_payment_method_name = 'click';

    return __(Modena_Translations::get_modena_banner_text($modena_payment_method_name), "modena");
  }

  private function get_installment_price_html($modena_gateway, $text): string {

    $icon = '<img src="'
       . WC_HTTPS::force_https_url('https://cdn.modena.ee/modena/assets/modena_logo_black_3f62f63466.png?1863664.3000000007')
       . '" alt="Modena-logo" style="max-height: 16px; margin-bottom: -3px; vertical-align: baseline;"/>';

    return '<p id="modena-' . $modena_gateway . '-product-page-display" style="margin: 0.5rem 0 1.5rem 0;">' . $text . $icon . '</p>';
  }

  private function get_installment_number($number): float {

    return number_format($number, 2, '.', '');
  }

  public function display_variation_installments($variation_data): array {
    $variation_id = $variation_data['variation_id'];
    $variation = wc_get_product($variation_id);

    $sale_price = $variation->get_price();
    $active_price = apply_filters('advanced_woo_discount_rules_get_product_discount_price', $sale_price, $variation);

    if (!$active_price) {
      return $variation_data;
    }

    $sliceBannerHtml = $this->get_slice_banner_html($active_price);
    $creditBannerHtml = $this->get_credit_banner_html($active_price);
    $leasingBannerHtml = $this->get_leasing_banner_html($active_price);
    $clickBannerHtml = $this->get_click_banner_html();

    if ($sliceBannerHtml || $creditBannerHtml) {
      $variation_data['price_html'] .= '<br>';
    }
    if ($sliceBannerHtml) {
      $variation_data['price_html'] .= $sliceBannerHtml;
    }
    if ($creditBannerHtml) {
      $variation_data['price_html'] .= $creditBannerHtml;
    }
    if ($leasingBannerHtml) {
      $variation_data['price_html'] .= $leasingBannerHtml;
    }
    if ($clickBannerHtml) {
      $variation_data['price_html'] .= $clickBannerHtml;
    }

    return $variation_data;
  }

}
<?php
if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

class Modena_Onboarding_Handler {
  protected $logger;

  public function __construct() {

    $this->logger = new WC_Logger(array(new Modena_Log_Handler()));
  }

  /**
   * Maybe received credentials after successfully returned from Modena autoconfigure flow.
   *
   * @return false|void
   */
  public function maybe_received_credentials() {

    if (!is_admin() || !is_user_logged_in()) {
      return false;
    }
    $wc_modena_admin_nonce = isset($_GET['wc_modena_admin_nonce'])
       ? sanitize_text_field($_GET['wc_modena_admin_nonce']) : null;
    // Require the nonce.
    if (empty($wc_modena_admin_nonce)) {
      return false;
    }
    // Verify the nonce.
    if (!wp_verify_nonce($wc_modena_admin_nonce, 'wc_modena')) {
      wp_die(__('Invalid connection request'));
    }
    $gateway_id = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : 'modena_slice';
    if (!empty($_GET['error'])) {
      $error_message = !empty($_GET['error_message']) ? sanitize_text_field($_GET['error_message']) : '';
      $this->logger->error(sprintf('%s: returned back from Autoconfigure flow with error: %s', __METHOD__, $error_message));
      $this->_redirect_with_messages(__('Sorry, Easy Setup encountered an error.  Please try again.'), $gateway_id);
    }
    // Make sure credentials present in query string.
    foreach (array('client_id', 'client_secret', 'environment') as $param) {
      if (empty($_GET[$param])) {
        $this->logger->error(sprintf('%s: returned back from Autoconfigure flow but missing parameter %s', __METHOD__, $param));
        $this->_redirect_with_messages(__('Sorry, Easy Setup encountered an error.  Please try again.'), $gateway_id);
      }
    }
    $messages = array();
    $messages[] = array(
       'success' => __('Success!  Your Modena account has been set up successfully.'),
    );
    // Save credentials to settings API
    $gateways = ['slice', 'credit', 'direct', 'business_leasing'];
    foreach ($gateways as $gateway) {
      $settings_array = (array)get_option(
         'woocommerce_modena_' . $gateway . '_settings', array());
      $environment = isset($_GET['environment'])
         ? sanitize_text_field($_GET['environment']) : 'sandbox';
      $client_id = isset($_GET['client_id'])
         ? sanitize_text_field($_GET['client_id']) : '';
      $client_secret = isset($_GET['client_secret'])
         ? sanitize_text_field($_GET['client_secret']) : '';
      $settings_array[$environment . '_client_id'] = base64_decode($client_id);
      $settings_array[$environment . '_client_secret'] = base64_decode($client_secret);
      update_option('woocommerce_modena_' . $gateway . '_settings', $settings_array);
    }
    $this->_redirect_with_messages($messages, $gateway_id);
  }

  /**
   * Redirect with messages.
   *
   * @param $error_msg
   * @param $gateway_id
   *
   * @return void
   */
  protected function _redirect_with_messages($error_msg, $gateway_id) {

    if (!is_array($error_msg)) {
      $messages = array(array('error' => $error_msg));
    }
    else {
      $messages = $error_msg;
    }
    add_option('woo_pp_admin_error', $messages);
    wp_safe_redirect($this->get_admin_setting_link($gateway_id));
    exit;
  }

  /**
   * Link to settings screen.
   *
   * @param $gateway_id
   *
   * @return string|void
   */
  public function get_admin_setting_link($gateway_id) {

    return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $gateway_id);
  }

  /**
   * @param      $gateway_id
   * @param bool $is_test_mode
   *
   * @return string
   */
  public function get_autoconfig_url($gateway_id, $is_test_mode = true) {

    $query_args = array(
       'redirect'   => urlencode($this->get_redirect_url($gateway_id, $is_test_mode)),
       /* phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode */
       'merchantId' => md5(site_url('/') . time()),
    );

    return add_query_arg($query_args, $this->get_partner_portal_url($is_test_mode));
  }

  /**
   * Get merchant redirect URL for Modena Autoconfigure.
   *
   * This is store URL that will be redirected from middleware.
   *
   * @param $gateway_id
   * @param $is_test_mode
   *
   * @return string Redirect URL
   */
  public function get_redirect_url($gateway_id, $is_test_mode) {

    return add_query_arg(
       array(
          'wc_modena_admin_nonce' => wp_create_nonce('wc_modena'),
       ), $this->get_admin_setting_link($gateway_id) . '&environment=' . ($is_test_mode ? 'sandbox' : 'live'));
  }

  /**
   * Get login URL to Modena Partner Portal.
   *
   * @param $is_test_mode
   *
   * @return string Partner Portal URL
   */
  public function get_partner_portal_url($is_test_mode) {

    return 'https://partner' . ($is_test_mode ? '-dev' : '') . '.modena.ee/developers/apikeys';
  }
}
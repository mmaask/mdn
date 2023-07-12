<?php
if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

class Modena_Direct_Payment extends Modena_Base_Payment {
  protected $service_info;

  public function __construct() {
    $this->id = 'modena_direct';
    $this->enabled = $this->get_option('enabled');
    $this->method_title = __('Modena Direct', 'modena');
    $this->initialize_variables_with_translations($this->id);
    add_filter('woocommerce_get_order_item_totals', array($this, 'customize_payment_method_order_totals'), 10, 3);
    parent::__construct();
  }

  public function get_description() {
    $options = [];
    try {
      $options = $this->modena->getPaymentOptions();
      $sortOrder = array_column($options, 'order');
      array_multisort($sortOrder, SORT_ASC, $options);
    } catch (Exception $e) {
      $this->logger->error(sprintf("Error retrieving payment options: %s", $e->getMessage()));
      $this->logger->error($e->getTraceAsString());
    }
    $ulOptions = '';
    $i = 0;
    foreach ($options as $option) {
      $i++;
      $id = str_replace(' ', '_', strtoupper($option['name']));
      $src = $option['buttonUrl'];
      $value = $option['code'];
      $alt = $option['name'];
      $class = 'mdn_banklink_img';
      if ($i === 1) {
        $class = 'mdn_banklink_img mdn_checked';
      }
      $ulOptions .= sprintf("<li><img id=\"mdn_bl_option_%s\" src=\"%s\" alt=\"%s\" class=\"%s\" onclick=\"selectModenaBanklink('%s', '%s')\"/></li>", $id, $src, $alt, $class, $id, $value);
    }
    $description = '<input type="hidden" id="modena_selected_payment_method" name="modena_selected_payment_method" value="HABAEE2X">';
    $description .= '<ul id="mdn_banklinks_wrapper" class="mdn_banklinks" style="margin: 0 14px 24px 14px !important; list-style-type: none;">'
      . $ulOptions . '</ul>';
    /*
     * Using the singleton pattern we can ensure that the <link> or <script> tags get added to the site only once.
     * */
    Modena_Load_Checkout_Assets::getInstance();

    return "{$description}{$this->getServiceInfoHtml()}";
  }

  private function getServiceInfoHtml() {
    $linkLabel = $this->service_info;

    return "<a class='mdn_service_info' href='https://modena.ee/makseteenused/' target='_blank'>{$linkLabel}</a>";
  }

  public function get_icon_alt() {
    return $this->default_alt;
  }

  public function get_icon_title() {
    return $this->default_icon_title_text;
  }

  protected function postPaymentOrderInternal($request) {
    return $this->modena->postDirectPaymentOrder($request);
  }

  protected function getPaymentApplicationStatus($applicationId) {
    return $this->modena->getDirectPaymentApplicationStatus($applicationId);
  }

  function customize_payment_method_order_totals($total_rows, $order, $tax_display) {
    foreach ($total_rows as $key => $total) {
      if ($key == 'payment_method') {
        $payment_method_id = $order->get_payment_method();
        if ($payment_method_id === 'modena_direct') {
          $total_rows[$key]['value'] = $total['value'] . ' (' . $order->get_meta(self::MODENA_SELECTED_METHOD_KEY) . ')';
        }
      }
    }

    return $total_rows;
  }
}
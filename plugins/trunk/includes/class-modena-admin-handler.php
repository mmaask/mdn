<?php

class Modena_Admin_Handler {
  private const KEY_COLUMN_PAYMENT_METHOD    = 'modena_payment_method';
  private const KEY_COLUMN_MC_PAYMENT_METHOD = 'makecommerce_payment_method';

  public function __construct() {

    add_filter('manage_edit-shop_order_columns', [$this, 'add_payment_method_column']);
    add_action('manage_shop_order_posts_custom_column', [$this, 'populate_payment_method_column'], 10, 2);
  }

  public function add_payment_method_column($columns) {

    if (isset($columns[self::KEY_COLUMN_MC_PAYMENT_METHOD])) {
      return $columns;
    }
    $columns[self::KEY_COLUMN_PAYMENT_METHOD] = __('Payment Method', 'modena');

    return $columns;
  }

  public function populate_payment_method_column($columnName, $postId) {

    if ($columnName !== self::KEY_COLUMN_PAYMENT_METHOD && $columnName !== self::KEY_COLUMN_MC_PAYMENT_METHOD) {
      return;
    }
    echo get_post_meta($postId, Modena_Base_Payment::MODENA_SELECTED_METHOD_KEY, true);
  }
}
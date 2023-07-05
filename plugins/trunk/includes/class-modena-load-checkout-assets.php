<?php

class Modena_Load_Checkout_Assets {
  private static $instance = null;

  private function __construct() {

    $this->renderAssets();
  }

  private function renderAssets() {

    wp_enqueue_style('modena_frontend_style', MODENA_PLUGIN_URL . '/assets/css/modena-checkout.css');
    wp_enqueue_script('modena_frontend_script', MODENA_PLUGIN_URL . '/assets/js/modena-checkout.js');
  }

  public static function getInstance(): Modena_Load_Checkout_Assets {

    if (self::$instance === null) {
      self::$instance = new Modena_Load_Checkout_Assets();
    }

    return self::$instance;
  }
}
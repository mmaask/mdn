<?php

class Modena_Load_Checkout_Assets {
  private static $instance = null;

  private function __construct() {

    $this->renderAssets();
    $this->enqueue_modena_shipping_assets();
  }

  private function renderAssets() {

    wp_enqueue_style('modena_frontend_style', MODENA_PLUGIN_URL . '/assets/css/modena-checkout.css');
    wp_enqueue_script('modena_frontend_script', MODENA_PLUGIN_URL . '/assets/js/modena-checkout.js');
    wp_enqueue_script('modena_setting_script', MODENA_PLUGIN_URL . '/assets/js/modena-settings.js');
  }

  public static function getInstance(): Modena_Load_Checkout_Assets {

    if (self::$instance === null) {
      self::$instance = new Modena_Load_Checkout_Assets();
    }

    return self::$instance;
  }

  public function enqueue_modena_shipping_assets() {
    if (!wp_script_is('jquery')) {
      wp_enqueue_script('jquery');
    }

    wp_enqueue_style('modena_shipping_style', MODENA_PLUGIN_URL . '/assets/css/modena-shipping.css');
    wp_enqueue_script('modena_shipping_script', MODENA_PLUGIN_URL . '/assets/js/modena-shipping.js', array('jquery'), '6.2', true);

    $translations = array(
       'please_choose_parcel_terminal' => __($this->getParcelTerminalDefaultTextTranslation(), 'modena')

    );

    wp_localize_script('modena_shipping_script', 'mdnTranslations', $translations);

    wp_register_style('select2', MODENA_PLUGIN_URL . '/assets/select2/select2.min.css');
    wp_register_script('select2', MODENA_PLUGIN_URL .'/assets/select2/select2.min.js', array('jquery'), true);

    wp_enqueue_style('select2');
    wp_enqueue_script('select2');

  }

  public function getParcelTerminalDefaultTextTranslation(): string {

    switch (get_locale()) {
      case 'en_GB' && 'en_US':
        return 'Select parcel terminal';
      case 'ru_RU':
        return 'Список почтовых терминалов';
      default:
        return 'Vali pakipunkt';
    }
  }
}
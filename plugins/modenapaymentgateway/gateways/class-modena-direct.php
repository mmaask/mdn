<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

 class Modena_Direct_Payment extends Modena_Base_Payment {
     public function __construct() {
         $this->id      = 'modena_direct';
         $this->enabled = $this->get_option('enabled');
         $this->maturity_in_months = 0;
         $this->default_alt             = '';

         $this->setNamesBasedOnLocales(get_locale());

         parent::__construct();
     }

     public function setNamesBasedOnLocales($current_locale) {
         switch ($current_locale) {
             case 'ru':
                 $this->method_title       = 'Modena банковские платежи и платежи картами';
                 $this->method_description = __('Банковские платежи / платежи картами', 'modena');
                 $this->title = "Интернетбанк или карта";
                 $this->default_image = 'https://cdn.modena.ee/modena/assets/modena_woocommerce_direct_01511526fd.png?47448.59999999963';
                 $this->default_icon_title_text = 'Платежные услуги предоставляются Modena Payments OÜ в сотрудничестве с EveryPay AS.';
                 break;
             case 'gb' && 'us':
                 $this->method_title       = 'Modena Bank & Card Payments';
                 $this->method_description = __('Bank payments / card payments', 'modena');
                 $this->title = "Bank & Card Payments";
                 $this->default_image = 'https://cdn.modena.ee/modena/assets/modena_woocommerce_direct_01511526fd.png?47448.59999999963';
                 $this->default_icon_title_text = 'Payment services are provided by Modena Payments OÜ in cooperation with EveryPay AS';
                 break;
             default:
                 $this->method_title       = 'Modena pangamaksed ja kaardimaksed';
                 $this->method_description = __('Pangamaksed / kaardimaksed', 'modena');
                 $this->title = "Panga- ja kaardimaksed";
                 $this->default_image = 'https://cdn.modena.ee/modena/assets/modena_woocommerce_direct_01511526fd.png?47448.59999999963';
                 $this->default_icon_title_text = 'Makseteenuseid pakub Modena Payments OÜ koostöös EveryPay AS-iga.';
                 break;
         }
     }

    public function get_description()
    {
        $options = [];

        try {
            $options   = $this->modena->getPaymentOptions();
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
            $id    = str_replace(' ', '_', strtoupper($option['name']));
            $src   = $option['buttonUrl'];
            $value = $option['code'];
            $alt   = $option['name'];
            $class = 'mdn_banklink_img';

            if ($i === 1) {
                $class = 'mdn_banklink_img mdn_checked';
            }

            $ulOptions .= sprintf(
                "<li><img id=\"mdn_bl_option_%s\" src=\"%s\" alt=\"%s\" class=\"%s\" onclick=\"selectModenaBanklink('%s', '%s')\"/></li>",
                $id, $src, $alt, $class, $id, $value
            );
        }

        $description = '<input type="hidden" id="mdn_selected_banklink" name="MDN_OPTION" value="HABAEE2X">';
        $description .= '<ul id="mdn_banklinks_wrapper" class="mdn_banklinks" style="margin: 0 14px 24px 14px !important; list-style-type: none;">' . $ulOptions . '</ul>';

        /*
         * Using the singleton pattern we can ensure that the <link> or <script> tags get added to the site only once.
         *
         * */

        Modena_Load_Checkout_Assets::getInstance();

        return "{$description}{$this->getServiceInfoHtml()}";
    }

    private function getServiceInfoHtml()
    {
        $linkLabel = $this->getServiceInfoText();
        return "<a class='mdn_service_info' href='https://modena.ee/makseteenused/' target='_blank'>{$linkLabel}</a>";
    }
     protected function postPaymentOrderInternal($request) {
         return $this->modena->postDirectPaymentOrder($request);
     }

     protected function getServiceInfoText() {
         switch (get_locale()) {
            case 'en_US' && 'en_GB':
                return 'Service info';
            case 'et' && 'et_EE':
                return 'Teenuse info';
            case 'ru_RU':
                return 'Сведения о сервисе';
        }
 }

}
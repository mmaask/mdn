<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

 class Modena_Direct_Payment extends Modena_Base_Payment {

    protected $service_info;

    public function __construct() {
         $this->id      = 'modena_direct';
         $this->enabled = $this->get_option('disabled');
         $this->maturity_in_months = 0;
         $this->default_alt = '';
        $this->setNamesBasedOnLocales(get_locale());

         parent::__construct();
     }

     public function setNamesBasedOnLocales($current_locale)
     {
         $translations = array(
             'en' => array(
                 'method_title' => __('Modena - Bank & Card Payments', 'mdn-translations'),
                 'default_alt' => __('Modena Bank & Card Payments', 'mdn-translations'),
                 'method_description' => __('Bank payments / card payments', 'mdn-translations'),
                 'title' => __('Bank & Card Payments', 'mdn-translations'),
                 'default_image' => 'https://cdn.modena.ee/modena/assets/modena_woocommerce_direct_01511526fd.png?47448.59999999963',
                 'default_icon_title_text' => __('Modena Bank Payments is provided by Modena Estonia OÜ.', 'mdn-translations'),
                 'description' => __('Payment services are provided by Modena Payments OÜ in cooperation with EveryPay AS', 'mdn-translations'),
                 'service_info' => __('Service info'),
             ),
             'ru' => array(
                 'method_title' => __('Modena - Банковские платежи / платежи картами', 'mdn-translations'),
                 'default_alt' => __('Банковские платежи / платежи картами', 'mdn-translations'),
                 'method_description' => __('Банковские платежи / платежи картами', 'modena'),
                 'title' => __('Интернетбанк или карта', 'mdn-translations'),
                 'default_image' => 'https://cdn.modena.ee/modena/assets/modena_woocommerce_direct_01511526fd.png?47448.59999999963',
                 'default_icon_title_text' => __('Платежные услуги предоставляются Modena Payments OÜ в сотрудничестве с EveryPay AS.', 'mdn-translations'),
                 'description' => 'Платежные услуги предоставляются Modena Payments OÜ в сотрудничестве с EveryPay AS.',
                 'service_info' => __('Сведения об услуге'),
             ),
             'et' => array(
                 'method_title' => __('Modena - Panga- ja kaardimaksed', 'mdn-translations'),
                 'default_alt' => __('Modena panga- ja kaardimaksed', 'mdn-translations'),
                 'method_description' => __('Kiired pangamaksed ja kaardimaksed Eestis', 'modena'),
                 'title' => __('Panga- ja kaardimaksed', 'mdn-translations'),
                 'default_image' => 'https://cdn.modena.ee/modena/assets/modena_woocommerce_direct_01511526fd.png?47448.59999999963',
                 'default_icon_title_text' => __('Makseteenuseid pakub Modena Payments OÜ koostöös EveryPay AS-iga.', 'mdn-translations'),
                 'description' => 'Makseteenuseid pakub Modena Payments OÜ koostöös EveryPay AS-iga.',
                 'service_info' => __('Teenuse info'),
             ),
         );
         // Set the locale key based on the current locale
         $locale_key = 'en';

         switch ($current_locale) {
             case 'ru_RU':
                 $locale_key = 'ru';
                 break;
             case 'en_GB':
             case 'en_US':
                 break;
             default:
                 $locale_key = 'et';
                 break;
         }

         foreach ($translations[$locale_key] as $key => $value) {
             $this->{$key} = $value;
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

        return "$description{$this->getServiceInfoHtml()}";
    }

    private function getServiceInfoHtml()
    {
        $linkLabel = $this->service_info;
        return "<a class='mdn_service_info' href='https://modena.ee/makseteenused/' target='_blank'>$linkLabel</a>";
    }
     protected function postPaymentOrderInternal($request) {
         return $this->modena->postDirectPaymentOrder($request);
     }


}
<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

 class Modena_Direct_Payment extends Modena_Base_Payment
{
    protected $service_info;

    public function __construct()
    {
        $this->id                                   = 'modena_direct';
        $this->maturity_in_months                   = 0;
        $this->enabled                              = $this->get_option('enabled');
        $this->method_title                         = __('Modena Direct', 'modena');
        $this->default_alt                          = __(' ', 'modena');
        $this->default_image                        = __('https://cdn.modena.ee/modena/assets/modena_woocommerce_direct_01511526fd.png', 'modena');

        $this->initialize_variables_with_translations();

        parent::__construct();
    }

    public function initialize_variables_with_translations() {
        $translations = array(
            'et' => array(
                'service_info'                      => __('Teenuse info', 'modena'),
                'title'                             => __('Panga- ja kaardimaksed', 'modena'),
                'default_icon_title_text'           => __('Panga- ja kaardimakseid pakub Modena Payments OÜ koostöös EveryPay AS-iga.', 'modena'),
            ),
            'ru_RU' => array(
                'service_info'                      => __('Сервисная информация', 'modena'),
                'title'                             => __('Интернетбанк или карта', 'modena'),
                'default_icon_title_text'           => __('Платежные услуги предоставляются Modena Payments OÜ в сотрудничестве с EveryPay AS.', 'modena'),
            ),
            'en_US' => array(
                'service_info'                      => __('Service Info', 'modena'),
                'title'                             => __('Bank & Card Payments', 'modena'),
                'default_icon_title_text'           => __('Modena Bank Payments is provided by Modena Estonia OÜ.', 'modena'),
            ),
        );

        $this->service_info                         = $translations[get_locale()]['service_info'] ?? $translations['en_US']['service_info'];
        $this->title                                = $translations[get_locale()]['title'] ?? $translations['en_US']['title'];
        $this->default_icon_title_text              = $translations[get_locale()]['default_icon_title_text'] ?? $translations['en_US']['default_icon_title_text'];
        $this->method_description                   = $this->title;
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
         * */
        Modena_Load_Checkout_Assets::getInstance();

        return "{$description}{$this->getServiceInfoHtml()}";
    }

    private function getServiceInfoHtml()
    {
        $linkLabel = $this->service_info;

        return "<a class='mdn_service_info' href='https://modena.ee/makseteenused/' target='_blank'>{$linkLabel}</a>";
    }

    public function get_icon_alt()
    {
        return $this->default_alt;
    }

    public function get_icon_title()
    {
        return $this->default_icon_title_text;
    }

     protected function postPaymentOrderInternal($request) {
         return $this->modena->postDirectPaymentOrder($request);
     }

     protected function getPaymentApplicationStatus($applicationId)
     {
         return $this->modena->getDirectPaymentApplicationStatus($applicationId);
     }
 }
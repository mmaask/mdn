<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Business_Leasing extends Modena_Base_Payment
{
    public function __construct()
    {
        $this->id                                           = 'modena_business_leasing';
        $this->maturity_in_months                           = 36;
        $this->hide_title                                   = false;
        $this->enabled                                      = $this->get_option('disabled');
        $this->title                                        = __('Modena - Ärikliendi järelmaks', 'modena');
        $this->method_title                                 = __('Modena Business Leasing', 'modena');

        $this->initialize_variables_with_translations();

        parent::__construct();
    }

    public function initialize_variables_with_translations() {

        $translations = array(
            'et' => array(
                'default_image'                             => __('https://cdn.modena.ee/modena/assets/modena_woocommerce_business_credit_62c8f2fa76.png', 'modena'),
                'default_alt'                               => __('Modena - Ärikliendi järelmaks kuni 48 kuud', 'modena'),
                'default_icon_title_text'                   => __('Modena ärikliendi järelmaksu võimaldab Modena Estonia OÜ.', 'modena'),
                'description'                               => __('Vormista järelmaks ettevõtte nimele. Tasu ostu eest 6-48 kuu jooksul.','modena'),
            ),
            'ru_RU' => array(
                'default_image'                             => __('https://cdn.modena.ee/modena/assets/modena_woocommerce_business_credit_rus_f84c520f4a.png', 'modena'),
                'default_alt'                               => __('Modena - бизнес лизинг до 48 месяцев', 'modena'),
                'default_icon_title_text'                   => __('Модена рассрочки для бизнеса предоставляется Modena Estonia OÜ.', 'modena'),
                'description'                               => __('Опция рассрочки для бизнеса. Оплачивайте за свою покупку частями в течение 6 - 48 месяцев.', 'modena'),
            ),
            'en_US' => array(
                'default_image'                             => __('https://cdn.modena.ee/modena/assets/modena_woocommerce_business_credit_eng_0f8d5b1a5a.png', 'modena'),
                'default_alt'                               => __('Modena - Leasing up to 48 months', 'modena'),
                'default_icon_title_text'                   => __('Modena Leasing is provided by Modena Estonia OÜ.', 'modena'),
                'description'                               => __('Installment payment option for businesses. Pay for your purchase in parts over 6 - 48 months.', 'modena'),
            ),
        );

        $this->default_image                                = $translations[get_locale()]['default_image'] ?? $translations['en_US']['default_image'];
        $this->default_alt                                  = $translations[get_locale()]['default_alt'] ?? $translations['en_US']['default_image'];
        $this->description                                  = $translations[get_locale()]['description'] ?? $translations['en_US']['description'];
        $this->default_icon_title_text                      = $translations[get_locale()]['default_icon_title_text'] ?? $translations['en_US']['default_icon_title_text'];
        $this->method_description                           = $this->description;
    }

    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['product_page_banner_enabled'] = [
            'title'       => __('Enable/Disable Product Page Banner', 'modena'),
            'label'       => '<span class="modena-slider"></span>',
            'type'        => 'checkbox',
            'description' => '',
            'default'     => 'yes',
            'class'       => 'modena-switch',
        ];
    }

    protected function postPaymentOrderInternal($request) {
        return $this->modena->postBusinessLeasingPaymentOrder($request);
    }

    protected function getPaymentApplicationStatus($applicationId)
    {
        return $this->modena->getBusinessLeasingPaymentApplicationStatus($applicationId);
    }
}
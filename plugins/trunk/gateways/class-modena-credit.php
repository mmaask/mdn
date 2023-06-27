<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Credit_Payment extends Modena_Base_Payment
{
    public function __construct()
    {
        $this->id                                           = 'modena_credit';
        $this->maturity_in_months                           = 36;
        $this->hide_title                                   = false;
        $this->enabled                                      = $this->get_option('enabled');
        $this->title                                        = __('Modena - Järelmaks', 'modena');
        $this->method_title                                 = __('Modena Credit', 'modena');

        $this->initialize_variables_with_translations();

        parent::__construct();
    }

    public function initialize_variables_with_translations() {

        $translations = array(
            'et' => array(
                'default_image'                             => __('https://cdn.modena.ee/modena/assets/modena_woocommerce_credit_alt_ee69576caf.png', 'modena'),
                'default_alt'                               => __('Modena - Järelmaks kuni 48 kuud', 'modena'),
                'default_icon_title_text'                   => __('Modena järelmaksu võimaldab Modena Estonia OÜ.', 'modena'),
                'description'                               => __('0€ sissemakse, 0€ haldustasu, 0€ lepingutasu. Hajuta mugavalt maksed 6-48 kuu peale.','modena'),
            ),
            'ru_RU' => array(
                'default_image'                             => __('https://cdn.modena.ee/modena/assets/modena_woocommerce_credit_rus_79ecd31ab6.png', 'modena'),
                'default_alt'                               => __('Modena - Рассрочка до 48 месяцев', 'modena'),
                'default_icon_title_text'                   => __('Модена 3 платежа предоставляется Modena Estonia OÜ.', 'modena'),
                'description'                               => __('Рассрочка до 48 месяцев. 0€ первоначальный взнос, 0€ плата за управление договором, 0€ плата за договор. Удобно распределите свои платежи на период от 6 до 48 месяцев.', 'modena'),
            ),
            'en_US' => array(
                'default_image'                             => __('https://cdn.modena.ee/modena/assets/modena_woocommerce_credit_eng_6a1c94d9a2.png', 'modena'),
                'default_alt'                               => __('Modena Credit up to 48 months', 'modena'),
                'default_icon_title_text'                   => __('Modena Credit is provided by Modena Estonia OÜ.', 'modena'),
                'description'                               => __('0€ down payment, 0€ administration fee, 0€ contract fee. Spread your payments conveniently over 6-48 months.', 'modena'),
            ),
        );

        $this->default_image                                = $translations[get_locale()]['default_image'] ?? $translations['en_US']['default_image'];
        $this->default_alt                                  = $translations[get_locale()]['default_alt'] ?? $translations['en_US']['default_image'];
        $this->description                                  = $translations[get_locale()]['description'] ?? $translations['en_US']['description'];
        $this->default_icon_title_text                      = $translations[get_locale()]['default_icon_title_text'] ?? $translations['en_US']['default_icon_title_text'];
        $this->method_description                           = $this->description;
        $this->title                                        = $this->default_alt;
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
        return $this->modena->postCreditPaymentOrder($request);
    }

    protected function getPaymentApplicationStatus($applicationId)
    {
        return $this->modena->getCreditPaymentApplicationStatus($applicationId);
    }
}
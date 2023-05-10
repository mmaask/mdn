<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Leasing extends Modena_Base_Payment
{
    public function __construct()
    {
        $this->id         = 'modena_leasing';
        $this->hide_title = true;
        $this->enabled    = $this->get_option('disabled');
        $this->maturity_in_months = 36;

        $this->setNamesBasedOnLocales(get_locale());

        parent::__construct();
    }

    public function setNamesBasedOnLocales($current_locale) {
        switch ($current_locale) {
            case 'en_GB' && 'en_US':
                $this->method_title             = 'Modena Business Leasing';
                $this->default_alt              = 'Modena - Leasing up to 48 months';
                $this->method_description       = __('Arrange installment plan for the company name. Pay for the purchase over 6-48 months.', 'modena');
                $this->title                    = 'Modena Leasing';
                $this->default_image            = 'https://cdn.modena.ee/modena/assets/modena_woocommerce_business_credit_62c8f2fa76.png?81164.69999998808';
                $this->default_icon_title_text  = 'Modena Leasing is provided by Modena Estonia OÜ.';
                break;
            case 'ru_RU':
                $this->method_title             = 'Modena бизнес лизинг';
                $this->title                    = 'Бизнес лизинг';
                $this->default_alt              = 'Modena - Рассрочка до 48 месяцев';
                $this->method_description       = __('Оформите рассрочку на имя компании. Оплатите покупку в течение 6-48 месяцев.', 'modena');
                $this->default_image            = 'https://cdn.modena.ee/modena/assets/modena_woocommerce_business_credit_62c8f2fa76.png?81164.69999998808';
                $this->default_icon_title_text  = 'Модена рассрочка предоставляется Modena Estonia OÜ.';
                break;
            default:
                $this->title                    = __('Modena ärikliendi järelmaks', 'modena');
                $this->method_title             = 'Modena Ärijärelemaks';
                $this->default_alt              = 'Vormista järelmaks ettevõtte nimele. Tasu ostu eest 6-48 kuu jooksul.';
                $this->method_description       = __('Vormista järelmaks ettevõtte nimele. Tasu ostu eest 6-48 kuu jooksul.', 'modena');
                $this->default_image            = 'https://cdn.modena.ee/modena/assets/modena_woocommerce_business_credit_62c8f2fa76.png?81164.69999998808';
                $this->default_icon_title_text = 'Vormista järelmaks ettevõtte nimele. Tasu ostu eest 6-48 kuu jooksul.';
                break;
        }
    }

    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['description'] = [
            'title'       => __('Payment Button Description', 'modena'),
            'type'        => 'textarea',
            'description' => __('This controls the description which the user sees during checkout.', 'modena'),
            //'default'     => __('Ettevõtetele mõeldud järelmaks. Tasu ostu eest osadena 6 - 48 kuu jooksul.', 'modena'),

            'default'     => __('leasingPaymentGatewayDescriptionText', 'mdn-translations'),
            'css'         => 'width:25em',
            'desc_tip'    => true,
        ];

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
}
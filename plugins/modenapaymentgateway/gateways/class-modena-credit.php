<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Credit_Payment extends Modena_Base_Payment
{
    public function __construct()
    {
        $this->id         = 'modena_credit';
        $this->hide_title = true;
        $this->enabled    = $this->get_option('disabled');
        $this->maturity_in_months = 36;

        $this->setNamesBasedOnLocales(get_locale());

        parent::__construct();
    }

    public function setNamesBasedOnLocales($current_locale) {
        switch ($current_locale) {
            case 'en_GB' && 'en_US':
                $this->method_title             = 'Modena Credit Payments';
                $this->default_alt              = 'Modena - Credit up to 48 months';
                $this->method_description       = __('0€ down payment, 0€ administration fee, 0€ contract fee. Spread your payments conveniently over 6-48 months.','modena');
                $this->title                    = 'Modena Credit';
                $this->default_image            = 'https://cdn.modena.ee/modena/assets/modena_woocommerce_credit_alt_ee69576caf.png?15591391.9';
                $this->default_icon_title_text  = 'Modena Credit is provided by Modena Estonia OÜ.';
                break;
            case 'ru_RU':
                $this->method_title             = 'Modena рассрочка';
                $this->default_alt              = 'Modena - Рассрочка до 48 месяцев';
                $this->method_description       = __('','modena');
                $this->title                    = 'Modena рассрочка';
                $this->default_image            = 'https://cdn.modena.ee/modena/assets/modena_woocommerce_credit_alt_ee69576caf.png?15591391.9';
                $this->default_icon_title_text  = 'Модена рассрочка предоставляется Modena Estonia OÜ.';
                break;
            default:
                $this->method_title             = 'Modena järelmaks';
                $this->default_alt              = 'Modena - Järelmaks kuni 48 kuud';
                $this->method_description       = __('0€ sissemakse, 0€ haldustasu, 0€ lepingutasu. Hajuta mugavalt maksed 6-48 kuu peale.','modena');
                $this->title                    = 'Modena järelmaks';
                $this->default_image            = 'https://cdn.modena.ee/modena/assets/modena_woocommerce_credit_alt_ee69576caf.png?15591391.9';
                $this->default_icon_title_text  = 'Modena järelmaksu võimaldab Modena Estonia OÜ.';
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
            //'default'     => __('0€ sissemakse, 0€ haldustasu, 0€ lepingutasu. Hajuta mugavalt maksed 6-48 kuu peale.', 'modena'),
            'default'     => __('creditPaymentGatewayDescriptionText', 'mdn-translations'),
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
        return $this->modena->postCreditPaymentOrder($request);
    }
}
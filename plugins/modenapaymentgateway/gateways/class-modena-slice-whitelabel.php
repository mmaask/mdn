<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Slice_Payment_Whitelabel extends Modena_Base_Payment
{
    public function __construct()
    {
        $this->id         = 'modena_slice_whitelabel';
        $this->hide_title = false;
        $this->enabled    = $this->get_option('disabled');
        $this->maturity_in_months = 3;

        $this->setNamesBasedOnLocales(get_locale());

        parent::__construct();
    }

    public function setNamesBasedOnLocales($current_locale) {
        switch ($current_locale) {
            case 'en_GB' && 'en_US':
                $this->method_title             = 'Modena Whitelabel - Pay in 3';
                $this->default_alt              = 'Modena - Installments up to 48 months';
                $this->method_description       = __('0€ down payment, 0% interest, 0€ extra charge. Simply pay later.','modena');
                $this->title                    = 'Buy now Pay Later';
                $this->default_icon_title_text  = 'Modena installments is provided by Modena Estonia OÜ.';
                break;
            case 'ru_RU':
                $this->method_title             = 'Modena рассрочка';
                $this->default_alt              = 'Modena - Рассрочка до 48 месяцев';
                $this->method_description       = __('0€ первоначальный взнос, 0% процент, 0€ дополнительная плата. Просто платите позже.','modena');
                $this->title                    = 'Modena рассрочка';
                $this->default_icon_title_text  = 'Модена рассрочка предоставляется Modena Estonia OÜ.';
                break;
            default:
                $this->method_title             = 'Modena järelmaks';
                $this->default_alt              = 'Maksa 3 osas, 0€ lisatasu';
                $this->method_description       = __('0€ sissemakse, 0% intress, 0€ lisatasu. Lihtsalt maksa hiljem.', 'modena');
                $this->title                    = 'Maksa 3 osas';
                $this->default_icon_title_text  = 'Osamakseid võimaldab Modena Estonia OÜ.';
                break;
        }
    }

    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['description']                 = [
            'title'       => __('Payment Button Description', 'modena'),
            'type'        => 'textarea',
            'description' => __('This controls the description which the user sees during checkout.', 'modena'),
            //'default'     => __('0€ sissemakse, 0% intress, 0€ lisatasu. Lihtsalt maksa hiljem.', 'modena'),
            'default'     => __('faktoringWhiteLabelPaymentGatewayDescriptionText', 'mdn-translations'),
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
        return $this->modena->postSliceWhiteLabelPaymentOrder($request);
    }
}
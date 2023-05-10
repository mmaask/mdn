<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Slice_Payment extends Modena_Base_Payment
{
    public function __construct() {
        $this->id         = 'modena_slice';
        $this->hide_title = true;
        $this->enabled    = $this->get_option('enabled');
        $this->maturity_in_months = 3;

        $this->setNamesBasedOnLocales(get_locale());

        parent::__construct();
    }

    public function setNamesBasedOnLocales($current_locale) {
        switch ($current_locale) {
            case 'us' && 'gb':
                $this->method_title             = 'Modena Pay in 3';
                $this->title                    = 'Modena Pay Later';
                $this->method_description       = __('0€ down payment, 0% interest, 0€ extra charge. Simply pay later.','modena');
                $this->default_alt              = 'Modena - Installments up to 48 months';
                $this->default_image            = 'https://cdn.modena.ee/modena/assets/modena_woocommerce_slice_alt_2dacff6e81.png?15560016.3';
                $this->default_icon_title_text  = 'Modena installments is provided by Modena Estonia OÜ.';
                break;
            case 'ru':
                $this->method_title             = 'Modena рассрочка';
                $this->default_alt              = 'Modena - Рассрочка до 48 месяцев';
                $this->method_description       = __('0€ первоначальный взнос, 0% процент, 0€ дополнительная плата. Просто платите позже.','modena');
                $this->title                    = 'Modena рассрочка';
                $this->default_image            = 'https://cdn.modena.ee/modena/assets/modena_woocommerce_slice_alt_2dacff6e81.png?15560016.3';
                $this->default_icon_title_text  = 'Модена рассрочка предоставляется Modena Estonia OÜ.';
                break;
            default:
                $this->method_title             = 'Modena järelmaks';
                $this->default_alt              = 'Maksa 3 osas, 0€ lisatasu';
                $this->method_description       = __('Maksa 3 osas, 0€ lisatasu', 'modena');
                $this->title                    = 'Maksa 3 osas';
                $this->default_image            = 'https://cdn.modena.ee/modena/assets/modena_woocommerce_slice_alt_2dacff6e81.png?15560016.3';
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
            'default'     => __($this->getDefaultPaymentButtonDescription(), 'modena'),

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
        return $this->modena->postSlicePaymentOrder($request);
    }

    protected function getDefaultPaymentButtonDescription() {
        switch (get_locale()) {
            case 'en_US' && 'en_GB':
                return '0€ down payment, 0% interest, 0€ extra charge. Simply pay later.';
            case 'et':
                return '0€ sissemakse, 0% intress, 0€ lisatasu. Lihtsalt maksa hiljem.';
            case 'ru_RU':
                return '0€ первоначальный взнос, 0% процент, 0€ дополнительная плата. Просто платите позже.';
        }
    }
}
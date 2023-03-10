<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Business_Credit_Payment extends Modena_Base_Payment
{
    public function __construct()
    {
        $this->id         = 'modena_business_credit';
        $this->title   = __('Ärikliendi järelmaks', 'modena');
        $this->hide_title = true;
        $this->enabled    = $this->get_option('disabled');

        $this->method_title       = 'Modena Business Credit';
        $this->method_description = __('Ärijärelmaks 0€ sissemakse, 0€ haldustasu, 0€ lepingutasu. Hajuta mugavalt maksed 6-48 kuu peale.',
            'modena');
        $this->default_alt        = 'Modena - Äri Järelmaks kuni 48 kuud';
        $this->maturity_in_months = 36;

        $this->default_image = 'https://cdn.modena.ee/modena/assets/modena_woocommerce_business_credit_62c8f2fa76.png?81164.69999998808';
        $this->default_icon_title_text = 'Modena ärikliendi järelmaksu võimaldab Modena Estonia OÜ.';


        parent::__construct();
    }

    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['description'] = [
            'title'       => __('Payment Button Description', 'modena'),
            'type'        => 'textarea',
            'description' => __('This controls the description which the user sees during checkout.', 'modena'),
            'default'     => __('Ettevõtetele mõeldud järelmaks. Tasu ostu eest osadena 6 - 48 kuu jooksul.',
                'modena'),
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
        return $this->modena->postBusinessCreditPaymentOrder($request);
    }
}
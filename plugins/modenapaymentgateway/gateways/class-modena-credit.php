<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Credit_Payment extends Modena_Base_Payment
{
    public function __construct()
    {
        $this->id         = 'modena_credit';
        $this->title      = 'Modena järelmaks';
        $this->hide_title = true;
        $this->enabled    = $this->get_option('enabled');

        $this->method_title       = 'Modena Credit';
        $this->method_description = __('0€ sissemakse, 0€ haldustasu, 0€ lepingutasu. Hajuta mugavalt maksed 6-48 kuu peale.',
            'modena');
        $this->default_alt        = 'Modena - Järelmaks kuni 48 kuud';
        $this->maturity_in_months = 36;

        $this->default_image = 'https://cdn.modena.ee/modena/assets/modena_woocommerce_credit_alt_ee69576caf.png?15591391.9';
        $this->default_icon_title_text = 'Modena järelmaksu võimaldab Modena Estonia OÜ.';
        parent::__construct();
    }

    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['description'] = [
            'title'       => __('Payment Button Description', 'modena'),
            'type'        => 'textarea',
            'description' => __('This controls the description which the user sees during checkout.', 'modena'),
            'default'     => __('0€ sissemakse, 0€ haldustasu, 0€ lepingutasu. Hajuta mugavalt maksed 6-48 kuu peale.',
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
        return $this->modena->postCreditPaymentOrder($request);
    }
}
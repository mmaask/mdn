<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Credit_Payment extends Modena_Base_Payment
{
    public function __construct()
    {
        $this->id                                   = 'modena_credit';
        $this->maturity_in_months                   = 36;
        $this->hide_title                           = false;
        $this->enabled                              = $this->get_option('enabled');

        $this->title                                = __('Modena - Järelmaks', 'modena');
        $this->method_title                         = __('Modena Credit', 'modena');
        $this->method_description                   = __('0€ sissemakse, 0€ haldustasu, 0€ lepingutasu. Hajuta mugavalt maksed 6-48 kuu peale.','modena');
        $this->default_alt                          = __('Modena - Järelmaks kuni 48 kuud', 'modena');
        $this->default_image                        = __('https://cdn.modena.ee/modena/assets/modena_woocommerce_credit_alt_ee69576caf.png', 'modena');
        $this->default_icon_title_text              = __('Modena järelmaksu võimaldab Modena Estonia OÜ.', 'modena');
        $this->default_payment_gateway_description  = __('0€ sissemakse, 0€ haldustasu, 0€ lepingutasu. Hajuta mugavalt maksed 6-48 kuu peale.', 'modena');

        parent::__construct();
    }

    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['description']                = [
            'title'       => __('Payment Method Description Text Box', 'modena'),
            'type'        => 'textarea',
            'description' => __($this->default_payment_gateway_description, 'modena'),
            'default'     => $this->default_payment_gateway_description,
            'css'         => 'width:29em',
            'desc_tip'    => false,
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

    protected function getPaymentApplicationStatus($applicationId)
    {
        return $this->modena->getCreditPaymentApplicationStatus($applicationId);
    }
}
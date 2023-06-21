<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Business_Leasing extends Modena_Base_Payment
{
    public function __construct()
    {
        $this->id                                   = 'modena_business_leasing';
        $this->maturity_in_months                   = 36;
        $this->hide_title                           = false;
        $this->enabled                              = $this->get_option('disabled');

        $this->title                                = __('Modena - Ärikliendi järelmaks', 'modena');
        $this->method_title                         = __('Modena Business Leasing', 'modena');
        $this->method_description                   = __('Vormista järelmaks ettevõtte nimele. Tasu ostu eest 6-48 kuu jooksul.','modena');
        $this->default_alt                          = __('Modena - Ärikliendi järelmaks kuni 48 kuud', 'modena');
        $this->default_image                        = __('https://cdn.modena.ee/modena/assets/modena_woocommerce_business_credit_62c8f2fa76.png', 'modena');
        $this->default_icon_title_text              = __('Modena ärikliendi järelmaksu võimaldab Modena Estonia OÜ.', 'modena');
        $this->default_payment_gateway_description  = __('Vormista järelmaks ettevõtte nimele. Tasu ostu eest 6-48 kuu jooksul.', 'modena');

        parent::__construct();
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
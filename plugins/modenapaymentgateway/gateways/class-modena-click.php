<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Click_Payment extends Modena_Base_Payment
{
    public function __construct()
    {
        $this->id                                   = 'modena_click';
        $this->maturity_in_months                   = 1;
        $this->hide_title                           = false;
        $this->enabled                              = $this->get_option('disabled');

        $this->title                                = __('Modena - Proovi kodus, Maksa hiljem');
        $this->method_title                         = __('Modena Try Now Pay Later', 'modena');
        $this->method_description                   = __('Telli tooted koju proovimiseks. Väljavalitud kauba eest saad arve 30 päeva pärast. Lisatasudeta.', 'modena');
        $this->default_alt                          = __('Modena - Proovi kodus, Maksa hiljem', 'modena');
        $this->default_image                        = __('https://cdn.modena.ee/modena/assets/modena_woocommerce_try_est_bd64474b16.png', 'modena');
        $this->default_icon_title_text              = __('Modena proovi kodus, Maksa hiljem võimaldab Modena Estonia OÜ.', 'modena');
        $this->default_payment_gateway_description  = __('Telli tooted koju proovimiseks. Väljavalitud kauba eest saad arve 30 päeva pärast. Lisatasudeta.', 'modena');

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
        return $this->modena->postClickPaymentOrder($request);
    }

    protected function getPaymentApplicationStatus($applicationId)
    {
        return $this->modena->getClickPaymentApplicationStatus($applicationId);
    }
}
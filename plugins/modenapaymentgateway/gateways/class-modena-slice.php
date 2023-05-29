<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Slice_Payment extends Modena_Base_Payment
{
    public function __construct()
    {
        $this->id         = 'modena_slice';
        $this->title      = 'Maksa 3 osas';
        $this->hide_title = false;
        $this->enabled    = $this->get_option('enabled');

        $this->method_title       = 'Modena Pay in 3';
        $this->method_description = __('Maksa 3 osas, 0€ lisatasu', 'modena');
        $this->default_alt        = 'Modena - Maksa 3 osas, 0€ lisatasu';
        $this->maturity_in_months = 3;

        $this->default_image = 'https://cdn.modena.ee/modena/assets/modena_woocommerce_slice_alt_2dacff6e81.png?15560016.3';
        $this->default_icon_title_text = 'Modena osamakseid võimaldab Modena Estonia OÜ.';
        parent::__construct();
    }

    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['description']                 = [
            'title'       => __('Payment Button Description', 'modena'),
            'type'        => 'textarea',
            'description' => __('This controls the description which the user sees during checkout.', 'modena'),
            'default'     => __('0€ sissemakse, 0% intress, 0€ lisatasu. Lihtsalt maksa hiljem.', 'modena'),
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
    public function get_icon_alt()
    {
        if ($this->icon_alt_text) {
            return $this->icon_alt_text;
        }

        return $this->default_alt;
    }

    protected function postPaymentOrderInternal($request) {
        return $this->modena->postSlicePaymentOrder($request);
    }

    protected function getPaymentApplicationStatus($applicationId)
    {
        return $this->modena->getSlicePaymentApplicationStatus($applicationId);
    }
}
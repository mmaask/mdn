<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Slice_Payment extends Modena_Base_Payment
{
    public function __construct()
    {
        $this->id                                           = 'modena_slice';
        $this->maturity_in_months                           = 3;
        $this->hide_title                                   = false;
        $this->enabled                                      = $this->get_option('enabled');
        $this->title                                        = __('Modena - Maksa 3 osas', 'modena');
        $this->method_title                                 = __('Modena Pay in 3', 'modena');

        $this->initialize_variables_with_translations();

        parent::__construct();
    }

    public function initialize_variables_with_translations() {

        $translations = array(
            'et' => array(
                'default_image'                             => __('https://cdn.modena.ee/modena/assets/modena_woocommerce_slice_alt_2dacff6e81.png', 'modena'),
                'default_alt'                               => __('Modena - Maksa 3 osas, 0€ lisatasu', 'modena'),
                'default_icon_title_text'                   => __('Modena osamakseid võimaldab Modena Estonia OÜ.', 'modena'),
                'description'                               => __('0€ sissemakse, 0% intress, 0€ lisatasu. Lihtsalt maksa hiljem.', 'modena'),
            ),
            'ru_RU' => array(
                'default_image'                             => __('https://cdn.modena.ee/modena/assets/modena_woocommerce_slice_rus_4da0fdb806.png', 'modena'),
                'default_alt'                               => __('Modena - Платежа до 3 месяцев', 'modena'),
                'default_icon_title_text'                   => __('Модена 3 платежа предоставляется Modena Estonia OÜ.', 'modena'),
                'description'                               => __('0€ первоначальный взнос, 0% интресс, 0€ дополнительная плата. Просто платите позже.', 'modena'),
            ),
            'en_US' => array(
                'default_image'                             => __('https://cdn.modena.ee/modena/assets/modena_woocommerce_slice_eng_228d8b3eed.png', 'modena'),
                'default_alt'                               => __('Modena - Installments up to 48 months', 'modena'),
                'default_icon_title_text'                   => __('Modena installments is provided by Modena Estonia OÜ.', 'modena'),
                'description'                               => __('0€ down payment, 0% interest, 0€ extra charge. Simply pay later.', 'modena'),
            ),
        );

        $this->default_image                                = $translations[get_locale()]['default_image'] ?? $translations['en_US']['default_image'];
        $this->default_alt                                  = $translations[get_locale()]['default_alt'] ?? $translations['en_US']['default_image'];
        $this->description                                  = $translations[get_locale()]['description'] ?? $translations['en_US']['description'];
        $this->default_icon_title_text                      = $translations[get_locale()]['default_icon_title_text'] ?? $translations['en_US']['default_icon_title_text'];
        $this->method_description                           = $this->description;
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
        return $this->modena->postSlicePaymentOrder($request);
    }

    protected function getPaymentApplicationStatus($applicationId)
    {
        return $this->modena->getSlicePaymentApplicationStatus($applicationId);
    }
}
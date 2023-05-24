<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Slice_Payment extends Modena_Base_Payment
{
    public function __construct() {
        $this->id         = 'modena_slice';
        $this->enabled    = $this->get_option('disabled');
        $this->maturity_in_months = 3;
        $this->logo_enabled    = $this->get_option('enabled');

        $this->setNamesBasedOnLocales(get_locale());

        parent::__construct();
    }

    public function setNamesBasedOnLocales($current_locale)
    {
        $this->mdn_translations = array(
            'en' => array(
                'method_title' => 'Modena - Pay in 3',
                'default_alt' => 'Modena - Installments up to 48 months',
                'method_description' => '0€ down payment, 0% interest, 0€ extra charge. Simply pay later.',
                'title' => 'Modena Pay Later',
                'default_image' => 'https://cdn.modena.ee/modena/assets/modena_woocommerce_slice_eng_228d8b3eed.png?439267.59999996424',
                'default_icon_title_text' => 'Modena installments is provided by Modena Estonia OÜ.',
                'description' => '0€ down payment, 0% interest, 0€ extra charge. Simply pay later.',
            ),
            'ru' => array(
                'method_title' => 'Modena - 3 платежа',
                'default_alt' => 'Modena - Платежа до 3 месяцев',
                'method_description' => '0€ первоначальный взнос, 0% интресс, 0€ дополнительная плата. Просто платите позже.',
                'title' => 'Modena - 3 платежа до 3 месяцев',
                'default_image' => 'https://cdn.modena.ee/modena/assets/modena_woocommerce_slice_rus_4da0fdb806.png?747330.8000000119',
                'default_icon_title_text' => 'Модена 3 платежа предоставляется Modena Estonia OÜ.',
                'description' => '0€ первоначальный взнос, 0% интресс, 0€ дополнительная плата. Просто платите позже.',
            ),
            'et' => array(
                'method_title' => 'Modena - Maksa 3 osas',
                'default_alt' => 'Maksa 3 osas, 0€ lisatasu',
                'method_description' => 'Maksa 3 osas, 0€ lisatasu',
                'title' => 'Maksa 3 osas',
                'default_image' => 'https://cdn.modena.ee/modena/assets/modena_woocommerce_slice_alt_2dacff6e81.png?15560016.3',
                'default_icon_title_text' => 'Osamakseid võimaldab Modena Estonia OÜ.',
                'description' => '0€ sissemakse, 0% intress, 0€ lisatasu. Lihtsalt maksa hiljem.',
            ),
        );
        $locale_key = substr($current_locale, 0, 2);
        if (!array_key_exists($locale_key, $this->mdn_translations)) {
            $locale_key = 'en'; // default to English if the locale does not exist in the translations array
        }

        foreach ($this->mdn_translations[$locale_key] as $key => $value) {
            if($key === 'default_image') {
                if($this->get_option('logo_enabled') == 'no') {
                    //error_log($this->get_option('logo_enabled') . " " . $this->id);
                    $this->{$key} = '';
                } else {
                    $this->{$key} = $value;
                }
            } else {
                $this->{$key} = $value;
            }
        }
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

}
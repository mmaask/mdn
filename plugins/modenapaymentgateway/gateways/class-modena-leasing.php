<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Leasing extends Modena_Base_Payment
{
    public function __construct()
    {
        $this->id         = 'modena_leasing';
        //$this->hide_title = true;
        $this->enabled    = $this->get_option('disabled');
        $this->maturity_in_months = 36;
        $this->logo_enabled    = $this->get_option('enabled');

        $this->setNamesBasedOnLocales(get_locale());

        parent::__construct();
    }
    public function setNamesBasedOnLocales($current_locale)
    {
        $translations = array(
            'en' =>  array(
                'method_title' => __('Modena Business Leasing', 'mdn-translations'),
                'default_alt' => __('Modena - Leasing up to 48 months', 'mdn-translations'),
                'method_description' => __('Arrange installment plan for the company name. Pay for the purchase over 6-48 months.', 'modena'),
                'title' => __('Modena Leasing', 'mdn-translations'),
                'default_image' => 'https://cdn.modena.ee/modena/assets/modena_woocommerce_business_credit_eng_0f8d5b1a5a.png?536007.8999999762',
                'default_icon_title_text' => __('Modena Leasing is provided by Modena Estonia OÜ.', 'mdn-translations'),
                'description' => __('Installment payment option for businesses. Pay for your purchase in parts over 6 - 48 months.', 'mdn-translations')
            ),
            'ru' => array(
                'method_title' => __('Modena бизнес лизинг', 'mdn-translations'),
                'default_alt' => __('Modena - бизнес лизинг до 48 месяцев', 'mdn-translations'),
                'method_description' => __('Оформите рассрочку на имя компании. Оплатите покупку в течение 6-48 месяцев.', 'modena'),
                'title' => __('Бизнес лизинг до 48 месяцев', 'mdn-translations'),
                'default_image' => 'https://cdn.modena.ee/modena/assets/modena_woocommerce_business_credit_rus_f84c520f4a.png?76438.69999998808',
                'default_icon_title_text' => __('Модена рассрочка предоставляется Modena Estonia OÜ.', 'mdn-translations'),
                'description' => __('Опция рассрочки для бизнеса. Оплачивайте за свою покупку частями в течение 6 - 48 месяцев.', 'mdn-translations'),
            ),
            'et' => array(
                'method_title' => __('Modena - Äri järelmaks', 'mdn-translations'),
                'default_alt' => __('Modena - Järelmaks kuni 48 kuud', 'mdn-translations'),
                'method_description' => __('Vormista järelmaks ettevõtte nimele. Tasu ostu eest 6-48 kuu jooksul.', 'modena'),
                'title' => __('Modena ärikliendi järelmaks', 'mdn-translations'),
                'default_image' => 'https://cdn.modena.ee/modena/assets/modena_woocommerce_business_credit_62c8f2fa76.png?81164.69999998808',
                'default_icon_title_text' => __('Modena äri järelmaksu võimaldab Modena Estonia OÜ.', 'mdn-translations'),
                'description' => 'Ettevõtetele mõeldud järelmaks. Tasu ostu eest osadena 6 - 48 kuu jooksul.',
            ),
        );

        // Set the locale key based on the current locale
        $locale_key = substr($current_locale, 0, 2);
        if (!array_key_exists($locale_key, $translations)) {
            $locale_key = 'en'; // default to English if the locale does not exist in the translations array
        }

        foreach ($translations[$locale_key] as $key => $value) {
            if($key === 'default_image') {
                if($this->get_option('logo_enabled') == 'no') {
                    //error_log($this->get_option('logo_enabled'));
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
        return $this->modena->postBusinessLeasingPaymentOrder($request);
    }
}
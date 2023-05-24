<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Credit_Payment extends Modena_Base_Payment
{
    public function __construct() {
        $this->id         = 'modena_credit';
        $this->enabled    = $this->get_option('disabled');
        $this->logo_enabled    = $this->get_option('enabled');
        $this->maturity_in_months = 36;
        $this->setNamesBasedOnLocales(get_locale());
        parent::__construct();
    }

    public function setNamesBasedOnLocales($current_locale)
    {
        $this->mdn_translations = array(
            'en' => array(
                'method_title' => __('Modena - Credit', 'mdn-translations'),
                'default_alt' => __('Modena - Credit up to 48 months', 'mdn-translations'),
                'method_description' => __('0€ down payment, 0€ administration fee, 0€ contract fee. Spread your payments conveniently over 6-48 months.', 'mdn-translations'),
                'title' => __('Modena Credit up to 48 months', 'mdn-translations'),
                'default_image' => 'https://cdn.modena.ee/modena/assets/modena_woocommerce_credit_eng_6a1c94d9a2.png?504170.39999997616',
                'default_icon_title_text' => __('Modena Credit is provided by Modena Estonia OÜ.', 'mdn-translations'),
                'description' => '0€ down payment, 0€ management fee, 0€ contract fee. Spread your payments comfortably over 6-48 months.',

            ),
            'ru' => array(
                'method_title' => __('Modena - Рассрочка', 'mdn-translations'),
                'default_alt' => __('Modena - Рассрочка до 48 месяцев', 'mdn-translations'),
                'method_description' => __('Рассрочка до 48 месяцев. 0€ первоначальный взнос, 0€ плата за управление договором, 0€ плата за договор. Удобно распределите свои платежи на период от 6 до 48 месяцев.', 'modena'),
                'title' => __('Modena - Рассрочка до 48 месяцев', 'mdn-translations'),
                'default_image' => 'https://cdn.modena.ee/modena/assets/modena_woocommerce_credit_rus_79ecd31ab6.png?410623.89999997616',
                'default_icon_title_text' => __('Модена рассрочка предоставляется Modena Estonia OÜ.', 'mdn-translations'),
                'description' => '0€ первоначальный взнос, 0€ плата за управление договором, 0€ плата за договор. Удобно распределите свои платежи на период от 6 до 48 месяцев.',
            ),
            'et' => array(
                'method_title' => __('Modena - Järelmaks', 'mdn-translations'),
                'default_alt' => __('Modena - Järelmaks kuni 48 kuud', 'mdn-translations'),
                'method_description' => __('0€ sissemakse, 0€ haldustasu, 0€ lepingutasu. Hajuta mugavalt maksed 6-48 kuu peale.', 'modena'),
                'title' => __(' Modena järelmaks', 'mdn-translations'),
                'default_image' => 'https://cdn.modena.ee/modena/assets/modena_woocommerce_credit_alt_ee69576caf.png?15591391.9',
                'default_icon_title_text' => __('Modena järelmaksu võimaldab Modena Estonia OÜ.', 'mdn-translations'),
                'description' => '0€ sissemakse, 0€ haldustasu, 0€ lepingutasu. Hajuta maksed mugavalt 6-48 kuu peale. Makseteenuse pakkujaks on Modena.',
            ),
        );
        $this->set_gateway_config(get_locale(), $this->mdn_translations);

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
        $this->create_translation_based_form_fields($this->mdn_translations);
    }

    protected function postPaymentOrderInternal($request) {
        return $this->modena->postCreditPaymentOrder($request);
    }

    protected function set_gateway_config($current_locale, $translations) {
        $locale_key = 'en';

        // Set the locale key based on the current locale
        $locale_key = substr($current_locale, 0, 2);
        if (!array_key_exists($locale_key, $translations)) {
            $locale_key = 'en'; // default to English if the locale does not exist in the translations array
        }

        foreach ($translations[$locale_key] as $key => $value) {
            $this->{$key} = $value;
        }
    }

    protected function create_translation_based_form_fields($translations) {
        foreach ($translations as $locale_key => $translation) {
            $this->form_fields['separator_' . $locale_key] = [
                'title' => 'Checkout translations: ' . strtoupper($locale_key),
                'type' => 'title',
                'description' => '',
            ];
            foreach ($translation as $key => $value) {
                if($key === 'title' || $key === 'description') {
                    $this->form_fields[$locale_key . '_' . $key] = [
                        'title'       => __($key, 'modena'),
                        'type'        => 'text',
                        'description' => '',
                        'default'     => $value,
                    ];
                } else {
                    continue;
                }
            }
        }
    }
}
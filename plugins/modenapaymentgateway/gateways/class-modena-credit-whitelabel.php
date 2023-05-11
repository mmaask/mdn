<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Credit_Payment_Whitelabel extends Modena_Base_Payment
{
    public function __construct()
    {
        $this->id         = 'modena_credit_whitelabel';
        $this->hide_title = false;
        $this->enabled    = $this->get_option('disabled');
        $this->maturity_in_months = 36;

        $this->setNamesBasedOnLocales(get_locale());

        parent::__construct();
    }
    public function setNamesBasedOnLocales($current_locale)
    {
        error_log("Finding locale: "  . $current_locale);
        $translations = array(
            'en' => array(
                'method_title' => __('Modena Credit Payments Whitelabel', 'mdn-translations'),
                'default_alt' => __('Credit up to 48 months', 'mdn-translations'),
                'method_description' => __('0€ down payment, 0€ administration fee, 0€ contract fee. Spread your payments conveniently over 6-48 months.', 'mdn-translations'),
                'title' => __('Credit', 'mdn-translations'),
                //'default_image' => 'https://cdn.modena.ee/modena/assets/modena_woocommerce_credit_alt_ee69576caf.png?15591391.9',
                'default_icon_title_text' => __('Credit is provided by Modena Estonia OÜ.', 'mdn-translations'),
                'default_payment_button_description' => __('0€ down payment, 0% interest, 0€ extra charge. Simply pay later.', 'mdn-translations'),
            ),
            'ru' => array(
                'method_title' => __('Modena рассрочка Whitelabel', 'mdn-translations'),
                'default_alt' => __('Modena - Рассрочка до 48 месяцев', 'mdn-translations'),
                'method_description' => __('', 'modena'),
                'title' => __('Modena рассрочка', 'mdn-translations'),
                //'default_image' => 'https://cdn.modena.ee/modena/assets/modena_woocommerce_credit_alt_ee69576caf.png?15591391.9',
                'default_icon_title_text' => __('Модена рассрочка предоставляется Modena Estonia OÜ.', 'mdn-translations'),
                'default_payment_button_description' => __('0€ down payment, 0% interest, 0€ extra charge. Simply pay later.','mdn-translations'),
            ),
            'et' => array(
                'method_title' => __('Modena Maksa 3 osas Whitelabel', 'mdn-translations'),
                'default_alt' => __('Maksa kolmes osas kuni 3 kuud', 'mdn-translations'),
                'method_description' => __('0€ sissemakse, 0€ haldustasu, 0€ lepingutasu. Hajuta mugavalt maksed 6-48 kuu peale.', 'mdn-translations'),
                'title' => __('Järelmaks', 'mdn-translations'),
                //'default_image' => 'https://cdn.modena.ee/modena/assets/modena_woocommerce_credit_alt_ee69576caf.png?15591391.9',
                'default_icon_title_text' => __('Järelmaksu võimaldab Modena Estonia OÜ.', 'mdn-translations'),
                'default_payment_button_description' => __('0€ sissemakse, 0€ haldustasu, 0€ lepingutasu. Hajuta mugavalt maksed 6-48 kuu peale.', 'mdn-translations'),
            ),
        );

        // Set the locale key based on the current locale
        $locale_key = 'en';

        switch ($current_locale) {
            case 'ru_RU':
                $locale_key = 'ru';
                break;
            case 'en_GB':
            case 'en_US':
                break;
            default:
                $locale_key = 'et';
                break;
        }

        foreach ($translations[$locale_key] as $key => $value) {
            $this->{$key} = $value;
        }
    }


    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields['description'] = [
            'title'       => __('Payment Button Description', 'modena'),
            'type'        => 'textarea',
            'description' => __('This controls the description which the user sees during checkout.', 'modena'),
            'default'     => __($this->default_payment_button_description, 'modena'),
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
        return $this->modena->postCreditWhiteLabelPaymentOrder($request);
    }
}
<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Leasing extends Modena_Base_Payment
{
    public function __construct()
    {
        $this->id         = 'modena_leasing';
        $this->hide_title = true;
        $this->enabled    = $this->get_option('disabled');
        $this->maturity_in_months = 36;

        $this->setNamesBasedOnLocales(get_locale());

        parent::__construct();
    }
    public function setNamesBasedOnLocales($current_locale)
    {
        error_log("Finding locale: "  . $current_locale);
        $translations = array(
            'en' =>  array(
                'method_title' => __('Modena Business Leasing', 'mdn-translations'),
                'default_alt' => __('Modena - Leasing up to 48 months', 'mdn-translations'),
                'method_description' => __('Arrange installment plan for the company name. Pay for the purchase over 6-48 months.', 'modena'),
                'title' => __('Modena Leasing', 'mdn-translations'),
                'default_image' => 'https://cdn.modena.ee/modena/assets/modena_woocommerce_business_credit_62c8f2fa76.png?81164.69999998808',
                'default_icon_title_text' => __('Modena Leasing is provided by Modena Estonia OÜ.', 'mdn-translations'),
                'default_payment_button_description' => __('0€ down payment, 0% interest, 0€ extra charge. Simply pay later.', 'mdn-translations')
            ),
            'ru' => array(
                'method_title' => __('Modena бизнес лизинг', 'mdn-translations'),
                'default_alt' => __('Modena - Рассрочка до 48 месяцев', 'mdn-translations'),
                'method_description' => __('Оформите рассрочку на имя компании. Оплатите покупку в течение 6-48 месяцев.', 'modena'),
                'title' => __('Бизнес лизинг', 'mdn-translations'),
                'default_image' => 'https://cdn.modena.ee/modena/assets/modena_woocommerce_business_credit_62c8f2fa76.png?81164.69999998808',
                'default_icon_title_text' => __('Модена рассрочка предоставляется Modena Estonia OÜ.', 'mdn-translations'),
                'default_payment_button_description' => __('0€ down payment, 0% interest, 0€ extra charge. Simply pay later.', 'mdn-translations'),
            ),
            'et' => array(
                'method_title' => __('Modena Äri järelmaks', 'mdn-translations'),
                'default_alt' => __('Modena - Järelmaks kuni 48 kuud', 'mdn-translations'),
                'method_description' => __('Vormista järelmaks ettevõtte nimele. Tasu ostu eest 6-48 kuu jooksul.', 'modena'),
                'title' => __('Modena ärikliendi järelmaks', 'mdn-translations'),
                'default_image' => 'https://cdn.modena.ee/modena/assets/modena_woocommerce_business_credit_62c8f2fa76.png?81164.69999998808',
                'default_icon_title_text' => __('Modena äri järelmaksu võimaldab Modena Estonia OÜ.', 'mdn-translations'),
                'default_payment_button_description' => 'Ettevõtetele mõeldud järelmaks. Tasu ostu eest osadena 6 - 48 kuu jooksul.',
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
            //'default'     => __('', 'modena'),
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
        return $this->modena->postBusinessLeasingPaymentOrder($request);
    }
}
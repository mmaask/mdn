<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Click_Payment extends Modena_Base_Payment
{
    public function __construct()
    {
        $this->id                                           = 'modena_click';
        $this->maturity_in_months                           = 1;
        $this->hide_title                                   = false;
        $this->enabled                                      = $this->get_option('disabled');
        $this->method_title                                 = __('Modena Try Now Pay Later', 'modena');

        $this->initialize_variables_with_translations();

        parent::__construct();
    }

    public function initialize_variables_with_translations() {

        $translations = array(
            'et' => array(
                'default_image'                             => __('https://cdn.modena.ee/modena/assets/modena_woocommerce_try_est_bd64474b16.png', 'modena'),
                'default_alt'                               => __('Modena - Proovi kodus, Maksa hiljem', 'modena'),
                'default_icon_title_text'                   => __('Modena Proovi kodus, Maksa hiljem võimaldab Modena Estonia OÜ.', 'modena'),
                'description'                               => __('Telli tooted koju proovimiseks. Väljavalitud kauba eest saad arve 30 päeva pärast. Lisatasudeta.','modena'),

                ),
            'ru_RU' => array(
                'default_image'                             => __('https://cdn.modena.ee/modena/assets/modena_woocommerce_try_rus_c091675f4f.png', 'modena'),
                'default_alt'                               => __('Modena - Попробуйте дома, заплатите позже', 'modena'),
                'default_icon_title_text'                   => __('Модена Попробуйте дома, заплатите позже предоставляется Modena Estonia OÜ.', 'modena'),
                'description'                               => __('Закажите товары, чтобы попробовать их дома. Вы получите счет за выбранный товар через 30 дней. Никаких наценок', 'modena'),
            ),
            'en_US' => array(
                'default_image'                             => __('https://cdn.modena.ee/modena/assets/modena_woocommerce_try_eng_0f3893e620.png', 'modena'),
                'default_alt'                               => __('Modena - Try at home, Pay Later', 'modena'),
                'default_icon_title_text'                   => __('Modena Try at home, Pay Later is provided by Modena Estonia OÜ.', 'modena'),
                'description'                               => __('Order products to try at home. Receive an invoice for the selected products 30 days later. No additional charges.', 'modena'),
            ),
        );


        $this->default_image                                = $translations[get_locale()]['default_image'] ?? $translations['en_US']['default_image'];
        $this->default_alt                                  = $translations[get_locale()]['default_alt'] ?? $translations['en_US']['default_image'];
        $this->description                                  = $translations[get_locale()]['description'] ?? $translations['en_US']['description'];
        $this->default_icon_title_text                      = $translations[get_locale()]['default_icon_title_text'] ?? $translations['en_US']['default_icon_title_text'];
        $this->method_description                           = $this->description;
        $this->title                                        = $this->default_alt;
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
        return $this->modena->postClickPaymentOrder($request);
    }

    protected function getPaymentApplicationStatus($applicationId)
    {
        return $this->modena->getClickPaymentApplicationStatus($applicationId);
    }
}
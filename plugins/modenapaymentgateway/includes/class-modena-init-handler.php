<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

require_once(MODENA_PLUGIN_PATH . 'includes/class-modena-admin-handler.php');
require_once(MODENA_PLUGIN_PATH . 'includes/class-home-url.php');
require_once(MODENA_PLUGIN_PATH . 'shipping/class-modena-shipping-handler.php');

class Modena_Init_Handler
{
    const PAYMENT_GATEWAYS = [
        'Modena_Direct_Payment' => 'direct',
        'Modena_Slice_Payment'  => 'slice',
        'Modena_Credit_Payment' => 'credit',
        'Modena_Business_Credit_Payment' => 'business-credit',
    ];

    public function run()
    {

        /**
        *create a new object of a Shipping Handler that creates necessary shipping methods in Woocommerce and fields for the end user.
        */
        new Modena_Shipping_Handler();
        new Modena_Admin_Handler();
        add_action('plugins_loaded', [$this, 'maybe_init'], 99);
    }
    public function maybe_init(): void
    {
        if (!$this->is_woocommerce_active() || !class_exists('WC_Payment_Gateway')) {
            return;
        }

        $this->init();
    }
    public function is_woocommerce_active()
    {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) ||
            (is_multisite() && in_array('woocommerce/woocommerce.php',
                    array_keys(get_site_option('active_sitewide_plugins'))));
    }
    public function init(): void
    {
        add_filter('plugin_action_links_modena/modena.php', array($this, 'modena_plugin_action_links'));

        $this->modena_gateways_init();

        add_action('woocommerce_single_product_summary', [$this, 'display_product_installments'],
            apply_filters('modena_product_installments_priority', 15));
        add_filter('woocommerce_available_variation', [$this, 'display_variation_installments'],
            apply_filters('modena_variation_installments_priority', 10));


    }
    function modena_gateways_init()
    {
        if (!class_exists('Modena_Base_Payment')) {
            require_once(MODENA_PLUGIN_PATH . 'gateways/class-modena-base.php');
        }

        foreach (self::PAYMENT_GATEWAYS as $className => $fileName) {
            if (!class_exists($className)) {
                require_once(MODENA_PLUGIN_PATH . 'gateways/class-modena-' . $fileName . '.php');
            }
        }

        add_filter('woocommerce_payment_gateways', array($this, 'add_modena_payment_gateways'));

    }

    function modena_plugin_action_links($links)
    {
        $plugin_links = array(
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . __('Settings',
                'modena') . '</a>',
            '<a href="https://developer.modena.ee" target="_blank">' . __('Support', 'modena') . '</a>',
        );

        return array_merge($plugin_links, $links);
    }


    private function is_slice_enabled(): bool
    {
        $setting = get_option('woocommerce_modena_slice_settings')['enabled'] ?? false;

        return $setting === 'yes';
    }

    private function is_credit_enabled(): bool
    {
        $setting = get_option('woocommerce_modena_credit_settings')['enabled'] ?? false;

        return $setting === 'yes';
    }
    private function is_business_credit_enabled(): bool
    {
        $setting = get_option('woocommerce_modena_business_credit_settings')['enabled'] ?? false;

        return $setting === 'yes';
    }
    public function display_product_installments(): void
    {
        global $product;

        if ($product->is_type('variable')) {
            return;
        }

        $active_price = wc_get_price_to_display($product);
        if (!$active_price) {
            return;
        }

        echo $this->get_slice_banner_html($active_price);
        echo $this->get_credit_banner_html($active_price);
        echo $this->get_business_credit_banner_html($active_price);
//        echo $this->add_checkout_fields()
    }

    private function get_slice_banner_html($active_price): string
    {
        if (!$this->is_slice_enabled() || !$this->is_slice_product_banner_enabled()) {
            return '';
        }

        return $this->get_installment_price_html($this->get_slice_banner_text($active_price));
    }

    private function get_credit_banner_html($active_price): string
    {
        if (!$this->is_credit_enabled() || !$this->is_credit_product_banner_enabled()) {
            return '';
        }

        return $this->get_installment_price_html($this->get_credit_banner_text($active_price));
    }

    private function get_business_credit_banner_html($active_price): string
    {
        if (!$this->is_business_credit_enabled() || !$this->is_business_credit_product_banner_enabled()) {
            return '';
        }

        return $this->get_installment_price_html($this->get_business_credit_banner_text($active_price));
    }
    private function is_slice_product_banner_enabled(): bool
    {
        $setting = get_option('woocommerce_modena_slice_settings')['product_page_banner_enabled'] ?? false;

        return $setting === 'yes';
    }

    private function is_credit_product_banner_enabled(): bool
    {
        $setting = get_option('woocommerce_modena_credit_settings')['product_page_banner_enabled'] ?? false;

        return $setting === 'yes';
    }

    private function is_business_credit_product_banner_enabled(): bool
    {
        $setting = get_option('woocommerce_modena_business_credit_settings')['product_page_banner_enabled'] ?? false;

        return $setting === 'yes';
    }

    private function get_slice_banner_text($active_price): string
    {
        return sprintf(__("3 makset %s€ kuus, ilma lisatasudeta.&ensp;", "woocommerce"),
            $this->get_installment_number($active_price / 3.0));
    }

    private function get_credit_banner_text($active_price): string
    {
        return sprintf(__("Järelmaks alates %s€ / kuu, 0€ lepingutasu.&ensp;", "woocommerce"),
            $this->get_installment_number($active_price * 0.0325));
    }

    private function get_business_credit_banner_text($active_price): string
    {
        return sprintf(__("Ärikliendi järelmaks alates %s€ / kuu, 0€ lepingutasu.&ensp;", "woocommerce"),
            $this->get_installment_number($active_price * 0.0325));
    }

    private function get_installment_price_html($text): string
    {
        $icon = '<img src="' . WC_HTTPS::force_https_url('https://cdn.modena.ee/modena/assets/modena_logo_black_3f62f63466.png?1863664.3000000007') . '" alt="Modena" style="max-height: 16px; margin-bottom: -3px; vertical-align: baseline;"/>';

        return '<p id="mdn-slice-product-page-display" style="margin: 0.5rem 0 1.5rem 0;">'
            . $text
            . $icon
            . '</p>';
    }

    private function get_installment_number($number): float
    {
        return number_format($number, 2, '.', '');
    }



    public function display_variation_installments($variation_data): array
    {
        $active_price = $variation_data['display_price'];
        if (!$active_price) {
            return $variation_data;
        }

        $sliceBannerHtml  = $this->get_slice_banner_html($active_price);
        $creditBannerHtml = $this->get_credit_banner_html($active_price);
        $businessCreditBannerHtml = $this->get_business_credit_banner_html($active_price);

        if ($sliceBannerHtml || $creditBannerHtml) {
            $variation_data['price_html'] .= '<br>';
        }

        if ($sliceBannerHtml) {
            $variation_data['price_html'] .= $sliceBannerHtml;
        }

        if ($creditBannerHtml) {
            $variation_data['price_html'] .= $creditBannerHtml;
        }

        if ($businessCreditBannerHtml) {
            $variation_data['price_html'] .= $businessCreditBannerHtml;
        }

        return $variation_data;
    }

    function add_modena_payment_gateways($methods)
    {
        return array_merge($methods, array_keys(self::PAYMENT_GATEWAYS));
    }
}
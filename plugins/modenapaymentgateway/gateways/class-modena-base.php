<?php

use Modena\Payment\model\ModenaApplication;
use Modena\Payment\model\ModenaCustomer;
use Modena\Payment\model\ModenaOrderItem;
use Modena\Payment\model\ModenaRequest;
use Modena\Payment\Modena;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

abstract class Modena_Base_Payment extends WC_Payment_Gateway
{
    const PLUGIN_VERSION             = '3.0.0';
    const MODENA_META_KEY            = 'modena-application-id';
    const MODENA_SELECTED_METHOD_KEY = 'modena-payment-method';
    const MODENA_SELECTED_BANK_METHOD = 'mdn_direct_payment_selected_bank';
    protected $client_id;
    protected $client_secret;
    protected $is_test_mode;
    protected $maturity_in_months;
    protected $site_url;
    protected $logger;
    protected $modena;
    protected $payment_button_max_height;
    protected $onboarding;
    protected $environment;
    protected $default_image;
    protected $hide_title = false;
    protected $default_icon_title_text;
    protected $default_alt;
    protected $button_text;
    protected $icon_alt_text;
    protected $icon_title_text;

    public function __construct()
    {
        require_once MODENA_PLUGIN_PATH . 'autoload.php';
        require ABSPATH . WPINC . '/version.php';

        $this->onboarding = new Modena_Onboarding_Handler();
        $this->onboarding->maybe_received_credentials();

        $this->environment = $this->get_option('environment');

        $this->client_id     = $this->get_option($this->environment . '_client_id');
        $this->client_secret = $this->get_option($this->environment . '_client_secret');
        $this->is_test_mode  = $this->environment === 'sandbox';

        $this->site_url = get_home_url();
        $this->logger   = new WC_Logger(array(new Modena_Log_Handler()));

        $userAgent = sprintf(
            'ModenaWoocommerce/%s WooCommerce/%s WordPress/%s',
            self::PLUGIN_VERSION,
            (!defined('WC_VERSION') ? '0.0.0' : WC_VERSION),
            (empty($wp_version) ? '0.0.0' : $wp_version)
        );

        $this->modena = new Modena(
            $this->client_id,
            $this->client_secret,
            $userAgent,
            $this->is_test_mode
        );

        //$this->description               = $this->get_option('description');
        //$this->button_text               = $this->get_option('payment_button_text');
        $this->payment_button_max_height = 30;
        $this->icon                      = $this->default_image;

        $this->icon_alt_text             = $this->default_alt;
        $this->icon_title_text           = $this->default_icon_title_text;

        $this->init_form_fields();

        $this->init_settings();

        if ($this->get_option('payment_button_max_height') >= 24 && $this->get_option('payment_button_max_height') <= 30) {
            $this->payment_button_max_height = $this->get_option('payment_button_max_height');
        }

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        add_action('woocommerce_api_redirect_to_modena_' . $this->id, [$this, 'redirect_to_modena']);

        add_action('woocommerce_api_modena_response_' . $this->id, [$this, 'modena_response']);

        add_action('woocommerce_api_modena_async_response_' . $this->id, [$this, 'modena_async_response']);

        add_action('woocommerce_api_modena_cancel_' . $this->id, [$this, 'modena_cancel']);

        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'credentials_title_line'    => [
                'type'  => 'title',
                'class' => 'modena-header-css-class',
            ],
            'credentials_title'         => array(
                'title'       => __('Modena Credentials', 'modena'),
                'type'        => 'title',
                'description' => __('Select Live mode to accept payments and Sandbox mode to test payments.', 'modena'),
            ),
            'environment'               => array(
                'title'       => __('Environment', 'modena'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'options'     => array(
                    'sandbox' => __('Sandbox mode', 'modena'),
                    'live'    => __('Live mode', 'modena'),
                ),
                'description' => __('<div id="environment_alert_desc"></div>', 'modena'),
                'default'     => 'sandbox',
                'desc_tip'    => __('Choose Sandbox mode to test payment using test API keys. Switch to live mode to accept payments with Modena using live API keys.',
                    'modena'),
            ),
            'sandbox_client_id'         => [
                'title'    => __('Sandbox Client ID', 'modena'),
                'type'     => 'text',
                'desc_tip' => true,
            ],
            'sandbox_client_secret'     => [
                'title'    => __('Sandbox Client Secret', 'modena'),
                'type'     => 'text',
                'desc_tip' => true,
            ],
            'live_client_id'            => [
                'title'    => __('Live Client ID', 'modena'),
                'type'     => 'text',
                'desc_tip' => true,
            ],
            'live_client_secret'        => [
                'title'    => __('Live Client Secret', 'modena'),
                'type'     => 'text',
                'desc_tip' => true,
            ],
            'gateway_title'             => [
                'type'  => 'title',
                'class' => 'modena-header-css-class',
            ],
            'enabled'                   => [
                'title'       => sprintf(__('%s Payment Gateway', 'modena'), $this->get_method_title()),
                'label'       => '<span class="modena-slider"></span>',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no',
                'class'       => 'modena-switch',
            ],
            'payment_button_max_height' => [
                'title'       => __('Payment Button Max Height', 'modena'),
                'type'        => 'number',
                'description' => __('This controls the maximum height of the payment button image in pixels, as allowed by your checkout template.',
                    'modena'),
                'default'     => 30,
                'desc_tip'    => true,
            ]
        ];
    }

    public function admin_options()
    {
        $autoconfigure_live_button    = '<a href="' . esc_url($this->onboarding->get_autoconfig_url($this->id,
                false)) . '" class="button button-primary">' . __('Autoconfigure Modena API LIVE credentials',
                'modena') . '</a>';
        $autoconfigure_sandbox_button = '<a href="' . esc_url($this->onboarding->get_autoconfig_url($this->id,
                true)) . '" class="button button-primary">' . __('Autoconfigure Modena API SANDBOX credentials',
                'modena') . '</a>';

        $api_live_credentials_text    = sprintf(
            '<div class="live_env_alert">' . __('WARNING: LIVE ENVIRONMENT SELECTED!',
                'modena') . '</div><p style="padding-top:15px;">%s</p><p style="padding-top:15px;"><a href="%s" class="toggle-credential-settings" target="_blank">' . __('Or click here to toggle manual API credential input',
                'modena') . '</a></p>',
            $autoconfigure_live_button,
            esc_url($this->onboarding->get_partner_portal_url(false))
        );
        $api_sandbox_credentials_text = sprintf(
            '<p style="padding-top:15px;">%s</p><p style="padding-top:15px;"><a href="%s" class="toggle-credential-settings" target="_blank">' . __('Or click here to toggle manual API credential input',
                'modena') . '</a></p>',
            $autoconfigure_sandbox_button,
            esc_url($this->onboarding->get_partner_portal_url(true))
        );

        wc_enqueue_js("
            jQuery( function( $ ) {
                $('.description').css({'font-style':'normal'});
                $('.modena-header-css-class').css({'border-top': 'dashed 1px #ccc','padding-top': '15px','width': '100%'});
                var " . $this->id . "_live = jQuery(  '#woocommerce_" . $this->id . "_live_client_id, #woocommerce_" . $this->id . "_live_client_secret').closest( 'tr' );
                var " . $this->id . "_sandbox = jQuery(  '#woocommerce_" . $this->id . "_sandbox_client_id, #woocommerce_" . $this->id . "_sandbox_client_secret').closest( 'tr' );
                $( '#woocommerce_" . $this->id . "_environment' ).change(function(){
                    if ( 'live' === $( this ).val() ) {
                            $( " . $this->id . "_live  ).show();
                            $( " . $this->id . "_sandbox ).hide();
                            $( '#environment_alert_desc').html('" . $api_live_credentials_text . "');
                    } else {
                            $( " . $this->id . "_live ).hide();
                            $( " . $this->id . "_sandbox ).show();
                            $( '#environment_alert_desc').html('" . $api_sandbox_credentials_text . "');
                        }
                }).change();
            });
        ");
        parent::admin_options();
    }

    public function admin_scripts()
    {
        wp_register_style('modena-admin-style', MODENA_PLUGIN_URL . 'assets/css/modena-admin-style.css');
        wp_enqueue_style('modena-admin-style');
    }

    abstract protected function postPaymentOrderInternal($request);

    public function redirect_to_modena()
    {
        $orderItems = [];

        $order = wc_get_order(sanitize_text_field($_GET['id']));

        $customer = new ModenaCustomer(
            $order->get_billing_first_name(),
            $order->get_billing_last_name(),
            $order->get_billing_email(),
            $order->get_billing_phone(),
            join(', ', [
                $order->get_billing_address_1(), $order->get_billing_address_2(), $order->get_billing_city(),
                $order->get_billing_state(),
            ])
        );

        foreach ($order->get_items(['line_item', 'fee', 'shipping']) as $orderItem) {
            $orderItems[] = new ModenaOrderItem(
                $orderItem->get_name(),
                $orderItem->get_total() + $orderItem->get_total_tax(),
                $orderItem->get_quantity(),
                get_woocommerce_currency()
            );
        }

        $maturityInMonths = isset($_GET['maturityInMonths']) ? sanitize_text_field($_GET['maturityInMonths']) : null;
        $selectedOption = isset($_GET['selectedOption']) ? sanitize_text_field($_GET['selectedOption']) : null;
        $paymentType = isset($_GET['paymentType']) ? sanitize_text_field($_GET['paymentType']) : null;

        $application = new ModenaApplication(
            $maturityInMonths,
            $selectedOption,
            strval($order->get_id()),
            $order->get_total(),
            $orderItems,
            $customer,
            date("Y-m-d\TH:i:s.u\Z"),
            get_woocommerce_currency()
        );

        $request = new ModenaRequest(
            $application,
            sprintf('%s/wc-api/modena_response_%s', HomeUrl::baseOnly(), $this->id),
            sprintf('%s/wc-api/modena_cancel_%s', HomeUrl::baseOnly(), $this->id),
            sprintf('%s/wc-api/modena_async_response_%s', HomeUrl::baseOnly(), $this->id)
        );

        try {
            $response = $this->postPaymentOrderInternal($request);
            if ($response->getApplicationId()) {
                $humanReadablePaymentMethod = "Modena - {$this->get_human_readable_selected_method($selectedOption)}";
                $order->add_meta_data(self::MODENA_META_KEY, $response->getApplicationId(), true);
                $order->add_meta_data(self::MODENA_SELECTED_METHOD_KEY, $humanReadablePaymentMethod, true);
                $order->add_meta_data(self::MODENA_SELECTED_BANK_METHOD, $selectedOption);

                $order->save_meta_data();
                $order->save();
                wp_redirect($response->getRedirectLocation());
            }
        } catch (Exception $exception) {
            $this->logger->error('Exception occurred when redirecting to modena: ' . $exception->getMessage());
            $this->logger->error($exception->getTraceAsString());
            wp_safe_redirect(get_home_url());
        }

        exit;
    }



    public function modena_response()
    {
        global $woocommerce;

        $modenaResponse = $this->modena->getOrderResponseFromRequest($_POST);

        if (!$this->validate_modena_response($modenaResponse)) {
            $this->logger->error('Modena response is invalid: ' . json_encode($modenaResponse));
            wp_redirect($this->site_url);
            wc_add_notice(__('Something went wrong, please try again later.', 'modena'));
            exit;
        }

        try {
            $applicationStatus = $this->modena->getPaymentApplicationStatus($modenaResponse->getApplicationId(),
                $this->id);

            if ($applicationStatus !== 'SUCCESS') {
                $this->logger->error('Invalid application status, expected: SUCCESS | received: ' . $applicationStatus);
                wp_redirect($this->site_url);
                wc_add_notice(__('Something went wrong, please try again later.', 'modena'));
                exit;
            }

            $order = wc_get_order($modenaResponse->getOrderId());

            if ($order && $order->get_payment_method() === $this->id) {
                if ($order->needs_payment()) {
                    $order->payment_complete();

                    //Can we get the bank which is paid with to show in the order note??
                    //$order->add_order_note(sprintf(__('Order paid via %s', 'modena'), $this->method_title));
                    if($order->get_payment_method() === 'modena_direct') {
                        $bank_name = $this->get_bank_name($order); // Assume this method exists and returns the bank name.

                        $order->add_order_note(sprintf(
                            __('Order paid via %s - %s', 'modena'),
                            $this->get_order_payment_method_eng($this->id),
                            $bank_name
                        ));
                    } else {
                        $order->add_order_note(sprintf(
                            __('Order paid via %s', 'modena'),
                            $this->get_order_payment_method_eng($this->id)
                        ));
                    }
                    $woocommerce->cart->empty_cart();
                }

                wp_safe_redirect($this->get_return_url($order));
                exit;
            } else {
                if (!$order) {
                    $this->logger->error(sprintf('Order not found for id: %s', $modenaResponse->getOrderId()));
                } else {
                    $this->logger->error(
                        sprintf(
                            'Payment is not successful, order is found but payment method mismatch: [method: %s, needs_payment: %s]' .  ' Order number: ' . $modenaResponse->getOrderId() . 'Payment method is: ' . $this->id,
                            $order->get_payment_method(),
                            $order->needs_payment()

                        )
                    );
                }
                wp_safe_redirect(wc_get_cart_url());
                wc_add_notice(__('Something went wrong, please try again later.', 'modena'));
                exit;
            }
        } catch (Exception $e) {
            $this->logger->error('Exception occurred in payment response: ' . $e->getMessage());
            $this->logger->error($e->getTraceAsString());
            wp_redirect($this->site_url);
            wc_add_notice(__('Something went wrong, please try again later.', 'modena'));
            exit;
        }
    }

    private function validate_modena_response($modenaResponse)
    {
        if (!$modenaResponse->getOrderId() || !$modenaResponse->getApplicationId()) {
            return false;
        }

        $order              = wc_get_order($modenaResponse->getOrderId());
        $orderApplicationId = $order->get_meta(self::MODENA_META_KEY);

        return $orderApplicationId === $modenaResponse->getApplicationId();
    }

    public function modena_cancel()
    {
        global $woocommerce;

        $modenaResponse = $this->modena->getOrderResponseFromRequest($_POST);

        if (!$this->validate_modena_response($modenaResponse)) {
            $this->logger->error('Modena cancel response is invalid: ' . json_encode($modenaResponse));
            wp_redirect($this->site_url);
            wc_add_notice(__('Something went wrong, please try again later.', 'modena'));
            exit;
        }

        try {
            $applicationStatus = $this->modena->getPaymentApplicationStatus($modenaResponse->getApplicationId(),
                $this->id);

            if ($applicationStatus !== 'FAILED' && $applicationStatus !== 'REJECTED') {
                $this->logger->error('Invalid application status, expected: FAILED or REJECTED | received: ' . $applicationStatus);
                wp_redirect($this->site_url);
                wc_add_notice(__('Something went wrong, please try again later.', 'modena'));
                exit;
            }

            $woocommerce->cart->empty_cart();
            $order          = wc_get_order($modenaResponse->getOrderId());
            $payment_method = $order->get_payment_method();

            if ($payment_method === $this->id) {
                foreach ($order->get_items() as $orderItem) {
                    $data = $orderItem->get_data();
                    WC()->cart->add_to_cart($data['product_id'], $data['quantity'], $data['variation_id']);
                }

                wp_safe_redirect(wc_get_cart_url());
                wc_add_notice(__('Payment canceled.', 'modena'));
                exit;
            } else {
                $this->logger->error(sprintf(
                    'Payment is not successful, payment is cancelled. [method: %s, needs_payment: %s] ' .  'Order number: ' . $modenaResponse->getOrderId() . 'Payment method is: ' . $this->id,
                    $order->get_payment_method(),
                    $order->get_payment_method(),
                    $order->needs_payment()
                ));
                wp_redirect($this->site_url);
                wc_add_notice(__('Something went wrong, please try again later.', 'modena'));
                exit;
            }
        } catch (Exception $e) {
            $this->logger->error('Exception occurred in payment cancel function: ' . $e->getMessage());
            $this->logger->error($e->getTraceAsString());
            wp_redirect($this->site_url);
            wc_add_notice(__('Something went wrong, please try again later.', 'modena'));
            exit;
        }
    }

    public function modena_async_response()
    {
        global $woocommerce;

        $modenaResponse = $this->modena->getOrderResponseFromRequest($_POST);

        if (!$this->validate_modena_response($modenaResponse)) {
            $this->logger->error('Modena response is invalid: ' . json_encode($modenaResponse));
            exit;
        }

        try {
            $applicationStatus = $this->modena->getPaymentApplicationStatus($modenaResponse->getApplicationId(),
                $this->id);

            if ($applicationStatus !== 'SUCCESS') {
                $this->logger->error('Invalid application status, expected: SUCCESS | received: ' . $applicationStatus);
                exit;
            }

            $order = wc_get_order($modenaResponse->getOrderId());

            if ($order && $order->get_payment_method() === $this->id && $order->needs_payment()) {
                $order->payment_complete();
                $order->add_order_note(sprintf(__('Order paid via %s', 'modena'), $this->method_title));
                $woocommerce->cart->empty_cart();
                exit;
            } else {
                if (!$order) {
                    $this->logger->error('Order not found for id: ' . $modenaResponse->getOrderId());
                } else {
                    $this->logger->error(
                        sprintf(
                            'Payment successful, but the order not found or payment method mismatch or order already paid. [method: %s, needs_payment: %s] '. $modenaResponse->getOrderId(),
                            $order->get_payment_method(),
                            $order->needs_payment()
                        )
                    );
                }
                exit;
            }
        } catch (Exception $e) {
            $this->logger->error('Exception occurred in payment response: ' . $e->getMessage());
            $this->logger->error($e->getTraceAsString());
            exit;
        }
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $order->update_status('pending', sprintf(__('Pending %s Payment...', 'modena'), $this->method_title));
        $order->save();

        return array(
            'result'   => 'success',
            'redirect' => $this->create_redirect_url(
                $order_id,
                $this->maturity_in_months,
                isset($_POST['MDN_OPTION']) ? sanitize_text_field($_POST['MDN_OPTION']) : null
            ),
        );
    }

    private function create_redirect_url($orderId, $maturityInMonths, $selectedOption)
    {
        return sprintf(
            '%s/wc-api/redirect_to_modena_%s?id=%d&maturityInMonths=%d&selectedOption=%s',
            HomeUrl::baseOnly(),
            $this->id,
            $orderId,
            $maturityInMonths,
            $selectedOption
        );
    }

    public function get_icon()
    {
        if ($this->button_text) {
            return '';
        }

        $marginLeft = $this->hide_title ? 'margin-left: 0 !important;' : '';
        $icon       = '<img src="' . WC_HTTPS::force_https_url($this->icon) . '" title="' . esc_attr($this->get_icon_title()) . '" alt="' . esc_attr($this->get_icon_alt()) . '" style="max-height:' . strval($this->payment_button_max_height) . 'px;' . $marginLeft . '"/>';

        return $this->icon ? apply_filters('woocommerce_gateway_icon', $icon, $this->id) : '';
    }

    public function get_description()
    {
        $this->logger->error(get_locale());
        $description = $this->description; // since description is not directly defined it sometimes will find random value assigned.
        if ($this->hide_title) {
            $description .= '<style>label[for=payment_method_' . $this->id . '] { font-size: 0 !important; }</style>';
        }
        /*
         * Using the singleton pattern we can ensure that the <link> or <script> tags get added to the site only once.
         * */
        Modena_Load_Checkout_Assets::getInstance();

        return apply_filters('woocommerce_gateway_description', $description, $this->id);
    }
    public function get_icon_title()
    {
        if ($this->icon_title_text) {
            return $this->icon_title_text;
        }
        return $this->default_icon_title_text;
    }

    public function get_icon_alt()
    {
        if ($this->icon_alt_text) {
            return $this->icon_alt_text;
        }
        return $this->default_alt;
    }

    public function get_title()
    {
        if ($this->button_text) {
            return $this->button_text;
        }
        return $this->title;
    }

    private function get_human_readable_selected_method($selectedOption) {
        if ($this instanceof Modena_Slice_Payment) {
            if (get_locale() == "RU") {
                return __("Оплатить позже", 'modena');
            } elseIF(get_locale() == "ET") {
                return __('Maksa 3 osas', 'modena');
            } else {
                return __('Pay Later', 'modena');
            }
        }

        if ($this instanceof Modena_Leasing) {
            if (get_locale() == "RU") {
                return __("Бизнес лизинг", 'modena');
            } elseIF(get_locale() == "ET") {
                return __('Äri järelmaks', 'modena');
            } else {
                return __('Business Leasing', 'modena');
            }
        }

        if ($this instanceof Modena_Slice_Payment_Whitelabel) {
            if (get_locale() == "RU") {
                return __("Оплатить позже: WL", 'modena');
            } elseIF(get_locale() == "ET") {
                return __('Maksa 3 osas: WL', 'modena');
            } else {
                return __('Pay Later: WL', 'modena');
            }
        }

        if ($this instanceof Modena_Credit_Payment_Whitelabel) {
            if (get_locale() == "RU") {
                return __("Рассрочка", 'modena');
            } elseIF(get_locale() == "ET") {
                return __('Järelmaks', 'modena');
            } else {
                return __('Credit', 'modena');
            }
        }

        if ($this instanceof Modena_Credit_Payment) {
            if (get_locale() == "RU") {
                return __("Рассрочка", 'modena');
            } elseIF(get_locale() == "ET") {
                return __('Järelmaks', 'modena');
            } else {
                return __('Credit', 'modena');
            }
        }
        error_log("This is the selected bank option: (" . $selectedOption . ') if empty then something went wrong with returning the correct bank name');

        switch ($selectedOption) {
            case 'HABAEE2X':
                return 'Swedbank';
            case 'EEUHEE2X':
                return 'SEB';
            case 'LHVBEE22':
                return 'LHV';
            case 'NDEAEE2X':
                return 'Luminor';
            case 'PARXEE22':
                return 'Citadele';
            case 'EKRDEE22':
                return 'COOP';
            case 'CREDIT_CARD':
                return 'Visa / Mastercard';
            default:
                return '';
        }
    }

    private function get_order_payment_method_eng($method_id) {
        if ($method_id === 'modena_direct') {
            return __('Bank & Card Payments');
        }
        if ($method_id === 'modena_leasing') {
            return __('Modena Leasing');
        }
        if ($method_id === 'modena_slice') {
            return __('Modena Pay Later');
        }
        else {
            return __('Modena Credit');
        }
    }

    private function get_bank_name($order) {

        $selectedOption = $order->get_meta('mdn_direct_payment_selected_bank');
        $bankOptions = [
            'HABAEE2X' => 'Swedbank',
            'EEUHEE2X' => 'SEB',
            'LHVBEE22' => 'LHV',
            'NDEAEE2X' => 'Luminor',
            'EKRDEE22' => 'COOP',
            'PARXEE22' => 'Citadele',
            'CREDIT_CARD' => 'Visa / Mastercard',
            'default' => 'Direct'
        ];

        if (isset($bankOptions[$selectedOption])) {
            return $bankOptions[$selectedOption];
        }

        error_log("This is the selected bank option: (" . $selectedOption . ') if empty then something went wrong with returning the correct bank name');
        return $bankOptions['default'];

    }
}
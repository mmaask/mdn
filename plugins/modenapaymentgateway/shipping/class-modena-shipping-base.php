<?php
if (!defined('ABSPATH')) { exit;}

abstract class Modena_Shipping_Base extends WC_Shipping_Method {

    public $instance_id;
    public $title;
    public $method_title;
    public $method_description;

    protected string $client_id;
    protected string $client_secret;
    protected string $domain;
    protected int $max_weight;

    public function __construct()
    {

        $this->domain                =       'modena';
        $this->instance_id           =       absint($instance_id= 0);
        $this->max_weight             =      35;
        $this->client_id             =       $this->get_option('client_id');
        $this->client_secret         =       $this->get_option('client-secret');

        $this->enabled               =       $this->settings['enabled'] ?? 'no';

        $this->init_form_fields();
        $this->init_settings();

        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );

        parent::__construct();
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Enable', $this->get_domain()),
                'type' => 'checkbox',
                'description' => __( 'Enable this shipping method to the customer.', $this->get_domain()),
                'default' => 'no'
            ),
            'client-id' => array(
                'title' => __( 'API Client ID', $this->get_domain()),
                'type' => 'text',
                'description' => __( 'API Client ID to connect to Modena', $this->get_domain()),
                'default' => __( $this->client_id, $this->get_domain())
            ),
            'client-secret' => array(
                'title' => __( 'API Client Secret', $this->get_domain()),
                'type' => 'text',
                'description' => __( 'API Client ID to connect to Modena', $this->get_domain()),
                'default' => __( $this->client_secret, $this->get_domain())
            ));
        }

    public function get_domain(): ?string
    {
        return $this->domain;
    }
    public function get_client_id() {
            return $this->client_id;
        }
    public function get_client_secret() {
        return $this->client_secret;
    }
    public function get_shipping_method_max_weight(): int
    {
        return $this->max_weight;
    }
}


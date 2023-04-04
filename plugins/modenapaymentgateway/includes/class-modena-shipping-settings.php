<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
$cost_desc = __( 'Enter a cost (excl. tax) or sum, e.g. <code>10.00 * [qty]</code>.', $this->domain ) . '<br/><br/>' . __( 'Use <code>[qty]</code> for the number of items, <br/><code>[cost]</code> for the total cost of items, and <code>[fee percent="10" min_fee="20" max_fee=""]</code> for percentage based fees.', $this->domain );

$settings = array(
    'title'            => array(
    'title'       => __( 'Title', 'woocommerce' ),
    'type'        => 'text',
    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
    'default'     => $this->method_title,
    'desc_tip'    => true,
    ),
    'cost'       => array(
    'title'             => __( 'Cost', $this->domain ),
    'type'              => 'text',
    'placeholder'       => '',
    'description'       => $cost_desc,
    'default'           => '0',
    'desc_tip'          => true,
    'sanitize_callback' => array( $this, 'sanitize_cost' ),
    ),
    'free-shipping-treshold'      => array(
    'title'       => __( 'Free shipping over sum of' ),
    'type'        => 'text',
    'description' => __( 'This controls the amount needed for free shipping.'),
    'default'     => __(0 ),
    'desc_tip'    => true,
    ),
    'sender-name'       => array(
    'title'       => __( 'Sender name' ),
    'type'        => 'text',
    'description' => __( 'This controls the parcel sender name that is required and sent to Itella.'),
    'default'     => __( ''),
    'desc_tip'    => true,
    ),
    'sender-email'       => array(
    'title'       => __( 'Sender email' ),
    'type'        => 'text',
    'description' => __( 'This controls the parcel sender email that is required and sent to Itella.'),
    'default'     => __( ''),
    'desc_tip'    => true,
    ),
    'sender-phone'       => array(
    'title'       => __( 'Sender phone' ),
    'type'        => 'number',
    'description' => __( 'This controls the parcel sender phone number that is required and sent to Itella.'),
    'default'     => __('' ),
    'desc_tip'    => true,
    ),
    'itella_api_key'      => array(
    'title'       => __( 'Itella API Key' ),
    'type'        => 'text',
    'description' => __( 'This controls the API key Secret from Modena'),
    'default'     => __( 'thisistheitellaapikeyfor1444221!'),
    'desc_tip'    => true,
    ),
    'itella_api_secret'      => array(
    'title'       => __( 'Itella API Secret' ),
    'type'        => 'text',
    'description' => __( 'This controls the API key Secret from Itella'),
    'default'     => __( 'secretcustomerapisecretfromparterportal112.xxxa4!'),
    'desc_tip'    => true,
    ),
    'client-id'      => array(
    'title'       => __( 'Modena API ID' ),
    'type'        => 'text',
    'description' => __( 'This controls the API key ID from Modena'),
    'default'     => __( 'idofcustomersecretkeyfromparterportal112.yyyy66!'),
    'desc_tip'    => true,
    ),
    'client-secret'      => array(
    'title'       => __( 'Modena API Secret' ),
    'type'        => 'text',
    'description' => __( 'This controls the API key Secret from Modena'),
    'default'     => __( 'secretcustomerapisecretfromparterportal112.xxxa4!'),
    'desc_tip'    => true,
    )
);

return $settings;
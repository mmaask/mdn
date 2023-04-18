<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

abstract class Modena_Shipping_Itella extends WC_Shipping_Method {

    private mixed $cost;

    public function __construct($instance_id = 0) {
        parent::__construct($instance_id);
        $this->instance_id = absint($instance_id);
        $this->supports = array('shipping-zones', 'instance-settings', );

        $this->init_form_fields();

        $this->title = $this->get_option('title');
        $this->cost = $this->get_option('cost');

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));

    }

    public function init_form_fields(): void {
        $cost_desc = __('Enter a cost (excl. tax) or sum, e.g. <code>10.00 * [qty]</code>.') . '<br/><br/>' . __('Use <code>[qty]</code> for the number of items, <br/><code>[cost]</code> for the total cost of items, and <code>[fee percent="10" min_fee="20" max_fee=""]</code> for percentage based fees.');

        $this->instance_form_fields = array(
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default' => $this->method_title,
                'desc_tip' => true,
            ),
            'cost' => array(
                'title' => __('Cost'),
                'type' => 'int',
                'placeholder' => '',
                'description' => $cost_desc,
                'default' => '0',
                'desc_tip' => true,
                'sanitize_callback' => array($this, 'sanitize_cost'),
            ),
        );
    }

    public function calculate_shipping($package = array()) {

        $rate = array(
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $this->cost,
        );

        $this->add_rate($rate);
    }

    public function getOrderTotalWeightAndContents($order): array {
        $packageContent = '';
        $total_weight = 0;

        foreach ($order->get_items() as $item_id => $item) {
            if ($item instanceof WC_Order_Item_Product) {
                $product_name = $item->get_name();
                $quantity = $item->get_quantity();
                $packageContent .= $quantity . ' x ' . $product_name . "\n";

                $product = $item->get_product();
                $product_weight = $product->get_weight();
                $total_weight += $product_weight * $quantity;
            }
        }
        return array(
            'total_weight' => $total_weight,
            'packageContent' => $packageContent,
        );
    }

    public function sanitizeOrderProductDimensions($ProductDimensions): array {
        return array_map(function ($ProductDimensions) {
            return max(0, (float) $ProductDimensions);
        }, $ProductDimensions);
    }

    public function addFreeShippingToProduct() {
        //todo
    }

    public function addFreeShippingOverTreshold() {
        //todo
    }

}
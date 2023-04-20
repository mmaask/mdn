<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

abstract class Modena_Shipping_Method extends WC_Shipping_Method {
    public function __construct($instance_id = 0) {
        parent::__construct($instance_id);

        $this->instance_id = absint($instance_id);
        $this->supports           = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );

        $this->init();
    }

    public function init() {

        $this->title = $this->get_option('title');

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
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

    public function sanitizeshippingMethodCost($shippingMethodCost): float {
        $sanitizedshippingMethodCost = floatval($shippingMethodCost);
        if ($sanitizedshippingMethodCost < 0) {
            $sanitizedshippingMethodCost = $this->cost;
        }
        return $sanitizedshippingMethodCost;
    }

    public function addFreeShippingToProduct() {
        //todo
    }

    public function addFreeShippingOverTreshold() {
        //todo
    }

}
<?php

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

class ModenaShippingItellaParcels extends ModenaShippingBase {

    // Define size constants
    const SIZE_XS = 'XS';
    const SIZE_S  = 'S';
    const SIZE_M  = 'M';
    const SIZE_L  = 'L';
    const SIZE_XL = 'XL';

    // Define threshold constants for each dimension and weight
    const XS_HEIGHT = 5;
    const XS_WIDTH = 34;
    const XS_DEPTH = 42;
    const XS_WEIGHT = 5;

    const S_HEIGHT = 12;
    const S_WIDTH = 34;
    const S_DEPTH = 42;
    const S_WEIGHT = 35;

    const M_HEIGHT = 20;
    const M_WIDTH = 34;
    const M_DEPTH = 42;
    const M_WEIGHT = 35;

    const L_HEIGHT = 34;
    const L_WIDTH = 36;
    const L_DEPTH = 42;
    const L_WEIGHT = 35;

    const XL_HEIGHT = 60;
    const XL_WIDTH = 36;
    const XL_DEPTH = 60;
    const XL_WEIGHT = 35;


    public function __construct($instance_id = 0) {
    $this->id                                    = 'modena-shipping-parcels-itella';
    $this->modena_shipping_type                  = 'parcels';
    $this->modena_shipping_service               = 'Itella';
    $this->title                                 = __('Itella Smartpost');
    $this->method_title                          = __('Modena - Itella Smartpost');
    $this->cost                                  = 0.99;
    $this->max_weight_for_modena_shipping_method = 35;

    error_log($this->cost . ", " . $this->id);

    parent::__construct($instance_id);
  }

    public function init_form_fields() {
        parent::init_form_fields();
        $this->form_fields += array(
            'dynamic_pricing' => array(
                'title'       => __('Aktiveeri pakisuuruse põhine hinnastamine', 'modena-for-woocommerce'),
                'type'        => 'checkbox',
                'description' => __('Vastavalt pakimõõtmele hinnastamine', 'modena-for-woocommerce'),
                'default'     => 'yes',
            ),
            'xs_cost' => array(
                'title'       => __('XS pakihind', 'modena-for-woocommerce'),
                'type'        => 'number',
                'default'     => '2.59',
            ),
            's_cost' => array(
                'title'       => __('S pakihind', 'modena-for-woocommerce'),
                'type'        => 'number',
                'default'     => '2.99',
            ),
            'm_cost' => array(
                'title'       => __('M pakihind', 'modena-for-woocommerce'),
                'type'        => 'number',
                'default'     => '3.99',
            ),
            'l_cost' => array(
                'title'       => __('L pakihind', 'modena-for-woocommerce'),
                'type'        => 'number',
                'default'     => '4.89',
            ),
            'xl_cost' => array(
                'title'       => __('XL pakihind', 'modena-for-woocommerce'),
                'type'        => 'number',
                'default'     => '6.49',
            ),
        );
    }

    public function calculate_shipping($package = array()) {
        error_log("Starting calculate_shipping() for " . $this->id);
        global $woocommerce;

        // Log the cart total
        $cartTotal = $woocommerce->cart->get_cart_contents_total();
        error_log("Cart total is: " . $cartTotal);

        // Assuming you have a method to get cart item count; otherwise, this will need to be adjusted.
        $cartItemCount = $woocommerce->cart->get_cart_contents_count();
        error_log("Cart item count is: " . $cartItemCount);

        // Log the shipping options being checked
        error_log("Free shipping threshold enabled? " . $this->get_option('modena_free_shipping_treshold'));
        error_log("Free shipping by quantity threshold enabled? " . $this->get_option('modena_quantity_free_shipping_treshold'));

        $rate = array(
            'id' => $this->id,
            'label' => $this->title,
        );

        if ($this->get_option('dynamic_pricing') === 'yes') {
            error_log("Dynamic pricing activated.");

            $size = $this->get_package_size($package);
            error_log("Determined package size: $size");

            if ($size === self::SIZE_XS) {
                $rate['cost'] = $this->get_option('xs_cost');
            } elseif ($size === self::SIZE_S) {
                $rate['cost'] = $this->get_option('s_cost');
            } elseif ($size === self::SIZE_M) {
                $rate['cost'] = $this->get_option('m_cost');
            } elseif ($size === self::SIZE_L) {
                $rate['cost'] = $this->get_option('l_cost');
            } elseif ($size === self::SIZE_XL) {
                $rate['cost'] = $this->get_option('xl_cost');
            }
        } else if ($this->get_option('modena_free_shipping_treshold') === 'yes' || $this->get_option('modena_quantity_free_shipping_treshold') === 'yes') {
            error_log("Free shipping threshold sum: " . $this->get_option('modena_free_shipping_treshold_sum'));
            error_log("Free shipping by quantity threshold sum: " . $this->get_option('modena_quantity_free_shipping_tresholdsum'));

            if ($this->get_option('modena_free_shipping_treshold_sum') <= $cartTotal || $this->get_option('modena_quantity_free_shipping_tresholdsum') < $cartItemCount) {
                $rate['cost'] = 0;
                error_log("Free shipping rate added due to cart value or quantity.");
            } else {
                // Consider some default/fixed cost here if size-based pricing is not used.
                $rate['cost'] = $this->cost; // Assuming $this->cost is your default cost
                error_log("Standard shipping rate added.");
            }
        } else {
            error_log("Dynamic pricing not activated.");
            // Consider some default/fixed cost here if size-based pricing is not used.
            $rate['cost'] = $this->cost; // Assuming $this->cost is your default cost
            error_log("Standard shipping rate added.");
        }

        $this->add_rate($rate);
        error_log("Final calculated rate: " . json_encode($rate));
    }

    public function get_package_size($package) {
        error_log("Starting get_package_size()");

        $total_height = 0;
        $total_weight = 0;
        $total_length = 0;
        $total_width = 0;

        foreach ($package['contents'] as $item) {
            $product = $item['data'];

            $height = method_exists($product, 'get_height') && is_numeric($product->get_height()) ? $product->get_height() : 0;
            $weight = method_exists($product, 'get_weight') && is_numeric($product->get_weight()) ? $product->get_weight() : 0;
            $length = method_exists($product, 'get_length') && is_numeric($product->get_length()) ? $product->get_length() : 0;
            $width = method_exists($product, 'get_width') && is_numeric($product->get_width()) ? $product->get_width() : 0;

            $total_height += $height * $item['quantity'];
            $total_weight += $weight * $item['quantity'];
            $total_length += $length * $item['quantity'];
            $total_width += $width * $item['quantity'];

            error_log("Product Dimensions - Height: $height, Weight: $weight, Length: $length, Width: $width");
        }

        // Debugging lines
        error_log("Total Height: $total_height");
        error_log("Total Weight: $total_weight");
        error_log("Total Length: $total_length");
        error_log("Total Width: $total_width");

        // Based on the table provided
        if ($total_height <= self::XS_HEIGHT && $total_weight <= self::XS_WEIGHT && $total_length <= self::XS_DEPTH && $total_width <= self::XS_WIDTH) {
            return self::SIZE_XS;
        } elseif ($total_height <= self::S_HEIGHT && $total_weight <= self::S_WEIGHT && $total_length <= self::S_DEPTH && $total_width <= self::S_WIDTH) {
            return self::SIZE_S;
        } elseif ($total_height <= self::M_HEIGHT && $total_weight <= self::M_WEIGHT && $total_length <= self::M_DEPTH && $total_width <= self::M_WIDTH) {
            return self::SIZE_M;
        } elseif ($total_height <= self::L_HEIGHT && $total_weight <= self::L_WEIGHT && $total_length <= self::L_DEPTH && $total_width <= self::L_WIDTH) {
            return self::SIZE_L;
        } elseif ($total_height <= self::XL_HEIGHT && $total_weight <= self::XL_WEIGHT && $total_length <= self::XL_DEPTH && $total_width <= self::XL_WIDTH) {
            return self::SIZE_XL;
        }

        // If the package doesn't fit into any of the categories, return a default value
        error_log("Package size is Oversize");
        return 'Oversize';
    }
}
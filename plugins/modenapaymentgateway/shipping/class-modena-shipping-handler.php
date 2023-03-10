<?php

/**
 * Exit if accessed directly.
 */
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Modena_Shipping_Handler {

    /**
     * instantinate the shipping handler
     */

    public function __construct() {
        $this->modena_shipping_init();
    }
    /**
     * declare new shipping methods
     */

    const SHIPPING_MODULES = [
        'Modena_Shipping_Self_Service' => 'self-service',
    ];

    /**
     *
     * @return array
     */

    function add_modena_shipping_modules($modules) {
        return array_merge($modules, array_keys(self::SHIPPING_MODULES));
    }

    /**
     * hook all custom data fields to woocommerce
     */

    function modena_shipping_init() {
        foreach (self::SHIPPING_MODULES as $className => $fileName) {
            if(!class_exists($className)) {
                require_once(MODENA_PLUGIN_PATH . 'shipping/class-modena-shipping-' . $fileName . '.php');
            }
        }
        add_filter('woocommerce_shipping_methods', array($this, 'add_modena_shipping_modules'));
        add_filter( 'woocommerce_review_order_before_payment' , array( $this, 'renderCheckoutSelection' ) );
        add_filter( 'woocommerce_shipping_method_chosen', array( $this, 'getCurrentShippingMethod'));

        add_filter( 'woocommerce_review_order_before_payment', array( $this, 'findUserShipmentAndPostIt' ));
        add_action('woocommerce_thankyou', array( $this, 'findUserShipmentAndPostIt' ));

        // we need a hook for finding out the current selected shipping method, when the user reselects a new one
        //

    }

    /**
     * scope is to retrieve the parcel list
     */

    public function getItellaTerminals() {
        $json = file_get_contents('https://monte360.com/itella/index.php?action=displayParcelsList');
        $obj = json_decode($json);
        return $obj->{'item'};
    }


    /**
     * check if the shipping method is mdn to display list of terminals
     */

    public function getCurrentShippingMethod(): bool {


        $shipping_methods = wc_get_chosen_shipping_method_ids();


        //print_r($methods[0]);
        if($shipping_methods[0] === 'itella_self_service_by_modena') {
            print_r("<br>Yep, is mdn-s: ". $shipping_methods[0]);
            return true;
        } else {
            print_r( "<br>Nope, is not mdn-s: ". $shipping_methods[0]);
            return false;
        }
    }

    /**
     * populate shipping terminal selection box field
     */

    public function renderCheckoutSelection() {
        $terminalList = $this->getItellaTerminals();

        if($this->getCurrentShippingMethod()) {
            echo '
                <select class="mdn-shipping-selection" name="userShippingSelection" id="userShippingSelectionChoice">
                <option disabled value="110" selected="selected">-- Palun vali pakiautomaat --</option>';
            for($x=0; $x<=count($terminalList)-1; $x++) {
                $terminalID = $terminalList[$x]->{'place_id'};
                print_r( "<option value=$terminalID>" . $terminalList[$x]->{'name'} . " - " . $terminalList[$x]->{'address'}  . " - " . $terminalList[$x]->{'place_id'}  . "<br></option>");
            }
            echo '</select>';
        }
        return $terminalList;
    }


    public function findUserShipmentAndPostIt(): void {
        echo '
            <script type="text/javascript">
                var e = document.getElementById("userShippingSelectionChoice");
                var value = e.value;
                var userShippingLocation = e.options[e.selectedIndex].text;
                var terminalID = document.getElementById("userShippingSelectionChoice").value;
                var uniqueID = Date.now();
                
             fetch("https://jsonplaceholder.typicode.com/posts", {
                  method: "POST",
                  body: JSON.stringify({
                    requestID: uniqueID,
                    terminalID: terminalID,
                    userShippingLocation: userShippingLocation,
                    //var merchantOrderNo = $order_id;
                  }),
                  headers: {
                    "Content-type": "application/json; charset=UTF-8"
                  }
                })
                .then((response) => response.json())
                .then((json) => console.log(json));;
                
            </script>';
    }
}
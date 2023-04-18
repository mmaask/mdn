<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Modena_Shipping_Itella_Terminals extends Modena_Shipping_Itella {

    public function __construct($instance_id = 0) {
        parent::__construct($instance_id);
        $this->id = 'modena-shipping-itella-terminals';
        $this->title = __('Itella pakiterminal', 'woocommerce');
        $this->method_title = __('Itella pakiterminal', 'woocommerce');
        $this->method_description = __('Itella pakiterminalide lahendus Modenalt', 'woocommerce');
        $this->cost = 5;

        add_action('woocommerce_checkout_update_order_review', array($this, 'isShippingMethodAvailable'));
        add_action('wp_enqueue_scripts', array($this, 'enqueueParcelTerminalSearchBoxAssets'));

        add_action('woocommerce_review_order_before_payment', array($this, 'renderParcelTerminalSelectBox'));
        add_action('woocommerce_review_meta', array($this, 'createOrderParcelMetaData'));
        add_action('woocommerce_get_order_item_totals', array($this, 'addParcelTerminalToCheckoutDetails'), 10, 2);
        add_action('woocommerce_thankyou', array($this, 'preparePOSTrequestForBarcodeID'));
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'renderParcelTerminalInAdminOrder'));
    }

    public function isShippingMethodAvailable($rates, $package) {
        if (!$this->isOrderSuitableForShipping($package)) {
            unset($rates[$this->id]);
        }
        return $rates;
    }

    public function isOrderSuitableForShipping($package): bool {
        $packageMaxCapacityKG = 35;
        $toSanitizePackageMinDimensionsCM = [1, 15, 15];
        $toSanitizePackageMaxDimensionsCM = [60, 36, 60];

        $wooCommerceTotalCartWeight = WC()->cart->get_cart_contents_weight();
        $shippingDestinationCountry = $package['destination']['country'] ?? '';
        if ($shippingDestinationCountry !== 'EE') {
            return false;
        }

        if ($wooCommerceTotalCartWeight > $packageMaxCapacityKG) {
            return false;
        }

        $sanitizedPackageMinDimensions = $this->sanitizeOrderProductDimensions($toSanitizePackageMinDimensionsCM);
        $sanitizedPackageMaxDimensions = $this->sanitizeOrderProductDimensions($toSanitizePackageMaxDimensionsCM);

        foreach ($package['contents'] as $item_id => $values) {
            $_product = $values['data'];
            $wooCommerceOrderProductDimensions = $_product->get_dimensions(false);

            if (empty($wooCommerceOrderProductDimensions['length']) || empty($wooCommerceOrderProductDimensions['width']) || empty($wooCommerceOrderProductDimensions['height'])) {
                return true;
            }

            if ($wooCommerceOrderProductDimensions['length'] < $sanitizedPackageMinDimensions[0] || $wooCommerceOrderProductDimensions['width'] < $sanitizedPackageMinDimensions[1] || $wooCommerceOrderProductDimensions['height'] < $sanitizedPackageMinDimensions[2]) {
                return false;
            }

            if ($wooCommerceOrderProductDimensions['length'] > $sanitizedPackageMaxDimensions[0] || $wooCommerceOrderProductDimensions['width'] > $sanitizedPackageMaxDimensions[1] || $wooCommerceOrderProductDimensions['height'] > $sanitizedPackageMaxDimensions[2]) {
                return false;
            }
        }
        return true;
    }

    /**
     * @throws Exception
     */
    public function getParcelTerminalsHTTPrequest(): bool|string {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://monte360.com/itella/index.php?action=displayParcelsList');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Set to true if you want to verify SSL certificate
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // Set to 2 to check that common name exists and matches the hostname provided

        $GETrequestResponse = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log('Something went wrong with curling the terminals list. Sorry. ');
            throw new Exception('Error: ' . curl_error($ch));
        }
        curl_close($ch);
        return $GETrequestResponse;
    }

    /**
     * @throws Exception
     */
    public function parseParcelTerminalsJSON() {
        $parcelTerminalsJSON = $this->getParcelTerminalsHTTPrequest();
        return json_decode($parcelTerminalsJSON)->item;
    }

    /**
     * @throws Exception
     */
    public function getParcelTerminals() {
        return $terminalList = $this->parseParcelTerminalsJSON();
    }

    function enqueueParcelTerminalSearchBoxAssets() {
        wp_register_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css');
        wp_enqueue_style('select2');
        wp_register_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js', array('jquery'), '4.1.0-rc.0', true);
        wp_enqueue_script('select2');
    }

    /**
     * @throws Exception
     */
    public function renderParcelTerminalSelectBox() {
        static $showOnce = false;

        ?>
        <div class="mdn-shipping-select-wrapper" style="margin-bottom: 15px">
            <label for="mdn-shipping-select-box"></label>
            <select name="userShippingSelection" id="mdn-shipping-select-box" data-method-id="<?php echo $this->id; ?>" style="width: 100%; height: 400px;">
                <option disabled selected="selected"></option>
                <?php
                $terminalList = $this->getParcelTerminals();

                $cities = array();
                foreach ($terminalList as $terminal) {
                    $cities[$terminal->{'city'}][] = $terminal;
                }

                foreach ($cities as $city => $terminals) {
                    echo "<optgroup label='$city'>";
                    foreach ($terminals as $terminal) {
                        $terminalID = $terminal->{'place_id'};
                        echo "<option value='$terminalID' >" . $terminal->{'name'} . " - " . $terminal->{'address'} . "</option>";
                    }
                    echo "</optgroup>";
                }
                ?>
            </select>
        </div>
        <?php
        $showOnce = true;
    }

    /**
     * @throws Exception
     */
    public function createOrderParcelMetaData($order_id) {
        error_log('createOrderParcelMetaData called with order_id: ' . $order_id);

        $order = wc_get_order($order_id);
        if ($order instanceof WC_Order) {
            $selected_parcel_terminal = sanitize_text_field($_POST['userShippingSelection']);
            error_log('Selected parcel terminal: ' . $selected_parcel_terminal);

            $order->add_meta_data('_selected_parcel_terminal_id', $selected_parcel_terminal, true);
            $order->save();
            error_log('Selected parcel terminal metadata saved for order_id: ' . $order_id);
        } else {
            error_log('Could not fetch the order with the provided order ID: ' . $order_id);
            throw new Exception("Could not fetch the order with the provided order ID.");
        }
    }


    /**
     * @throws Exception
     */
    public function addParcelTerminalToCheckoutDetails($totals, $order) {
        $order_id = $order->get_id();
        $parcel_terminal = $this->getOrderParcelTerminalID($order_id);

        if ($parcel_terminal) {
            $new_totals = [];

            foreach ($totals as $key => $total) {
                $new_totals[$key] = $total;

                if ($key === 'shipping') {
                    $new_totals['parcel_terminal'] = [
                        'label' => __('Valitud pakiterminal:', 'woocommerce'),
                        'value' => $parcel_terminal,
                    ];
                }
            }

            $totals = $new_totals;
        }

        return $totals;
    }

    /**
     * @throws Exception
     */
    public function preparePOSTrequestForBarcodeID($order_id) {

        $order = wc_get_order($order_id);
        $orderReference = $order->get_order_number();

        $recipientName = WC()->customer->get_billing_first_name() . ' class-modena-shipping-itella-terminals.php' . WC()->customer->get_billing_last_name();
        $recipientPhone = WC()->customer->get_shipping_phone();
        $recipientEmail = WC()->customer->get_billing_email();
        $placeId = $this->getOrderParcelTerminalID($orderReference);
        $result = $this->getOrderTotalWeightAndContents($order);
        $weight = $result['total_weight'];
        $packageContent = $result['packageContent'];

        $data = array(
            'orderReference' => $orderReference,
            'packageContent' => $packageContent,
            'weight' => $weight,
            'recipient_name' => $recipientName,
            'recipient_phone' => $recipientPhone,
            'recipientEmail' => $recipientEmail,
            'placeId' => $placeId,

            );
        $this->barcodePOSTrequest($data, $order);

    }

    /**
     * @throws Exception
     */
    public function barcodePOSTrequest($data, $order) {
        $curl = curl_init('https://monte360.com/itella/index.php?action=createShipment');

        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 5); // Add a 5-second wait time for the response
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));

        $POSTrequestResponse = curl_exec($curl);

        if (curl_errno($curl)) {
            error_log('Error in POST response: ' . curl_error($curl));
        } else {
            $this->barcodeJSONparser($POSTrequestResponse, $order);
        }
        curl_close($curl);
    }

    /**
     * @throws Exception
     */
    public function barcodeJSONparser($POSTrequestResponse, $order) {

        if (is_null($POSTrequestResponse)) {
            error_log('Response is NULL. Exiting get_label function.');
            return;
        }

        $POSTrequestResponse = trim($POSTrequestResponse, '"');
        $array = json_decode($POSTrequestResponse, true);

        if (is_null($array) || !isset($array['item']['barcode'])) {
            error_log('Cannot access barcode_id. Invalid JSON or missing key in array.');
            return Null;
        }

        $parcelLabelBarcodeID = $array['item']['barcode'];
        $this->addBarcodeMetaDataToOrder($parcelLabelBarcodeID, $order);
    }

    /**
     * @throws Exception
     */
    public function addBarcodeMetaDataToOrder($parcelLabelBarcodeID, $order) {
        if (!$order instanceof WC_Order) {
            $order = wc_get_order($order);
        }
        if ($order instanceof WC_Order) {
            $order->add_meta_data('_barcode_id', $parcelLabelBarcodeID, true);
            $order->save();
        } else {
            error_log('Could not fetch the order with the provided order ID.');
            throw new Exception("Could not fetch the order with the provided order ID.");
        }
    }

    public function labelGETrequest($parcelLabelBarcodeID): void {
        $curl = curl_init('https://monte360.com/itella/index.php?action=getLabel&barcode=' . $parcelLabelBarcodeID);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);

        $LabelResponse = curl_exec($curl);

        if (curl_errno($curl)) {
            error_log('Error in GET response: ' . curl_error($curl));
        } else {
            $this->getLinkToLabelPrintDialog($LabelResponse);
        }
        curl_close($curl);
    }

    public function createPrintDialogInAdminOrders($LabelResponse) {

        return $this->print->dialog($LabelResponse);

        // ÖÖP
    }

    public function getLinkToLabelPrintDialog($LabelResponse) {

        $linkToLabelPDF = $this->createPrintDialogInAdminOrders();

        return $linkToLabelPDF;
    }

    /**
     * @throws Exception
     */
    public function renderParcelTerminalInAdminOrder($order_id) {
        static $showOnce = false;

        ?>
        <tr class="selected-terminal">
            <th>
                <h3>
                    <?php _e('Saadetise pakiterminal', 'woocommerce'); ?>
                </h3>
            </th>
            <td>
                <p>
                    <?php echo $this->getOrderParcelTerminalID($order_id); ?>
                </p>
                <p>
                    <b><a href="#" >Prindi pakikaart</a></b>
                </p>
            </td>
        </tr>
        <?php
        $showOnce = true;
    }

    /**
     * @throws Exception
     */
    public function getOrderParcelTerminalID($order_id): ?string
    {
        $order = wc_get_order($order_id);
        if ($order instanceof WC_Order) {

            $selected_parcel_terminal_id = $order->get_meta('_selected_parcel_terminal_id', true);
            error_log("is empty? : " . $selected_parcel_terminal_id);

            if(!$selected_parcel_terminal_id) {
                $parcelTerminalText = $this->getOrderParcelTerminalText(110);
            } else {
                $parcelTerminalText = $this->getOrderParcelTerminalText($selected_parcel_terminal_id);
            }
            return $parcelTerminalText;
        }
        return '';
    }

    /**
     * @throws Exception
     */
    public function getOrderParcelTerminalText($selectedParcelTerminalID) {
        $parcelTerminals = $this->getParcelTerminals();
        foreach ($parcelTerminals as $parcelTerminal) {
            $parcelTerminalID = $parcelTerminal->{'place_id'};
            if ($selectedParcelTerminalID = $parcelTerminalID) {
                return $parcelTerminal->{'name'} . " - " . $parcelTerminal->{'address'};
            }
            return 'Veateade - pakiterminalide listile ei pääsetud ligi.';
        }
    }
}

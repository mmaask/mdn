<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Modena_Shipping_Itella_Terminals extends Modena_Shipping_Method {

    public $cost;

    public function __construct($instance_id = 0) {
        parent::__construct($instance_id);
        $this->id = 'modena-shipping-itella-terminals';
        $this->init_form_fields();
        $this->cost = floatval($this->get_option('cost'));
        $this->setNamesBasedOnLocales(get_locale());
        $this->init_hooks();
    }

    public function setNamesBasedOnLocales($current_locale) {
        switch ($current_locale) {
            case 'en_GB' && 'en_US':
                $this->method_title             = 'Smartpost Estonia';
                $this->method_description = __('Itella Smartpost parcel terminals', 'woocommerce');
                $this->title                    = 'Smartpost Estonia';
                break;
            case 'ru_RU':
                $this->method_title             = 'Ителла Смартпост';
                $this->method_description = __('Ителла Смартпост почтовых терминалов', 'woocommerce');
                $this->title                    = 'Ителла Смартпост';
                break;
            default:
                $this->method_description = __('Itella Smartpost pakiterminalid', 'woocommerce');
                $this->method_title = __('Itella Smartpost - Modena', 'woocommerce');
                $this->title                    = 'Smartpost Eesti';
                break;
        }
    }

    public function init_form_fields(): void {
        $cost_desc = __('Enter a cost (excl. tax) or sum, e.g. <code>10.00 * [qty]</code>.') . '<br/><br/>' . __('Use <code>[qty]</code> for the number of items, <br/><code>[cost]</code> for the total cost of items, and <code>[fee percent="10" min_fee="20" max_fee=""]</code> for percentage based fees.');

        $this->instance_form_fields = array(

            'cost' => array(
                'title' => __('Cost'),
                'type' => 'float',
                'placeholder' => '',
                'description' => $cost_desc,
                'default' => 4.99,
                'desc_tip' => true,
                'sanitize_callback' => array($this, 'sanitizeshippingMethodCost'),
            ),
        );
    }

    public function init_hooks() {
        add_action('woocommerce_checkout_update_order_review', array($this, 'isShippingMethodAvailable'));
        add_action('woocommerce_review_order_before_payment', array($this, 'renderParcelTerminalSelectBox'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'createOrderParcelMetaData'));
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

        foreach ($package['contents'] as $values) {
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
    public function getParcelTerminalsHTTPrequest(): string {
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

        $parcelTerminals = $this->parseParcelTerminalsJSON();

        if(empty($parcelTerminals)) {
            echo '<b><span style="color:red">Veateade - pakiterminalide listile ei pääsetud ligi.</span></b> ';
            error_log("Veateade - pakiterminalide listile ei pääsetud ligi.");
        }

        return $parcelTerminals;
    }

    /**
     * @throws Exception
     */
    public function renderParcelTerminalSelectBox() {
        static $showOnce = false;

        if(!$showOnce) {
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
        }

        $showOnce = true;
    }

    /**
     * @throws Exception
     */
    public function createOrderParcelMetaData($order_id) {
        error_log('createOrderParcelMetaData called with order_id: ' . $order_id);

        $order = wc_get_order($order_id);
        if ($order instanceof WC_Order) {
            $shipping_methods = $order->get_shipping_methods();

            $orderShippingMethodID = '';
            if (!empty($shipping_methods)) {
                $first_shipping_method = reset($shipping_methods);
                $orderShippingMethodID = $first_shipping_method->get_method_id();
            }

            if ($orderShippingMethodID == $this->id) {
                $selected_parcel_terminal = sanitize_text_field($_POST['userShippingSelection']);
                error_log("See on valitud pakipunkt: " . $selected_parcel_terminal);
                if(empty($selected_parcel_terminal)) {
                    error_log("Veateade - Pakipunkti ID ei leitud.");
                }

                error_log('Selected parcel terminal: ' . $selected_parcel_terminal);

                $order->add_meta_data('_selected_parcel_terminal_id', $selected_parcel_terminal, true);
                $order->save();
                error_log('Selected parcel terminal metadata saved for order_id: ' . $order_id);
            } else {
                error_log('The order shipping method does not match. Skipping metadata creation for order_id: ' . $order_id);
            }
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
        $shipping_methods = $order->get_shipping_methods();
        $orderShippingMethodID = '';
        if (!empty($shipping_methods)) {
            $first_shipping_method = reset($shipping_methods);
            $orderShippingMethodID = $first_shipping_method->get_method_id();
        }
        if ($orderShippingMethodID == $this->id) {
            $wcOrderParcelTerminalID = $order->get_meta('_selected_parcel_terminal_id');
            if(empty($wcOrderParcelTerminalID)) {
                error_log('Veateade - Tellimusel puudub pakipunkti ID '  . $wcOrderParcelTerminalID);
                echo '<b><span style="color:red">Veateade - Tellimusel puudub salvestatud pakipunkti ID</span></b>';
                $wcOrderParcelTerminalID = 110;
            }

            $parcel_terminal = $this->getOrderParcelTerminalText($wcOrderParcelTerminalID);

            $new_totals = [];

            foreach ($totals as $key => $total) {
                $new_totals[$key] = $total;

                if ($key === 'shipping') {
                    $new_totals['parcel_terminal'] = [
                        'label' => apply_filters('gettext', 'Smartpost pakipunkt', 'selectedParcelTerminal', 'mdn-translations'),
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
    public function getOrderParcelTerminalText($wcOrderParcelTerminalID) {
        $parcelTerminals = $this->getParcelTerminals();

        foreach ($parcelTerminals as $parcelTerminal) {
            $parcelTerminalID = $parcelTerminal->{'place_id'};
            if ($parcelTerminalID == $wcOrderParcelTerminalID) {
                return $parcelTerminal->{'name'} . " - " . $parcelTerminal->{'address'};
            }
        }
    }
    /**
     * @throws Exception
     */
    public function preparePOSTrequestForBarcodeID($order_id) {

        $order = wc_get_order($order_id);
        $shipping_methods = $order->get_shipping_methods();

        $orderShippingMethodID = '';
        if (!empty($shipping_methods)) {
            $first_shipping_method = reset($shipping_methods);
            $orderShippingMethodID = $first_shipping_method->get_method_id();
        }

        if ($orderShippingMethodID != $this->id) {
            return;
        }

        $orderReference = $order->get_order_number();

        $recipientName = WC()->customer->get_billing_first_name() . " " . WC()->customer->get_billing_last_name();
        $recipientPhone = WC()->customer->get_shipping_phone();
        $recipientEmail = WC()->customer->get_billing_email();
        $wcOrderParcelTerminalID = $order->get_meta('_selected_parcel_terminal_id');
        if(empty($wcOrderParcelTerminalID)) {
            error_log('Veateade - Tellimusel puudub salvestatud pakipunkti ID, et alustada POST päringut'  . $wcOrderParcelTerminalID);
            echo '<b><span style="color:red">Veateade - Tellimusel puudub salvestatud pakipunkti ID, et alustada POST päringut</span></b>';
            $wcOrderParcelTerminalID = 110;
        }

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
            '$wcOrderParcelTerminalID' => $wcOrderParcelTerminalID,
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
            echo '<b><span style="color:red">Veateade - POST päringul tegemisel ilmnes viga. </span></b> ' . curl_error($curl);
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
            echo '<b><span style="color:red">Veateade - barcode_id polnud kättesaadav. </span></b> ';
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
            $debugString = "WIN - Lisasime Barcode ID tellimuse külge " . $parcelLabelBarcodeID;
            //echo '<br><b><span style="color:green">' . $debugString . '</span></b>';
            error_log("WIN - Lisasime Barcode ID tellimuse külge " . $parcelLabelBarcodeID);
            $order->add_meta_data('_barcode_id', $parcelLabelBarcodeID, true);
            $order->save();
        } else {
            error_log('Could not fetch the order with the provided order ID.');
            throw new Exception("Could not fetch the order with the provided order ID.");
        }
    }


    /**
     * @throws Exception
     */
    public function renderParcelTerminalInAdminOrder($order_id) {
        $order = wc_get_order($order_id);
        $shipping_methods = $order->get_shipping_methods();

        $first_shipping_method = reset($shipping_methods);
        $orderShippingMethodID = $first_shipping_method->get_method_id();

        if(!$orderShippingMethodID || $orderShippingMethodID != $this->id) {
            return;
        }

        static $showOnce = false;

        if($showOnce) {
            return;
        }

        $wcOrderParcelTerminalID = $order->get_meta('_selected_parcel_terminal_id');
        if(empty($wcOrderParcelTerminalID)) {
            error_log('Veateade - Tellimusel puudub salvestatud pakipunkti ID '  . $wcOrderParcelTerminalID);
            echo '<b><span style="color:red">Veateade - Tellimusel puudub salvestatud pakipunkti ID</span></b>';
            $wcOrderParcelTerminalID = 110;
        }
        $labelPDF =$this->getPDF($order);

        ?>
        <tr class="selected-terminal">
            <th>
                <h3>
                    <?php _e(apply_filters('gettext', 'Smartpost pakipunkt', 'selectedParcelTerminal', 'mdn-translations')); ?>
                </h3>
            </th>
            <td>
                <p>
                    <?php echo $this->getOrderParcelTerminalText($wcOrderParcelTerminalID); ?>
                </p>

                <p>

                    <b>
                        <a id="clickedonevent" href="#">
                            <?php echo esc_html(__('Prindi pakisilt', 'mdn-translations')); ?>
                        </a>
                        <script>
                            document.getElementById('clickedonevent').addEventListener('click', function(event) {
                                event.preventDefault();
                                var printUrl = '<?php echo esc_html($labelPDF); ?>';
                                var printWindow = window.open(printUrl, '_blank');
                                printWindow.addEventListener('load', function() {
                                    printWindow.document.body.style.zoom = "175%";
                                    printWindow.document.title = 'Print PDF';
                                    setTimeout(function() {
                                        var iframe = printWindow.document.createElement('iframe');
                                        iframe.src = printUrl;
                                        iframe.style.display = 'none';
                                        printWindow.document.body.appendChild(iframe);
                                        iframe.onload = function() {
                                            iframe.contentWindow.print();
                                            printWindow.document.body.removeChild(iframe);
                                        };
                                    }, 1000);
                                });
                            });
                        </script>
                    </b>
                </p>
            </td>
        </tr>
        <?php
        $showOnce = true;
    }

    public function getPDF($order): string {
        static $showOnce = false;

        if($showOnce) {
            return 0;
        }

        $pdfUrl = 'https://monte360.com/itella/index.php?action=getLable&barcode=' . $order->get_meta('_barcode_id'); // Replace this with your actual PDF URL

        $uploads = wp_upload_dir();
        $tempFolderPath = trailingslashit($uploads['path']); // Use 'path' instead of 'basedir' to get the correct directory for uploads
        $tempFileName = uniqid() . '.pdf';
        $tempFilePath = $tempFolderPath . $tempFileName;

        // Initialize a new cURL session
        $ch = curl_init($pdfUrl);

        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Return the transfer as a string
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // Follow redirects

        // Execute the cURL session and fetch the content
        $pdfContent = curl_exec($ch);

        // Check for errors
        if ($pdfContent === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL error: {$error}");
        }

        // Close the cURL session
        curl_close($ch);

        // Save the fetched content as a PDF file
        $bytes_written = file_put_contents($tempFilePath, $pdfContent);

        // Check if the file was created successfully
        if ($bytes_written === false) {
            throw new Exception("Failed to create temporary PDF file: {$tempFilePath}");
        }

        $showOnce = true;
        // Return the public URL for the temporary PDF file
        return trailingslashit($uploads['url']) . $tempFileName;
    }

    /**
     * @return float
     */
    public function getCost(): float {
        return $this->cost;
    }
}

<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Modena_Shipping_Itella_Terminals extends Modena_Shipping_Method {

    public $cost;
    protected $smartpostMachines;
    protected $adjustParcelTerminalInAdminPlaceholder;
    protected $placeholderForSelectBoxLabel;
    protected $shorthandForTitle;
    protected $addBarcodeMetaDataNotePlaceholderText;
    protected $createOrderParcelMetaDataPlaceholderText;
    protected $updateParcelTerminalNewTerminalNote;
    protected $updateParcelTerminalOldTerminalNote;

    /**
     * @throws Exception
     */
    public function __construct($instance_id = 0) {
        parent::__construct($instance_id);
        $this->id = 'modena-shipping-itella-terminals';
        $this->init_form_fields();
        $this->cost = floatval($this->get_option('cost'));
        $this->setNamesBasedOnLocales(get_locale());
        $this->getParcelTerminals();
        $this->init_hooks();
    }

    public function setNamesBasedOnLocales($current_locale) {
        switch ($current_locale) {
            case 'en_GB' && 'en_US':
                $this->method_title             = 'Smartpost Estonia';
                $this->method_description               = __('Itella Smartpost parcel terminals', 'woocommerce');
                $this->title                              = 'Smartpost Estonia';
                $this->placeholderPrintLabelInAdmin             = "Download Smartpost label";
                $this->printLabelPlaceholderInBulkActions       = "Download Itella Smartpost Parcel Labels";
                $this->adjustParcelTerminalInAdminPlaceholder        = "Update";
                $this->placeholderForSelectBoxLabel             = "Select parcel terminal";
                $this->shorthandForTitle                                            = "Smartpost";
                $this->addBarcodeMetaDataNotePlaceholderText        = $this->shorthandForTitle . " barcode has been created and label is available to Download. ";
                $this->createOrderParcelMetaDataPlaceholderText     = $this->shorthandForTitle . " parcel terminal is selected: ";
                $this->updateParcelTerminalNewTerminalNote          = " parcel terminal has been updated to: ";
                $this->updateParcelTerminalOldTerminalNote          = " parcel terminal was: ";

                break;
            case 'ru_RU':
                $this->method_title             = 'Ителла Смартпост';
                $this->method_description = __('Ителла Смартпост почтовых терминалов', 'woocommerce');
                $this->title                    = 'Ителла Смартпост';
                $this->placeholderPrintLabelInAdmin = "Распечатать ярлык в админке";
                $this->printLabelPlaceholderInBulkActions = "Download Itella Smartpost Parcel Labels";
                break;
            default:
                $this->method_description = __('Itella Smartpost pakiterminalid', 'woocommerce');
                $this->method_title = __('Itella Smartpost - Modena', 'woocommerce');
                $this->title                    = 'Smartpost Eesti';
                $this->placeholderPrintLabelInAdmin = "Prindi pakisilt";
                $this->printLabelPlaceholderInBulkActions = "Lae alla Itella Smartpost pakisildid";
                $this->placeholderForSelectBoxLabel = "Vali pakipunkt";
                break;
        }
    }

    public function init_form_fields()
    {
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

    /**
     * @throws Exception
     */
    public function init_hooks() {
        add_action('woocommerce_checkout_update_order_review', array($this, 'isShippingMethodAvailable'));
        add_action('woocommerce_review_order_before_payment', array($this, 'renderParcelTerminalSelectBox'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'createOrderParcelMetaData'));
        add_action('woocommerce_get_order_item_totals', array($this, 'addParcelTerminalToCheckoutDetails'), 10, 2);
        add_action('woocommerce_thankyou', array($this, 'preparePOSTrequestForBarcodeID'));
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'renderParcelTerminalInAdminOrder'));
        add_filter('woocommerce_order_actions', array($this, 'add_custom_order_action'));
        add_action('woocommerce_order_action_custom_order_action', array($this, 'process_custom_order_action'));
        add_filter('bulk_actions-edit-shop_order', array($this, 'register_custom_bulk_action'));
        add_filter('handle_bulk_actions-edit-shop_order', array($this,  'process_custom_bulk_action', 10, 3));
        add_action('admin_notices',  array($this, 'custom_bulk_action_admin_notice'));
        //add_action( 'woocommerce_admin_order_data_after_shipping_address',array($this, 'renderParcelTerminalSelectBox' ));


    }

    public function custom_bulk_action_admin_notice()
        {
            if (!empty($_REQUEST['bulk_send_samples'])) {
                $count = intval($_REQUEST['bulk_send_samples']);
                printf('<div id="message" class="updated notice is-dismissible"><p>' .
                    _n('%s sample label has been downloaded.',
                        '%s sample labels have been downloaded.',
                        $count,
                        'woocommerce') . '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>', $count);
            }
        }

    public function add_custom_bulk_action() {
        global $post_type;

        static $bass = 0;

        if($bass == 1) {
            return;
        }

        if ('shop_order' == $post_type) {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function() {
                    jQuery('<option>').val('mark_as_shipped').text('<?php _e($this->printLabelPlaceholderInBulkActions, 'woocommerce'); ?>').appendTo("select[name='action']");
                });
            </script>
            <?php


        }
        $bass += 1;
    }

    /**
     * @throws Exception
     */
    function process_custom_bulk_action($redirect_to, $action, $post_ids): string
        {
            if ($action !== 'custom_order_bulk_action') {
                return $redirect_to;
            }

            foreach ($post_ids as $post_id) {
                $order = wc_get_order($post_id);

                if ($order) {
                    // Your sample sending logic here
                    // ...
                    $this->saveLabelPDFinUser($order);

                    // Add a note to the order indicating the action was executed
                    $order_note = "Smartpost: " . ' label has been downloaded.';
                    $order->add_order_note($order_note);


                }
            }

            // Redirect back to the orders list with a success message
            return add_query_arg(array(
                'bulk_send_samples' => count($post_ids),
                'ids' => join(',', $post_ids),
            ), $redirect_to);
        }

    public function register_custom_bulk_action($bulk_actions)
        {
            $bulk_actions['custom_order_bulk_action'] = __($this->placeholderPrintLabelInAdmin, 'woocommerce');
            return $bulk_actions;
        }


    public function add_custom_order_action($actions)
    {
        $actions['custom_order_action'] = __($this->placeholderPrintLabelInAdmin, 'woocommerce');
        return $actions;
    }

    /**
     * @throws Exception
     */
    public function process_custom_order_action($order) {
        $order_note = "Smartpost label has been downloaded. (" . $this->getOrderParcelTerminalText($order->get_meta('_selected_parcel_terminal_id_mdn')) . ")";
        $order->add_order_note($order_note);
        $this->saveLabelPDFinUser($order);
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
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
        $this->smartpostMachines = $parcelTerminals;
    }

    /**
     * @throws Exception
     */
    public function renderParcelTerminalSelectBox() {
        static $showOnce = false;

        if(!$showOnce) {
            ?>
            <div class="mdn-shipping-select-wrapper" style="margin-bottom: 15px">
                <label  for="mdn-shipping-select-box"><?php echo $this->placeholderForSelectBoxLabel ?></label>
                <select name="userShippingSelection" id="mdn-shipping-select-box" data-method-id="<?php echo $this->id; ?>" style="width: 100%; height: 400px;">
                    <option disabled selected="selected"></option>
                    <?php
                    $cities = array();
                    foreach ($this->smartpostMachines as $terminal) {
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

        static $doOnce = False;
        if($doOnce){
            return;
        }
        $doOnce = True;

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

                $order->add_meta_data('_selected_parcel_terminal_id_mdn', $selected_parcel_terminal, true);
                $order->save();

                $order->add_order_note($this->createOrderParcelMetaDataPlaceholderText . $this->getOrderParcelTerminalText($order->get_meta('_selected_parcel_terminal_id_mdn')));

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

        $shipping_methods = $order->get_shipping_methods();
        $orderShippingMethodID = '';
        if (!empty($shipping_methods)) {
            $first_shipping_method = reset($shipping_methods);
            $orderShippingMethodID = $first_shipping_method->get_method_id();
        }
        if ($orderShippingMethodID == $this->id) {
            $wcOrderParcelTerminalID = $order->get_meta('_selected_parcel_terminal_id_mdn');
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
                        'label' => $this->title,
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

        $parcelTerminalsById = array_column($this->smartpostMachines, null, 'place_id');

        if (isset($parcelTerminalsById[$wcOrderParcelTerminalID])) {
            $parcelTerminal = $parcelTerminalsById[$wcOrderParcelTerminalID];

            //return $parcelTerminal->{'name'} . " - " . $parcelTerminal->{'address'};
            return $parcelTerminal->{'name'};

        }
        return error_log("Terminal not found by ID" . $wcOrderParcelTerminalID);
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

        $recipientName = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
        $recipientEmail = $order->get_billing_email();
        $recipientPhone = $order->get_billing_phone();

        if(empty($order->get_meta('_selected_parcel_terminal_id_mdn'))) {
            error_log('Veateade - Tellimusel puudub salvestatud pakipunkti ID, et alustada POST päringut'  . $order->get_meta('_selected_parcel_terminal_id_mdn'));
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
            '$wcOrderParcelTerminalID' => $order->get_meta('_selected_parcel_terminal_id_mdn'),
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

            error_log("WIN - Lisasime Barcode ID tellimuse külge " . $parcelLabelBarcodeID);
            $order->add_meta_data('_barcode_id_mdn', $parcelLabelBarcodeID, true);


            $order->add_order_note($this->addBarcodeMetaDataNotePlaceholderText . $order->get_meta('_barcode_id_mdn') . " (" .  $this->getOrderParcelTerminalText($order->get_meta('_selected_parcel_terminal_id_mdn')) . ").");
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

        if(empty($order->get_meta('_selected_parcel_terminal_id_mdn'))) {
            error_log('Veateade - Tellimusel puudub salvestatud pakipunkti ID '  . $order->get_meta('_selected_parcel_terminal_id_mdn'));
            echo '<b><span style="color:red">Veateade - Tellimusel puudub salvestatud pakipunkti ID</span></b>';
        }

        ?>
        <tr class="selected-terminal">
            <th>
                <h3>
                    <?php echo $this->title ?>
                </h3>
            </th>
            <td>
                <p>
                    <?php echo $this->getOrderParcelTerminalText($order->get_meta('_selected_parcel_terminal_id_mdn')); ?>

                </p>
                <button id="buttonForClicking" onClick="startUpdatingOrderParcel()" class="button grant-access"><?php _e($this->adjustParcelTerminalInAdminPlaceholder)?></button>

                <script>
                    document.getElementById("buttonForClicking").addEventListener("click", startUpdatingOrderParcel);

                    function startUpdatingOrderParcel() {

                        //todo open a list of locations,

                        <?php
                        //$this->updateParcelTerminalForOrder($order, $order_id);
                        ?>
                    }
                </script>
            </td>
        </tr>
        <?php

        $showOnce = true;
    }

    /**
     * @throws Exception
     */
    public function updateParcelTerminalForOrder($order, $order_id) {

        static $runOnce = False;
        if ($runOnce) {
            return;
        }
        $runOnce = True;

        $order_note1 = $this->shorthandForTitle . $this->updateParcelTerminalOldTerminalNote . $this->getOrderParcelTerminalText($order->get_meta('_selected_parcel_terminal_id_mdn')) . ".";
        $order_note2 = $this->shorthandForTitle . $this->updateParcelTerminalNewTerminalNote . $this->getOrderParcelTerminalText($order->get_meta('_selected_parcel_terminal_id_mdn'));

        $order->add_order_note($order_note1);
        $order->add_order_note($order_note2);

        $this->preparePOSTrequestForBarcodeID($order_id);
    }



    /**
     * @throws Exception
     */
    public function saveLabelPDFinUser($order) {

        $pdfUrl = 'https://monte360.com/itella/index.php?action=getLable&barcode=' . $order->get_meta('_barcode_id_mdn');
        $tempFileName = $order->get_meta('_barcode_id_mdn') . '.pdf';

        $ch = curl_init($pdfUrl);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $pdfContent = curl_exec($ch);

        if ($pdfContent === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL error: $error");
        }
        curl_close($ch);

        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename=' . $tempFileName);
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($pdfContent));
        ob_clean(); // Clean any pre-existing output buffers
        flush();
        echo $pdfContent;
        exit;
    }

    /**
     * @return float
     */
    public function getCost(): float {
        return $this->cost;
    }
}

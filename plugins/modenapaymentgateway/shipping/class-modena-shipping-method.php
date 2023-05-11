<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

abstract class Modena_Shipping_Method extends WC_Shipping_Method {

    protected $placeholderPrintLabelInAdmin;
    protected  $printLabelPlaceholderInBulkActions;
    protected $clientAPIkey;
    protected $clientAPIsecret;
    protected $urltoJSONlist;
    protected $maxCapacityForTerminal;
    protected $pathToLocalTerminalsFile;
    protected $urlToPackageLabel;
    protected $barcodePostURL;
    public $cost;

    public function __construct($instance_id = 0) {
        parent::__construct($instance_id);

        $this->instance_id = absint($instance_id);
        $this->supports           = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );
        $this->init();
        $this->init_form_fields();
        $this->enqueueAssests();
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
                'default' => 5.99,
                'desc_tip' => true,
                'sanitize_callback' => array($this, 'sanitizeshippingMethodCost'),
            ),
            'clientAPIkey' => array(
                'title' => __('API key'),
                'type' => 'text',
                'placeholder' => '',
                'description' => 'Package Provider account API secret',
                'desc_tip' => true,
            ),
            'clientAPIsecret' => array(
                'title' => __('API secret'),
                'type' => 'text',
                'placeholder' => '',
                'description' => 'Package Provider account API secret',
                'desc_tip' => true,
            ),
        );
    }

    public function init()
    {

        $this->title = $this->get_option('title');
        $this->cost = floatval($this->get_option('cost'));
        $this->clientAPIkey = $this->get_option('clientAPIkey');
        $this->clientAPIsecret = $this->get_option('clientAPIsecret');
        $this->placeholderPrintLabelInAdmin = "Download parcel label";

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'enqueueParcelTerminalSearchBoxAssets'));

        add_action('woocommerce_checkout_update_order_review', array($this, 'isShippingMethodAvailable'));

        add_action('woocommerce_checkout_update_order_meta', array($this, 'createOrderParcelMetaData'));

        add_action('woocommerce_get_order_item_totals', array($this, 'addParcelTerminalToCheckoutDetails'));
        add_action('woocommerce_thankyou', array($this, 'preparePOSTrequestForBarcodeID'));
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'renderParcelTerminalInAdminOrder'));

        add_filter('bulk_actions-edit-shop_order', array($this, 'register_custom_bulk_action'));
        add_filter('handle_bulk_actions-edit-shop_order', array($this,  'process_custom_bulk_action', 10, 3));
        add_action('admin_notices',  array($this, 'custom_bulk_action_admin_notice'));

        add_filter('woocommerce_order_actions', array($this, 'addPrintLabelCustomOrderAction'));
        add_action('woocommerce_order_action_custom_order_action', array($this, 'processPrintLabelCustomOrderAction'));

    }

    public function enqueueAssests() {
        if (!wp_script_is('jquery')) {
            wp_enqueue_script('jquery');
        }
        wp_enqueue_style('modena_shipping_style', MODENA_PLUGIN_URL . '/shipping/assets/modena-shipping.css');
        wp_enqueue_script('modena_shipping_script', MODENA_PLUGIN_URL . 'shipping/assets/modena-shipping.js', array('jquery'), '6.2', true);

        $translations = array(
            'please_choose_parcel_terminal' => __($this->getParcelTerminalDefaultTextTranslation(), 'mdn-translations')

        );

        wp_localize_script('modena_shipping_script', 'mdnTranslations', $translations);

        wp_register_style('select2', 'assets/select2/select2.min.css');
        wp_register_script('select2', 'assets/select2/select2.min.js', array('jquery'), true);

        wp_enqueue_style('select2');
        wp_enqueue_script('select2');

    }

    public function getParcelTerminalDefaultTextTranslation(): string
    {

        switch (get_locale()) {
            case 'en_GB' && 'en_US':
                return 'Select parcel terminal';
            case 'ru_RU':
                return 'Список почтовых терминалов';
            default:
                return 'Vali pakipunkt';
        }
    }

    public function calculate_shipping($package = array()) {

        $rate = array(
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $this->cost,
        );

        $this->add_rate($rate);
    }

    public function sanitizeshippingMethodCost($shippingMethodCost): float {
        $sanitizedshippingMethodCost = floatval($shippingMethodCost);
        if ($sanitizedshippingMethodCost < 0) {
            $sanitizedshippingMethodCost = $this->cost;
        }
        return $sanitizedshippingMethodCost;
    }

    public function getShippingMethodAndCompareItWithOrder($shippingMethodID, $order_id) {
        $order = wc_get_order($order_id);
        $shipping_methods = $order->get_shipping_methods();

        if (empty($shipping_methods)) {
            //error_log("Metadata not saved since order no shipping method: ");
            return False;
        }

        $first_shipping_method = reset($shipping_methods);
        $orderShippingMethodID = $first_shipping_method->get_method_id();

        //error_log("Comparing methods... " . $shippingMethodID . " with:  " . $orderShippingMethodID);

        if (empty($orderShippingMethodID)) {
            //error_log("Metadata not saved since order no shipping method with id: " . $orderShippingMethodID);
            return False;
        }

        if($orderShippingMethodID == $shippingMethodID) {
            //error_log("win, because methods are same named. saned.");
            return True;
        } else {
            //error_log("Metadata not saved: " . $shippingMethodID);
            return False;
        }
    }

    public function getParcelTerminalsHTTPrequest($urltoJSONlist) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $urltoJSONlist);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Set to true if you want to verify SSL certificate
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // Set to 2 to check that common name exists and matches the hostname provided
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Sets a timeout for cURL

        $GETrequestResponse = curl_exec($ch);

        if (curl_errno($ch)) {
            error_log('Something went wrong with curling the terminals list. Finding fallback from local file. ' . curl_error($ch) . $urltoJSONlist);
        }
        curl_close($ch);
        return $GETrequestResponse;
    }

    public function parseParcelTerminalsJSON($urltoJSONlist) {
        $parcelTerminalsJSON = $this->getParcelTerminalsHTTPrequest($urltoJSONlist);

        if(!$parcelTerminalsJSON) {
            $fallbackFile = MODENA_PLUGIN_PATH . $this->pathToLocalTerminalsFile;

            if (file_exists($fallbackFile) && is_readable($fallbackFile)) {
                return json_decode(file_get_contents($fallbackFile));
            } else {
                error_log('Fallback JSON file not found or not readable.');
            }
        }
        return json_decode($parcelTerminalsJSON);
    }

    public function getOrderTotalWeightAndContents($order): array {
        $packageContent = '';
        $total_weight = 0;

        foreach ($order->get_items() as $item) {
            if ($item instanceof WC_Order_Item_Product) {
                $product_name = $item->get_name();
                $quantity = $item->get_quantity();
                $packageContent .= $quantity . ' x ' . $product_name . "\n";

                $product = $item->get_product();
                $product_weight = $product->get_weight();
                $total_weight += $product_weight * $quantity;
            }
        }
        error_log("Total weight: " . $total_weight . " and package content: " . $packageContent);
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

    public function isOrderSuitableForShipping($package, $maxCapacityForTerminal) {
        $toSanitizePackageMinDimensionsCM = [1, 15, 15];
        $toSanitizePackageMaxDimensionsCM = [60, 36, 60];
        error_log("Finding results for isSuitableForShipping");
        $wooCommerceTotalCartWeight = WC()->cart->get_cart_contents_weight();
        $shippingDestinationCountry = $package['destination']['country'] ?? '';
        if ($shippingDestinationCountry !== 'EE') {
            return false;
        }

        if ($wooCommerceTotalCartWeight > $maxCapacityForTerminal) {
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

    public function createOrderParcelMetaData($order_id) {
        $order = wc_get_order($order_id);
        if ($this->getShippingMethodAndCompareItWithOrder($this->id, $order_id)) {
            if(!empty($order->get_meta('_selected_parcel_terminal_id_mdn'))) {
                error_log("Metadata already saved to order. Exiting early: " . sanitize_text_field($_POST['userShippingSelection-' . $this->id]));
                return;
            }

            $selected_parcel_terminal = sanitize_text_field($_POST['userShippingSelection-' . $this->id]);

            if(empty($selected_parcel_terminal)) {
                error_log("Veateade - Pakipunkti ID ei leitud. Not posting metadata.");
                return;
            }

            $order->add_meta_data('_selected_parcel_terminal_id_mdn', $selected_parcel_terminal, true);
            $order->save();
            error_log('SUCCESS.. Selected parcel terminal metadata ' . $order->get_meta('_selected_parcel_terminal_id_mdn') . ' saved for order_id: ' . $order_id);
        }
    }

    public function hasOrderGotPackagePointMetaData($order) {
        if(empty($order->get_meta('_selected_parcel_terminal_id_mdn'))) {
            error_log("oh no, order does not yet have a terminal.");
            return False;
        } else {
            return True;
        }
    }

    public function addParcelTerminalToCheckoutDetails($totals, $order_id)
    {
        error_log("Hello, does it get here?");
        $order = wc_get_order($order_id);
        if (!$this->hasOrderGotPackagePointMetaData($order)) return $totals;
        if ($this->getShippingMethodAndCompareItWithOrder($this->id, $order_id)) {
            $parcel_terminal = $this->getOrderParcelTerminalText($order->get_meta('_selected_parcel_terminal_id_mdn'));

            foreach ($totals as $key => $total) {
                if ($key === 'shipping') {
                    $totals['shipping']['value'] = $totals['shipping']['value'] . " (" . $parcel_terminal . ")";
                }
            }

        }
        return $totals;
    }

    public function returnTrueIfOrderPending($order) {
        if ($order->get_status() == 'pending') {
            return True;
        } else {
            return False;
        }
    }

    public function renderParcelTerminalInAdminOrder($order_id) {
        //error_log("Trying to run into admin orders");

        $order = wc_get_order($order_id);

        if ($this->returnTrueIfOrderPending($order)) return;

        if ($this->getShippingMethodAndCompareItWithOrder($this->id, $order_id)) {

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


                            <?php
                            //$this->updateParcelTerminalForOrder($order, $order_id);
                            ?>
                        }
                    </script>
                </td>
            </tr>
            <?php
        }

    }

    public function preparePOSTrequestForBarcodeID($order_id) {
        error_log("preparing post for order: " . $order_id);

        $order = wc_get_order($order_id);
        if ($this->getShippingMethodAndCompareItWithOrder($this->id, $order_id)) {
            if(empty($this->clientAPIkey) || empty($this->clientAPIsecret)) {
                error_log("clientAPIkey or clientAPIsecret not set, exiting posting barcodeID");
                return;
            }

            if(empty($order->get_meta('_selected_parcel_terminal_id_mdn'))) {
                error_log('Veateade - Tellimusel puudub salvestatud pakipunkti ID, et alustada POST päringut'  . $order->get_meta('_selected_parcel_terminal_id_mdn'));
            }

            $result = $this->getOrderTotalWeightAndContents($order);
            $weight = $result['total_weight'];
            $packageContent = $result['packageContent'];

            $data = array(
                'orderReference' => $order->get_order_number(),
                'packageContent' => $packageContent,
                'weight' => $weight,
                'recipient_name' => $order->get_billing_first_name() . " " . $order->get_billing_last_name(),
                'recipient_phone' => $order->get_billing_phone(),
                'recipientEmail' => $order->get_billing_email(),
                '$wcOrderParcelTerminalID' => $order->get_meta('_selected_parcel_terminal_id_mdn'),
                'clientAPIkey' => $this->clientAPIkey,
                'clientAPIsecret' => $this->clientAPIsecret,
            );

            $this->barcodePOSTrequest($data, $order, $this->barcodePostURL);
        }
    }

    public function barcodePOSTrequest($data, $order, $barcodePostURL) {

        $curl = curl_init($barcodePostURL);

        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 2); // Add a 5-second wait time for the response
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));

        $POSTrequestResponse = curl_exec($curl);

        if (curl_errno($curl)) {
            error_log('Error in POST response: ' . curl_error($curl));
            echo '<b><span style="color:black">Veateade - POST päringul tegemisel ilmnes viga. Kontrollige interneti/Itella ühenduse olemasolu. </span></b> ' . curl_error($curl);
        } else {
            $this->barcodeJSONparser($POSTrequestResponse, $order);
        }
        curl_close($curl);
    }

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

    public function addBarcodeMetaDataToOrder($parcelLabelBarcodeID, $order) {
        $order->add_meta_data('_barcode_id_mdn', $parcelLabelBarcodeID, true);
        $order->save();
    }

    public function saveLabelPDFinUser($order, $urlToPackageLabel) {

        error_log("attempting to download label");

        $pdfUrl = $urlToPackageLabel . $order->get_meta('_barcode_id_mdn');
        $tempFileName = $order->get_meta('_barcode_id_mdn') . '.pdf';

        $ch = curl_init($pdfUrl);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $pdfContent = curl_exec($ch);

        if ($pdfContent === false) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log("cURL error: " . $error);
        }
        curl_close($ch);

        header('Content-Description: File Transfer');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename=' . $tempFileName);
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . strlen($pdfContent));
        ob_clean();
        flush();
        echo $pdfContent;
        exit;
    }

    public function isShippingMethodAvailable($rates, $package) {

        error_log("Hellow ");
        if (!$this->isOrderSuitableForShipping($package, $this->maxCapacityForTerminal)) {
            unset($rates[$this->id]);
        }
        return $rates;
    }

    public function addPrintLabelCustomOrderAction($actions) {
        //error_log("Create label print custom action: " . $this->id);
        $actions['custom_order_action'] = __($this->placeholderPrintLabelInAdmin, 'woocommerce');
        return $actions;
    }

    public function processPrintLabelCustomOrderAction($order) {
        $order_note = $this->shorthandForTitle . " " . $this->labelDownloadedPlaceholderText . $this->getOrderParcelTerminalText($order->get_meta('_selected_parcel_terminal_id_mdn')) . ".";
        $order->add_order_note($order_note);
        error_log("this is the url to the label: " . $this->urlToPackageLabel);
        $this->saveLabelPDFinUser($order, $this->urlToPackageLabel);
    }
    public function updateParcelTerminalForOrder($order, $order_id) {

        $order_note1 = $this->updateParcelTerminalNewTerminalNote . $this->getOrderParcelTerminalText($order->get_meta('_selected_parcel_terminal_id_mdn')) . ".";
        $order->add_order_note($order_note1);
        $this->preparePOSTrequestForBarcodeID($order_id);
    }

    public function addFreeShippingToProduct() {
        //todo
    }

    public function addFreeShippingOverTreshold() {
        //todo
    }

    public function mark_orders_completed( $order_ids ) {
        foreach ( $order_ids as $order_id ) {
            $order = wc_get_order( $order_id );
            $order->update_status( 'completed' );
        }
    }

    // below is functionality for mass printing labels

    public function add_custom_bulk_action() {
        global $post_type;


        if ('shop_order' == $post_type) {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function() {
                    jQuery('<option>').val('mark_as_shipped').text('<?php _e($this->printLabelPlaceholderInBulkActions, 'woocommerce'); ?>').appendTo("select[name='action']");
                });
            </script>
            <?php


        }
    }

    public function process_custom_bulk_action($redirect_to, $action, $post_ids): string
    {
        if ($action !== 'custom_order_bulk_action') {
            return $redirect_to;
        }

        foreach ($post_ids as $post_id) {
            $order = wc_get_order($post_id);

            if ($order) {

                $this->saveLabelPDFinUser($order, $this->urlToPackageLabel);
                $order_note = $this->shorthandForTitle . " " .$this->labelDownloadedPlaceholderText . $this->getOrderParcelTerminalText($order->get_meta('_selected_parcel_terminal_id_mdn')) . ".";
                $order->add_order_note($order_note);

            }
        }

        // Redirect back to the orders list with a success message
        return add_query_arg(array(
            'bulk_send_samples' => count($post_ids),
            'ids' => join(',', $post_ids),
        ), $redirect_to);
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

    public function register_custom_bulk_action($bulk_actions)
    {
        $bulk_actions['custom_order_bulk_action'] = __($this->placeholderPrintLabelInAdmin, 'woocommerce');
        return $bulk_actions;
    }
}
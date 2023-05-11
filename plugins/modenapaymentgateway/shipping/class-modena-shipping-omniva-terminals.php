<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Modena_Shipping_Omniva_Terminals extends Modena_Shipping_Method {

    public $cost;
    protected $omnivaMachines;
    protected $adjustParcelTerminalInAdminPlaceholder;
    protected $placeholderForSelectBoxLabel;
    protected $shorthandForTitle;
    protected $createOrderParcelMetaDataPlaceholderText;
    protected $updateParcelTerminalNewTerminalNote;
    protected $labelDownloadedPlaceholderText;


    public function __construct($instance_id = 0) {
        parent::__construct($instance_id);
        $this->id = 'modena-shipping-omniva-terminals';
        $this->urltoJSONlist = 'https://www.omniva.ee/locations.json';
        $this->urlToPackageLabel = 'https://monte360.com/itella/index.php?action=getLable&barcode=';
        $this->barcodePostURL = 'https://monte360.com/itella/index.php?action=createShipment';
        $this->maxCapacityForTerminal = 35;
        $this->pathToLocalTerminalsFile = '/shipping/assets/json/omnivaparcelterminals.json';
        $this->init_form_fields();
        $this->cost = floatval($this->get_option('cost'));
        $this->clientAPIkey = $this->get_option('clientAPIkey');
        $this->clientAPIsecret = $this->get_option('clientAPIsecret');
        $this->setNamesBasedOnLocales(get_locale());
        add_action('woocommerce_review_order_before_payment', array($this, 'renderParcelTerminalSelectBox'));

    }

    public function setNamesBasedOnLocales($current_locale) {

        $translations = array(
            'en' => array(
                'method_title' => 'Omniva parcel terminals',
                'method_description' => __('Omniva parcel terminals', 'woocommerce'),
                'title' => 'Omniva',
                //'placeholderPrintLabelInAdmin' => "Download Omniva parcel label",
                'printLabelPlaceholderInBulkActions' => "Download Omniva Parcel Labels",
                'adjustParcelTerminalInAdminPlaceholder' => "Update",
                'placeholderForSelectBoxLabel' => "Select parcel terminal",
                'shorthandForTitle' => "Omniva",
                'createOrderParcelMetaDataPlaceholderText' => "Omniva parcel terminal is selected for the order: ",
                'updateParcelTerminalNewTerminalNote' => "New Omniva parcel terminal has been selected for the order: ",
                'labelDownloadedPlaceholderText' => "parcel label has been downloaded: ",
            ),
            'ru' => array(
                'method_title' => 'Omniva Эстония',
                'method_description' => __('Терминалы посылок Omniva', 'woocommerce'),
                'title' => 'Omniva Эстония',
                //'placeholderPrintLabelInAdmin' => "Скачать этикетку посылки Omniva",
                'printLabelPlaceholderInBulkActions' => "Скачать этикетки посылок Omniva",
                'adjustParcelTerminalInAdminPlaceholder' => "Обновить",
                'placeholderForSelectBoxLabel' => "Выберите терминал посылки",
                'shorthandForTitle' => "Omniva",
                'createOrderParcelMetaDataPlaceholderText' => "Omniva терминал посылки выбран для заказа: ",
                'updateParcelTerminalNewTerminalNote' => "Новый терминал посылки Omniva был выбран для заказа: ",
                'labelDownloadedPlaceholderText' => "этикетка посылки была загружена: ",
            ),
            'et' => array(
                'method_title' => __('Omniva pakiterminalid - Modena', 'woocommerce'),
                'method_description' => __('Itella Smartpost pakiterminalid', 'woocommerce'),
                'title' => 'Omniva pakiterminalid',
                //'placeholderPrintLabelInAdmin' => "Prindi pakisilt",
                'printLabelPlaceholderInBulkActions' => "Lae alla Omniva pakisildid",
                'adjustParcelTerminalInAdminPlaceholder' => "Uuenda",
                'placeholderForSelectBoxLabel' => "Vali pakipunkt",
                'shorthandForTitle' => "Omniva",
                'createOrderParcelMetaDataPlaceholderText' => "Omniva pakiterminal on valitud tellimusele: ",
                'updateParcelTerminalNewTerminalNote' => "Uus Omniva pakiterminal on valitud tellimusele: ",
                'labelDownloadedPlaceholderText' => "pakisilt on alla laetud: ",
            ),
        );

        // Set the locale key based on the current locale
        $locale_key = 'en';
        if ($current_locale === 'ru_RU') {
            $locale_key = 'ru';
        } elseif ($current_locale !== 'en_GB' && $current_locale !== 'en_US') {
            $locale_key = 'et';
        }

        // Assign the translations to the object properties
        foreach ($translations[$locale_key] as $key => $value) {
            $this->{$key} = $value;
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
                'default' => $this->cost,
                'desc_tip' => true,
                'sanitize_callback' => array($this, 'sanitizeshippingMethodCost'),
            ),
            'clientAPIkey' => array(
                'title' => __('API key'),
                'type' => 'float',
                'placeholder' => '',
                'description' => 'Package Provider account API secret',
                'desc_tip' => true,
            ),
            'clientAPIsecret' => array(
                'title' => __('API secret'),
                'type' => 'float',
                'placeholder' => '',
                'description' => 'Package Provider account API secret',
                'desc_tip' => true,
            ),
        );
    }



    public function getParcelTerminalsbyCity() {
        if(empty($this->omnivaMachines)) {
            //error_log("Otsime veebist pakiterminalide listi, kuna pole varem laetud.");
            $this->omnivaMachines = $this->parseParcelTerminalsJSON($this->urltoJSONlist);
        }

        $terminalsByCity = [];

        foreach ($this->omnivaMachines as $terminal) {
            if ($terminal->ZIP == "96331" || $terminal->A0_NAME === "LV" || $terminal->A0_NAME === "LT") {
                continue;
            }
            $city = $terminal->A2_NAME;
            $name = $terminal->NAME;

            if (!isset($terminalsByCity[$city])) {
                $terminalsByCity[$city] = [];
            }

            $terminalsByCity[$city][] = [
                'id' => $terminal->ZIP,
                'name' => $name
            ];
        }
        return $terminalsByCity;
    }
    public function getParcelTerminals()
    {
        return $this->parseParcelTerminalsJSON($this->urltoJSONlist);

    }

    public function renderParcelTerminalSelectBox() {
        $terminalsByCity = $this->getParcelTerminalsbyCity();

        ?>
        <div class="mdn-shipping-select-wrapper-<?php echo $this->id ?>" style="margin-bottom: 15px">
            <label  for="mdn-shipping-select-box-omniva"><?php echo $this->placeholderForSelectBoxLabel ?></label>
            <select name="userShippingSelection-<?php echo $this->id ?>" id="mdn-shipping-select-box-omniva" data-method-id="<?php echo $this->id; ?>" style="width: 100%; height: 400px;">
                <option disabled selected="selected"></option>
                <?php
                foreach ($terminalsByCity as $city => $terminals) {
                    echo "<optgroup label='$city'>";
                    foreach ($terminals as $terminal) {
                        $terminalID = $terminal['id'];
                        echo "<option value='$terminalID' >" . $terminal['name'] . "</option>";
                    }
                    echo "</optgroup>";

                }
                ?>
            </select>
        </div>
        <?php
    }

    public function getOrderParcelTerminalText($wcOrderParcelTerminalID) {
        error_log("getOrderParcelTerminalText called with ID: " . $wcOrderParcelTerminalID); // Log the input

        $this->omnivaMachines = $this->getParcelTerminals();
        $parcelTerminalsById = array_column($this->omnivaMachines, null, 'ZIP');

        if (isset($parcelTerminalsById[$wcOrderParcelTerminalID])) {
            $parcelTerminal = $parcelTerminalsById[$wcOrderParcelTerminalID];
            return $parcelTerminal->NAME;
        }

        // Log the error and return a default value
        error_log("Terminal not found by ID: " . $wcOrderParcelTerminalID);
        return 'Not found';
    }

    /**
     * @return float
     */
    public function getCost(): float {
        return $this->cost;
    }
}

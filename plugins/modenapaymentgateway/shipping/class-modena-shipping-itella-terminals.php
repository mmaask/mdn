<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Modena_Shipping_Itella_Terminals extends Modena_Shipping_Method {

    protected $smartpostMachines;
    protected $adjustParcelTerminalInAdminPlaceholder;
    protected $placeholderForSelectBoxLabel;
    protected $shorthandForTitle;
    protected $createOrderParcelMetaDataPlaceholderText;
    protected $updateParcelTerminalNewTerminalNote;
    protected $labelDownloadedPlaceholderText;

    public function __construct($instance_id = 0) {
        parent::__construct($instance_id);
        $this->id = 'modena-shipping-itella-terminals';
        $this->urltoJSONlist = 'https://monte360.com/itella/index.php?action=displayParcelsList';
        $this->urlToPackageLabel = 'https://monte360.com/itella/index.php?action=getLable&barcode=';
        $this->barcodePostURL = 'https://monte360.com/itella/index.php?action=createShipment';
        $this->cost = 2.99;
        $this->maxCapacityForTerminal = 35;
        $this->pathToLocalTerminalsFile = '/shipping/assets/json/smartpostterminals.json';
        $this->setNamesBasedOnLocales(get_locale());
        add_action('woocommerce_review_order_before_payment', array($this, 'renderParcelTerminalSelectBox'));

    }

    public function setNamesBasedOnLocales($current_locale) {
        switch ($current_locale) {
            case 'en_GB' && 'en_US':
                $this->method_title                                  = 'Itella Smartpost';
                $this->method_description                            = __('Itella Smartpost parcel terminals', 'woocommerce');
                $this->title                                         = 'Itella Smartpost';
                $this->placeholderPrintLabelInAdmin                  = "Download Smartpost label";
                $this->printLabelPlaceholderInBulkActions            = "Download Itella Smartpost Parcel Labels";
                $this->adjustParcelTerminalInAdminPlaceholder        = "Update";
                $this->placeholderForSelectBoxLabel                  = "Select parcel terminal";
                $this->shorthandForTitle                             = "Smartpost";
                $this->createOrderParcelMetaDataPlaceholderText      = $this->shorthandForTitle . " parcel terminal is selected for the order: ";
                $this->updateParcelTerminalNewTerminalNote           = "New " . $this->shorthandForTitle . " parcel terminal has been selected for the order: ";
                $this->labelDownloadedPlaceholderText                = "parcel label has been downloaded: ";

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


    public function getParcelTerminals() {
       return $this->parseParcelTerminalsJSON($this->urltoJSONlist)->item;

    }

    public function renderParcelTerminalSelectBox() {

        if(empty($this->smartpostMachines)) {
            $this->smartpostMachines = $this->getParcelTerminals();
        }

        ?>
        <div class="mdn-shipping-select-wrapper-<?php echo $this->id ?>" style="margin-bottom: 15px">
            <label  for="mdn-shipping-select-box-itella"><?php echo $this->placeholderForSelectBoxLabel ?></label>
            <select name="userShippingSelection-<?php echo $this->id ?>" id="mdn-shipping-select-box-itella" data-method-id="<?php echo $this->id; ?>" style="width: 100%; height: 400px;">
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
                        echo "<option value='$terminalID' >" . $terminal->{'name'} . "</option>";
                    }
                    echo "</optgroup>";
                }
                ?>
            </select>
        </div>
        <?php
    }

    public function getOrderParcelTerminalText($wcOrderParcelTerminalID) {

        //error_log("getOrderParcelTerminalText called with ID: " . $wcOrderParcelTerminalID . " this method belongs to: " . $this->id); // Log the input
        $this->smartpostMachines = $this->getParcelTerminals();
        $parcelTerminalsById = array_column($this->smartpostMachines, null, 'place_id');

        if (isset($parcelTerminalsById[$wcOrderParcelTerminalID])) {
            $parcelTerminal = $parcelTerminalsById[$wcOrderParcelTerminalID];
            return $parcelTerminal->{'name'};

        }
        return error_log("Terminal not found by ID" . $wcOrderParcelTerminalID);
    }


    /**
     * @return float
     */
    public function getCost(): float {
        return $this->cost;
    }
}

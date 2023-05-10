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
    protected $labelDownloadedPlaceholderText;

    public function __construct($instance_id = 0) {
        parent::__construct($instance_id);
        $this->id = 'modena-shipping-itella-terminals';
        $this->urltoJSONlist = 'https://monte360.com/itella/index.php?action=displayParcelsList';
        $this->maxCapacityForTerminal = 35;
        $this->pathToLocalTerminalsFile = '/shipping/assets/json/smartpostterminals.json';
        $this->init_form_fields();
        $this->cost = floatval($this->get_option('cost'));
        $this->clientAPIkey = $this->get_option('clientAPIkey');
        $this->clientAPIsecret = $this->get_option('clientAPIsecret');
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

    public function init_form_fields()
    {
        $cost_desc = __('Enter a cost (excl. tax) or sum, e.g. <code>10.00 * [qty]</code>.') . '<br/><br/>' . __('Use <code>[qty]</code> for the number of items, <br/><code>[cost]</code> for the total cost of items, and <code>[fee percent="10" min_fee="20" max_fee=""]</code> for percentage based fees.');

        $this->instance_form_fields = array(

            'cost' => array(
                'title' => __('Cost'),
                'type' => 'float',
                'placeholder' => '',
                'description' => $cost_desc,
                'default' => 2.99,
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

    public function getParcelTerminals() {
       return $this->parseParcelTerminalsJSON()->item;

    }

    public function renderParcelTerminalSelectBox() {
        static $showOnce = false;

        if(empty($this->smartpostMachines)) {
            $this->smartpostMachines = $this->getParcelTerminals();
        }


        if(!$showOnce) {
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

        $showOnce = true;
    }



    public function getOrderParcelTerminalText($wcOrderParcelTerminalID) {
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

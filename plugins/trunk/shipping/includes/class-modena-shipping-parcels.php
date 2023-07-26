<?php

use Modena\Payment\Modena;

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

abstract class Modena_Shipping_Parcels extends Modena_Shipping_Method {
    public function __construct($instance_id = 0) {
      parent::__construct($instance_id);
    }

  public function get_modena_parcel_terminal_list() {
    return $this->modena_shipping->get_modena_parcel_terminal_list($this->id);
  }

  public function render_modena_select_box_in_checkout($modena_shipping_method) {

    if (empty($this->parcelMachineList)) {
      $this->parcelMachineList = $this->get_modena_parcel_terminal_list($modena_shipping_method);
    }

    ?>
      <div class="modena-shipping-select-wrapper-<?php
      echo $this->id ?>" style="margin-bottom: 15px">
          <label for="mdn-shipping-select-box-itella"><?php
            echo $this->get_select_box_placeholder_for_modena_shipping() ?></label>
          <select name="userShippingSelection-<?php
          echo $this->id ?>" id="mdn-shipping-select-box-itella" data-method-id="<?php
          echo $this->id; ?>" style="width: 100%; height: 400px;">
              <option disabled selected="selected"></option>
            <?php
            $cities = array();
            foreach ($this->parcelMachineList as $terminal) {
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

  public function add_shipping_to_checkout_details($totals, $order_id) {
    $order = wc_get_order($order_id);
    if (!$this->has_order_got_package_point_meta_data($order))
      return $totals;
    if ($this->parse_shipping_methods($this->id, $order_id)) {
      $parcel_terminal = $this->get_selected_shipping_destination($order);

      foreach ($totals as $key => $total) {
        if ($key === 'shipping') {
          $totals['shipping']['value'] = $totals['shipping']['value'] . " (" . $parcel_terminal . ")";
        }
      }

    }

    return $totals;
  }

  public function has_order_got_package_point_meta_data($order) {
    if (empty($this->get_selected_shipping_destination_barcode_id())) {
      error_log("oh no, order does not yet have a terminal.");

      return False;
    }
    else {
      return True;
    }
  }


  public function add_label_url_to_order_meta_data($label_url, $order_id) {
    $order = wc_get_order($order_id);
    $order->add_meta_data('_selected_modena_shipping_label_url', $label_url, true);
    $order->save();
  }

  public function add_print_label_custom_order_action($actions) {
    error_log("Create label print custom action: " . $this->id);
    $actions['custom_order_action'] = __($this->get_placeholderPrintLabelInAdmin(), 'woocommerce');

    return $actions;
  }

  public function process_print_label_custom_order_action($order) {
    $order_note = $this->get_placeholderPrintLabelInAdmin() . " class-modena-shipping-method.php" . $this->get_selected_shipping_destination($order) . ".";
    $order->add_order_note($order_note);
    error_log("this is the url to the label: " . $this->get_selected_shipping_label_url($order));
    $this->modena_shipping->save_modena_shipping_label_PDF_in_User($this->get_selected_shipping_destination_barcode_id($order));
  }

  public function render_shipping_destination_in_admin_order_view($order_id) {
    //error_log("Trying to run into admin orders");

    $order = wc_get_order($order_id);

    if ($this->is_order_pending($order))
      return;

    if ($this->parse_shipping_methods($this->id, $order_id)) {

      ?>
        <tr class="selected-terminal">
            <th>
                <h3>
                  <?php
                  echo $this->title ?>
                </h3>
            </th>
            <td>
                <p>
                  <?php
                  echo $this->get_selected_shipping_destination(); ?>

                </p>
                <button id="buttonForClicking" onClick="startUpdatingOrderParcel()" class="button grant-access"><?php
                  _e($this->get_placeholderPrintLabelInAdmin()) ?></button>

                <script>
                    document.getElementById("buttonForClicking").addEventListener("click", startUpdatingOrderParcel);

                    function startUpdatingOrderParcel() {


                      <?php
                      //todo implement download correctly
                      //$this->updateParcelTerminalForOrder($order, $order_id);
                      ?>
                    }
                </script>
            </td>
        </tr>
      <?php
    }
  }

  public function process_modena_shipping_request($order_id) {
    //try {
    //  $modena_shipping_response = $this->modena_shipping->get_modena_shipping_barcode_id($this->compile_data_for_modena_shipping_request($order_id));
    //  $this->add_label_url_to_order_meta_data($modena_shipping_response, $order_id);
    //} catch (Exception $exception) {
    //  $this->shipping_logger->error('Exception occurred when processing data: ' . $exception->getMessage());
    //  $this->shipping_logger->error($exception->getTraceAsString());
    //}
  }


  public function get_placeholderPrintLabelInAdmin() {
    return __('Download ' . $this->get_title() . ' Parcel Label');
  }

  public function get_printLabelPlaceholderInBulkActions() {
    return __('Download ' . $this->get_title() . ' Parcel Labels');
  }

  public function get_adjustParcelTerminalInAdminPlaceholder() {
    return __('Update: ');
  }

  public function get_select_box_placeholder_for_modena_shipping() {
    return __('Select parcel terminal ');
  }

  public function get_createOrderParcelMetaDataPlaceholderText() {
    return __('Parcel terminal is selected for the order: ');
  }

  public function get_updateParcelTerminalNewTerminalNote() {
    return __('New parcel terminal has been selected for the order: ');
  }

  public function get_selected_shipping_destination_barcode_id($order) {
    return $this->$order->get_meta('_selected_modena_shipping_destination_barcode_id');
  }

  public function get_selected_shipping_destination($order) {
    return $this->$order->get_meta('_selected_modena_shipping_destination_id');
  }

  public function get_selected_shipping_label_url($order) {
    return $this->$order->get_meta('_selected_modena_shipping_label_url');
  }

}
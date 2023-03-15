<?php
if (!defined('ABSPATH')) { exit;}

class Modena_Shipping_Terminals extends Modena_Shipping_Base {
    public function __construct($instance_id = 0) {

        $this->instance_id = absint($instance_id);

        $this->id                       =           'modena_shipping_itella_terminals';
        $this->method_title             =           'Parcel terminals from Itella';
        $this->method_description       =           'Parcel terminals from Itella by Modena, ' . $this->instance_id;
        $this->title                    =           'Parcel terminals from Itella';
        $this->supports                 =           array('shipping-zones', 'instance-settings', 'instance-settings-modal');

        parent::__construct();
    }
}
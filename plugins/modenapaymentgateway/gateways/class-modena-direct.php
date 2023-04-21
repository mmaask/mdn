<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

 class Modena_Direct_Payment extends Modena_Base_Payment
{
    public function __construct()
    {
        $this->id      = 'modena_direct';
        $this->title = apply_filters('gettext', 'Panga- ja kaardimaksed', 'mdn-direct-title-text', 'mdn-translations');
        $this->enabled = $this->get_option('enabled');

        $this->method_title       = 'Modena Direct';
        $this->method_description = __('Pangamaksed / kaardimaksed', 'modena');

        $this->maturity_in_months = 0;

        $this->default_image = 'https://cdn.modena.ee/modena/assets/modena_woocommerce_direct_01511526fd.png?47448.59999999963';
        $this->default_alt             = '';
        $this->default_icon_title_text = 'Makseteenuseid pakub Modena Payments OÜ koostöös EveryPay AS-iga.';

        parent::__construct();
    }

    public function get_description()
    {
        $options = [];

        try {
            $options   = $this->modena->getPaymentOptions();
            $sortOrder = array_column($options, 'order');
            array_multisort($sortOrder, SORT_ASC, $options);
        } catch (Exception $e) {
            $this->logger->error(sprintf("Error retrieving payment options: %s", $e->getMessage()));
            $this->logger->error($e->getTraceAsString());
        }

        $ulOptions = '';

        $i = 0;
        foreach ($options as $option) {
            $i++;
            $id    = str_replace(' ', '_', strtoupper($option['name']));
            $src   = $option['buttonUrl'];
            $value = $option['code'];
            $alt   = $option['name'];
            $class = 'mdn_banklink_img';

            if ($i === 1) {
                $class = 'mdn_banklink_img mdn_checked';
            }

            $ulOptions .= sprintf(
                "<li><img id=\"mdn_bl_option_%s\" src=\"%s\" alt=\"%s\" class=\"%s\" onclick=\"selectModenaBanklink('%s', '%s')\"/></li>",
                $id, $src, $alt, $class, $id, $value
            );
        }

        $description = '<input type="hidden" id="mdn_selected_banklink" name="MDN_OPTION" value="HABAEE2X">';
        $description .= '<ul id="mdn_banklinks_wrapper" class="mdn_banklinks" style="margin: 0 14px 24px 14px !important; list-style-type: none;">' . $ulOptions . '</ul>';

        /*
         * Using the singleton pattern we can ensure that the <link> or <script> tags get added to the site only once.
         * */
        Modena_Load_Checkout_Assets::getInstance();

        return "{$description}{$this->getServiceInfoHtml()}";
    }

    private function getServiceInfoHtml()
    {
        $linkLabel = __('Teenuse info', 'modena');

        return "<a class='mdn_service_info' href='https://modena.ee/makseteenused/' target='_blank'>{$linkLabel}</a>";
    }
     protected function postPaymentOrderInternal($request) {
         return $this->modena->postDirectPaymentOrder($request);
     }

}
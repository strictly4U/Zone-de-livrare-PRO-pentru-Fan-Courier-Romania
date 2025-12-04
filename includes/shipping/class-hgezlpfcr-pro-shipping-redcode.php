<?php
/**
 * RedCode Shipping Method
 * Express delivery - same day or next day
 * Max weight: 5kg
 */
if (!defined('ABSPATH')) exit;

class HGEZLPFCR_Pro_Shipping_RedCode extends HGEZLPFCR_Pro_Shipping_Base {

    /**
     * Constructor
     */
    public function __construct($instance_id = 0) {
        $this->id                 = 'fc_pro_redcode';
        $this->service_name       = 'RedCode';
        $this->service_type_id    = 2;
        $this->max_weight         = 5; // Max 5kg for RedCode
        $this->method_title       = __('FAN Courier: RedCode', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');
        $this->method_description = __('FAN Courier RedCode - express delivery (same day or next day). Maximum weight: 5kg.', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');
        $this->title              = __('FAN Courier RedCode', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');

        parent::__construct($instance_id);
    }
}

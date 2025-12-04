<?php
/**
 * Express Loco Shipping Method
 * Fast delivery service
 */
if (!defined('ABSPATH')) exit;

class HGEZLPFCR_Pro_Shipping_ExpressLoco extends HGEZLPFCR_Pro_Shipping_Base {

    /**
     * Constructor
     */
    public function __construct($instance_id = 0) {
        $this->id                 = 'fc_pro_express_loco';
        $this->service_name       = 'Express Loco';
        $this->service_type_id    = 5;
        $this->max_weight         = 0; // No weight limit
        $this->method_title       = __('FAN Courier: Express Loco', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');
        $this->method_description = __('FAN Courier Express Loco - fast delivery service.', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');
        $this->title              = __('FAN Courier Express Loco', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');

        parent::__construct($instance_id);
    }
}

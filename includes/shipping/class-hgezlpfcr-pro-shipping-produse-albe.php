<?php
/**
 * Produse Albe Shipping Method
 * For bulky/white goods (large appliances, furniture)
 */
if (!defined('ABSPATH')) exit;

class HGEZLPFCR_Pro_Shipping_ProduseAlbe extends HGEZLPFCR_Pro_Shipping_Base {

    /**
     * Constructor
     */
    public function __construct($instance_id = 0) {
        $this->id                 = 'fc_pro_produse_albe';
        $this->service_name       = 'Produse Albe';
        $this->service_type_id    = 13;
        $this->max_weight         = 0; // No weight limit - bulky goods
        $this->method_title       = __('FAN Courier: Produse Albe', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');
        $this->method_description = __('FAN Courier Produse Albe - for bulky goods (large appliances, furniture).', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');
        $this->title              = __('FAN Courier Produse Albe', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');

        parent::__construct($instance_id);
    }
}

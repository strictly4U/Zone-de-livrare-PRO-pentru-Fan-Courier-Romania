<?php
/**
 * Collect Point PayPoint Shipping Method
 * Pickup points at PayPoint network
 */
if (!defined('ABSPATH')) exit;

class HGEZLPFCR_Pro_Shipping_CollectPointPayPoint extends HGEZLPFCR_Pro_Shipping_Base {

    /**
     * Constructor
     */
    public function __construct($instance_id = 0) {
        $this->id                 = 'fc_pro_collect_point_paypoint';
        $this->service_name       = 'Collect Point PayPoint';
        $this->service_type_id    = 7;
        $this->max_weight         = 0; // No weight limit
        $this->requires_pickup_point = true;
        $this->method_title       = __('FAN Courier: Collect Point PayPoint', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');
        $this->method_description = __('FAN Courier Collect Point - pickup from PayPoint network locations.', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');
        $this->title              = __('FAN Courier Collect Point PayPoint', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');

        parent::__construct($instance_id);
    }

    /**
     * Get instance form fields - extends base with pickup point specific options
     */
    protected function get_instance_form_fields() {
        $fields = parent::get_instance_form_fields();

        // Add pickup point specific field
        $fields['show_pickup_map'] = [
            'title'       => __('Show pickup map', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
            'type'        => 'checkbox',
            'label'       => __('Show interactive map for pickup point selection', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
            'default'     => 'yes',
            'description' => __('If checked, customers can select pickup point from an interactive map.', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
        ];

        return $fields;
    }
}

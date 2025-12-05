<?php
/**
 * Base Shipping Class for FAN Courier PRO Services
 * All PRO shipping methods extend this class
 */
if (!defined('ABSPATH')) exit;

abstract class HGEZLPFCR_Pro_Shipping_Base extends WC_Shipping_Method {

    /**
     * Service name for API calls (e.g., 'RedCode', 'Express Loco')
     */
    protected $service_name = '';

    /**
     * Service type ID for FAN Courier API
     */
    protected $service_type_id = 1;

    /**
     * Maximum weight allowed for this service (0 = unlimited)
     */
    protected $max_weight = 0;

    /**
     * Whether this service requires pickup point selection
     */
    protected $requires_pickup_point = false;

    /**
     * Constructor
     */
    public function __construct($instance_id = 0) {
        $this->instance_id = absint($instance_id);
        $this->supports = ['shipping-zones', 'instance-settings', 'instance-settings-modal'];
        $this->enabled = 'yes';
        $this->init();
    }

    /**
     * Initialize settings
     */
    public function init() {
        $this->instance_form_fields = $this->get_instance_form_fields();
        $this->init_instance_settings();
        $this->title = $this->get_instance_option('title', $this->method_title);
        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
    }

    /**
     * Get instance form fields
     */
    public function get_instance_form_fields() {
        $fields = [
            'title' => [
                'title'       => __('Checkout title', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
                'type'        => 'text',
                'default'     => $this->method_title,
            ],
            'enable_dynamic_pricing' => [
                'title'       => __('Dynamic pricing', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
                'type'        => 'checkbox',
                'label'       => __('Enable real-time calculation via API', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
                'default'     => 'yes',
                'description' => __('If checked, cost will be calculated dynamically via FAN Courier API.', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
            ],
            'free_shipping_min' => [
                'title'       => __('Free shipping minimum', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
                'type'        => 'price',
                'default'     => '0',
                'description' => __('Minimum cart value for free shipping. Leave 0 to disable.', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
            ],
            'cost_bucharest' => [
                'title'       => __('Fixed Cost Bucharest', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
                'type'        => 'price',
                'default'     => '0',
                'description' => __('Fixed cost for Bucharest and Ilfov (when dynamic pricing is disabled).', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
            ],
            'cost_country' => [
                'title'       => __('Fixed Cost Country', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
                'type'        => 'price',
                'default'     => '0',
                'description' => __('Fixed cost for the rest of the country (when dynamic pricing is disabled).', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
            ],
        ];

        // Add max weight field if service has weight limit
        if ($this->max_weight > 0) {
            $fields['max_weight'] = [
                'title'       => __('Maximum weight (kg)', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
                'type'        => 'number',
                'default'     => $this->max_weight,
                'description' => sprintf(__('Maximum weight allowed for this service. Default: %d kg.', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'), $this->max_weight),
                'custom_attributes' => ['min' => '0.1', 'step' => '0.1'],
            ];
        }

        return $fields;
    }

    /**
     * Calculate shipping cost
     */
    public function calculate_shipping($package = []) {
        // Check license first
        if (!class_exists('HGEZLPFCR_Pro_License_Manager') || !HGEZLPFCR_Pro_License_Manager::is_license_active()) {
            return; // Don't add rate if license is not active
        }

        // Check weight limit
        if ($this->max_weight > 0) {
            $package_weight = $this->calculate_package_weight($package);
            $max_allowed = (float) $this->get_instance_option('max_weight', $this->max_weight);
            if ($package_weight > $max_allowed) {
                $this->log('Package weight exceeds limit', [
                    'package_weight' => $package_weight,
                    'max_allowed' => $max_allowed
                ]);
                return; // Don't show this shipping option
            }
        }

        $enable_dynamic = $this->get_instance_option('enable_dynamic_pricing', 'yes') === 'yes';

        // Check for free shipping first
        $free_shipping_min = (float) $this->get_instance_option('free_shipping_min', 0);
        $cart_total = WC()->cart ? WC()->cart->get_cart_contents_total() : 0;

        if ($free_shipping_min > 0 && $cart_total >= $free_shipping_min) {
            $cost = 0;
        } elseif ($enable_dynamic) {
            // Calculate dynamic pricing
            $cost = $this->get_dynamic_cost($package);
            // If dynamic pricing fails, fallback to fixed cost
            if ($cost <= 0) {
                $cost = $this->get_location_based_cost($package);
            }
        } else {
            // Calculate location-based fixed cost
            $cost = $this->get_location_based_cost($package);
        }

        $this->log('Shipping calculation', [
            'method' => $this->id,
            'service' => $this->service_name,
            'enable_dynamic' => $enable_dynamic,
            'free_shipping_min' => $free_shipping_min,
            'cart_total' => $cart_total,
            'calculated_cost' => $cost,
            'destination' => $package['destination'] ?? []
        ]);

        $this->add_rate([
            'id'    => $this->get_rate_id(),
            'label' => $this->title,
            'cost'  => max(0, $cost),
            'meta_data' => [
                'service_name' => $this->service_name,
                'service_type_id' => $this->service_type_id,
                'dynamic_pricing' => $enable_dynamic && $cost > 0 ? 'yes' : 'no'
            ],
        ]);
    }

    /**
     * Romanian county code to name mapping (without diacritics for API compatibility)
     */
    protected static $county_map = [
        'AB' => 'Alba', 'AR' => 'Arad', 'AG' => 'Arges', 'BC' => 'Bacau', 'BH' => 'Bihor',
        'BN' => 'Bistrita-Nasaud', 'BT' => 'Botosani', 'BV' => 'Brasov', 'BR' => 'Braila',
        'B' => 'Bucuresti', 'BZ' => 'Buzau', 'CS' => 'Caras-Severin', 'CL' => 'Calarasi',
        'CJ' => 'Cluj', 'CT' => 'Constanta', 'CV' => 'Covasna', 'DB' => 'Dambovita',
        'DJ' => 'Dolj', 'GL' => 'Galati', 'GR' => 'Giurgiu', 'GJ' => 'Gorj', 'HR' => 'Harghita',
        'HD' => 'Hunedoara', 'IL' => 'Ialomita', 'IS' => 'Iasi', 'IF' => 'Ilfov',
        'MM' => 'Maramures', 'MH' => 'Mehedinti', 'MS' => 'Mures', 'NT' => 'Neamt',
        'OT' => 'Olt', 'PH' => 'Prahova', 'SM' => 'Satu Mare', 'SJ' => 'Salaj',
        'SB' => 'Sibiu', 'SV' => 'Suceava', 'TR' => 'Teleorman', 'TM' => 'Timis',
        'TL' => 'Tulcea', 'VS' => 'Vaslui', 'VL' => 'Valcea', 'VN' => 'Vrancea'
    ];

    /**
     * Convert county code to full name for API
     */
    protected function get_county_name($county_code) {
        $code = strtoupper(trim($county_code));
        return self::$county_map[$code] ?? $county_code;
    }

    /**
     * Remove diacritics from string
     */
    protected function remove_diacritics($str) {
        $transliterator = null;
        if (function_exists('transliterator_transliterate')) {
            return transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $str);
        }
        // Fallback: simple replacement
        $search = ['ă', 'â', 'î', 'ș', 'ț', 'Ă', 'Â', 'Î', 'Ș', 'Ț', 'ş', 'ţ', 'Ş', 'Ţ'];
        $replace = ['a', 'a', 'i', 's', 't', 'A', 'A', 'I', 'S', 'T', 's', 't', 'S', 'T'];
        return str_replace($search, $replace, $str);
    }

    /**
     * Get dynamic cost from API
     * Uses HGEZLPFCR_Pro_API for PRO services (standalone, no base plugin dependency)
     */
    protected function get_dynamic_cost($package) {
        try {
            if (!class_exists('HGEZLPFCR_Pro_API')) {
                $this->log('PRO API class not available');
                return 0;
            }

            $destination = $package['destination'] ?? [];
            if (empty($destination['city'])) {
                $this->log('Insufficient destination data for dynamic pricing', $destination);
                return 0;
            }

            // Convert county code to full name for FAN Courier API
            $county_code = $destination['state'] ?? '';
            $county_name = $this->get_county_name($county_code);

            // Remove diacritics from locality for API compatibility
            $locality = $this->remove_diacritics($destination['city'] ?? '');

            // Prepare params for API calls
            $params = [
                'county' => $county_name,
                'locality' => $locality,
                'weight' => $this->calculate_package_weight($package),
                'length' => 30,
                'width' => 20,
                'height' => 10,
            ];

            $this->log('Dynamic pricing params', [
                'original_county' => $county_code,
                'mapped_county' => $county_name,
                'locality' => $locality,
                'weight' => $params['weight']
            ]);

            // Check service availability first using PRO API
            $availability = HGEZLPFCR_Pro_API::check_service($this->service_name, $params);
            if (is_wp_error($availability) || empty($availability['available'])) {
                $this->log('Service not available for destination', [
                    'service' => $this->service_name,
                    'destination' => $destination
                ]);
                return 0;
            }

            // Get tariff using PRO API
            $response = HGEZLPFCR_Pro_API::get_tariff($this->service_name, $params);

            if (is_wp_error($response)) {
                $this->log('API tariff calculation failed', ['error' => $response->get_error_message()]);
                return 0;
            }

            return isset($response['price']) ? (float) $response['price'] : 0;

        } catch (Exception $e) {
            $this->log('Dynamic pricing calculation error', ['exception' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Check if shipping method is available
     */
    public function is_available($package) {
        // Check license first
        if (!class_exists('HGEZLPFCR_Pro_License_Manager') || !HGEZLPFCR_Pro_License_Manager::is_license_active()) {
            return false;
        }

        if (!parent::is_available($package)) {
            return false;
        }

        // Check weight limit
        if ($this->max_weight > 0) {
            $package_weight = $this->calculate_package_weight($package);
            $max_allowed = (float) $this->get_instance_option('max_weight', $this->max_weight);
            if ($package_weight > $max_allowed) {
                return false;
            }
        }

        // Check API credentials
        if (class_exists('HGEZLPFCR_Settings')) {
            $user = HGEZLPFCR_Settings::get('hgezlpfcr_user', '');
            $client = HGEZLPFCR_Settings::get('hgezlpfcr_client', '');
            if (empty($user) || empty($client)) {
                $enable_dynamic = $this->get_instance_option('enable_dynamic_pricing', 'yes') === 'yes';
                if ($enable_dynamic) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get location-based fixed cost
     */
    protected function get_location_based_cost($package) {
        $destination = $package['destination'] ?? [];
        $city = trim($destination['city'] ?? '');
        $state = trim($destination['state'] ?? '');

        $is_bucharest_area = $this->is_bucharest_area($state, $city);

        if ($is_bucharest_area) {
            return (float) $this->get_instance_option('cost_bucharest', 0);
        } else {
            return (float) $this->get_instance_option('cost_country', 0);
        }
    }

    /**
     * Check if destination is Bucharest/Ilfov area
     */
    protected function is_bucharest_area($state, $city) {
        // Check by state/county code
        if ($state === 'B' || $state === 'IF') {
            return true;
        }

        // Check by city name
        if (!empty($city)) {
            $city_lower = strtolower($city);
            if (strpos($city_lower, 'sector') !== false ||
                strpos($city_lower, 'bucuresti') !== false ||
                strpos($city_lower, 'bucharest') !== false ||
                strpos($city_lower, 'bucureşti') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate total package weight
     */
    protected function calculate_package_weight($package) {
        $weight = 0;
        foreach ($package['contents'] as $item) {
            $product = $item['data'];
            $product_weight = (float) $product->get_weight();
            $weight += $product_weight * $item['quantity'];
        }
        return max($weight, 0.1); // minimum 0.1 kg
    }

    /**
     * Log helper
     */
    protected function log($message, $context = []) {
        if (class_exists('HGEZLPFCR_Logger')) {
            HGEZLPFCR_Logger::log('[PRO ' . $this->service_name . '] ' . $message, $context);
        }
    }
}

<?php
/**
 * Service Registry - Central management for all PRO shipping services
 */

if (!defined('ABSPATH')) exit;

class HGEZLPFCR_Pro_Service_Registry {

    private static $services = [
        'redcode' => [
            'class'         => 'HGEZLPFCR_Pro_Shipping_RedCode',
            'file'          => 'shipping/class-hgezlpfcr-pro-shipping-redcode.php',
            'id'            => 'fc_pro_redcode',
            'name'          => 'FAN Courier RedCode',
            'description'   => 'Livrare in aceeasi zi pentru colete mici (max 5kg)',
            'service_ids'   => [2, 9],
            'requires'      => ['HGEZLPFCR_Settings', 'HGEZLPFCR_Logger'],
            'priority'      => 10,
            'features'      => ['same_day', 'weight_restricted', 'zone_restricted'],
        ],
        'express_loco' => [
            'class'         => 'HGEZLPFCR_Pro_Shipping_ExpressLoco',
            'file'          => 'shipping/class-hgezlpfcr-pro-shipping-express-loco.php',
            'id'            => 'fc_pro_express_loco',
            'name'          => 'FAN Courier Express Loco',
            'description'   => 'Livrare rapida in aceeasi zi',
            'service_ids'   => [5, 10],
            'requires'      => ['HGEZLPFCR_Settings', 'HGEZLPFCR_Logger'],
            'priority'      => 9,
            'features'      => ['same_day', 'zone_restricted'],
        ],
        'omv' => [
            'class'         => 'HGEZLPFCR_Pro_Shipping_CollectPointOMV',
            'file'          => 'shipping/class-hgezlpfcr-pro-shipping-collect-point-omv.php',
            'id'            => 'fc_pro_collect_point_omv',
            'name'          => 'FAN Courier CollectPoint OMV/Petrom',
            'description'   => 'Ridicare colete din benzinarii OMV si Petrom',
            'service_ids'   => [6, 11],
            'requires'      => ['HGEZLPFCR_Settings', 'HGEZLPFCR_Logger'],
            'priority'      => 8,
            'features'      => ['pickup_point', 'cod_support'],
        ],
        'paypoint' => [
            'class'         => 'HGEZLPFCR_Pro_Shipping_CollectPointPayPoint',
            'file'          => 'shipping/class-hgezlpfcr-pro-shipping-collect-point-paypoint.php',
            'id'            => 'fc_pro_collect_point_paypoint',
            'name'          => 'FAN Courier CollectPoint PayPoint',
            'description'   => 'Ridicare colete din reteaua de puncte PayPoint',
            'service_ids'   => [7, 12],
            'requires'      => ['HGEZLPFCR_Settings', 'HGEZLPFCR_Logger'],
            'priority'      => 7,
            'features'      => ['pickup_point', 'cod_support'],
        ],
        'produse_albe' => [
            'class'         => 'HGEZLPFCR_Pro_Shipping_ProduseAlbe',
            'file'          => 'shipping/class-hgezlpfcr-pro-shipping-produse-albe.php',
            'id'            => 'fc_pro_produse_albe',
            'name'          => 'FAN Courier Produse Albe',
            'description'   => 'Transport specializat pentru electronice mari si electrocasnice',
            'service_ids'   => [13, 14],
            'requires'      => ['HGEZLPFCR_Settings', 'HGEZLPFCR_Logger'],
            'priority'      => 6,
            'features'      => ['bulky_goods'],
        ],
        'fanbox' => [
            'class'         => 'HGEZLPFCR_Pro_Shipping_Fanbox',
            'file'          => 'shipping/class-hgezlpfcr-pro-shipping-fanbox.php',
            'id'            => 'fc_pro_fanbox',
            'name'          => 'FAN Courier FANBox',
            'description'   => 'Livrare in lockere FANBox amplasate in diverse locatii',
            'service_ids'   => [27, 28],
            'requires'      => ['HGEZLPFCR_Settings', 'HGEZLPFCR_Logger'],
            'selector'      => 'HGEZLPFCR_Pro_Fanbox_Selector',
            'selector_file' => 'selectors/class-hgezlpfcr-pro-fanbox-selector.php',
            'priority'      => 5,
            'features'      => ['pickup_point', 'map_selector', 'cod_support'],
        ],
    ];

    public static function init() {
        add_filter('woocommerce_shipping_methods', [__CLASS__, 'register_shipping_methods'], 20);
        add_action('woocommerce_shipping_init', [__CLASS__, 'load_service_classes'], 20);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend_assets']);
    }

    public static function load_service_classes() {
        // Load PRO API class first
        $api_class_file = HGEZLPFCR_PRO_PLUGIN_DIR . 'includes/class-hgezlpfcr-pro-api.php';
        if (file_exists($api_class_file)) {
            require_once $api_class_file;
        } else {
            return;
        }

        // Load Base Shipping Class
        $base_class_file = HGEZLPFCR_PRO_PLUGIN_DIR . 'includes/shipping/class-hgezlpfcr-pro-shipping-base.php';
        if (file_exists($base_class_file)) {
            require_once $base_class_file;
        } else {
            return;
        }

        foreach (self::$services as $key => $service) {
            if (!self::is_service_enabled($key)) {
                continue;
            }
            if (!self::check_dependencies($service['requires'])) {
                continue;
            }
            $file_path = HGEZLPFCR_PRO_PLUGIN_DIR . 'includes/' . $service['file'];
            if (file_exists($file_path)) {
                require_once $file_path;
            }
            if (!empty($service['selector']) && !empty($service['selector_file'])) {
                $selector_path = HGEZLPFCR_PRO_PLUGIN_DIR . 'includes/' . $service['selector_file'];
                if (file_exists($selector_path)) {
                    require_once $selector_path;
                    if (class_exists($service['selector'])) {
                        $selector_class = $service['selector'];
                        $selector_class::init();
                    }
                }
            }
        }
    }

    public static function register_shipping_methods($methods) {
        foreach (self::$services as $key => $service) {
            if (self::is_service_enabled($key) && class_exists($service['class'])) {
                $methods[$service['id']] = $service['class'];
            }
        }
        return $methods;
    }

    public static function enqueue_frontend_assets() {
        if (!is_checkout()) {
            return;
        }
        $has_pickup_service = false;
        foreach (self::$services as $key => $service) {
            if (self::is_service_enabled($key) && in_array('pickup_point', $service['features'] ?? [], true)) {
                $has_pickup_service = true;
                break;
            }
        }
        if (!$has_pickup_service) {
            return;
        }
        $css_file = HGEZLPFCR_PRO_PLUGIN_DIR . 'assets/css/pro-checkout.css';
        if (file_exists($css_file)) {
            wp_enqueue_style('hgezlpfcr-pro-checkout', HGEZLPFCR_PRO_PLUGIN_URL . 'assets/css/pro-checkout.css', [], HGEZLPFCR_PRO_VERSION);
        }
        $js_file = HGEZLPFCR_PRO_PLUGIN_DIR . 'assets/js/pro-checkout.js';
        if (file_exists($js_file)) {
            wp_enqueue_script('hgezlpfcr-pro-checkout', HGEZLPFCR_PRO_PLUGIN_URL . 'assets/js/pro-checkout.js', ['jquery'], HGEZLPFCR_PRO_VERSION, true);
        }
    }

    public static function is_service_enabled($service_key) {
        return get_option("hgezlpfcr_pro_service_{$service_key}_enabled", 'no') === 'yes';
    }

    private static function check_dependencies($requires) {
        foreach ($requires as $dependency) {
            if (!class_exists($dependency)) {
                return false;
            }
        }
        return true;
    }

    public static function get_service($key) {
        return isset(self::$services[$key]) ? self::$services[$key] : null;
    }

    public static function get_all_services() {
        return self::$services;
    }

    public static function get_enabled_services() {
        $enabled = [];
        foreach (self::$services as $key => $service) {
            if (self::is_service_enabled($key)) {
                $enabled[$key] = $service;
            }
        }
        return $enabled;
    }

    public static function get_service_id($service_key, $is_cod = false) {
        $service = self::get_service($service_key);
        if (!$service) {
            return 0;
        }
        $service_ids = $service['service_ids'];
        if ($is_cod && isset($service_ids[1]) && $service_ids[1] > 0) {
            return $service_ids[1];
        }
        return isset($service_ids[0]) ? $service_ids[0] : 0;
    }
}

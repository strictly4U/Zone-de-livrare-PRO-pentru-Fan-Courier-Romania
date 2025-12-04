<?php
/**
 * PRO API Client
 * Extends base API client with support for all FAN Courier services
 */
if (!defined('ABSPATH')) exit;

class HGEZLPFCR_Pro_API {

    /**
     * Get all service mappings
     */
    public static function get_service_map() {
        return [
            'Standard' => 1,
            'RedCode' => 2,
            'Export' => 3,
            'Cont Colector' => 4,
            'Express Loco' => 5,
            'Collect Point OMV' => 6,
            'Collect Point PayPoint' => 7,
            'Produse Albe' => 13,
            'FANbox' => 27,
        ];
    }

    /**
     * Get COD service mapping
     */
    public static function get_cod_service_map() {
        return [
            1 => 4,   // Standard -> Cont Colector
            2 => 9,   // RedCode COD
            5 => 10,  // Express Loco COD
            6 => 11,  // Collect Point OMV COD
            7 => 12,  // Collect Point PayPoint COD
            13 => 14, // Produse Albe COD
            27 => 28, // FANbox COD
        ];
    }

    /**
     * Get serviceTypeId from service name
     */
    public static function get_service_type_id($service_name) {
        $map = self::get_service_map();
        return $map[$service_name] ?? 1;
    }

    /**
     * Get tariff for a service
     * Uses the base API client's authentication but with our service mapping
     */
    public static function get_tariff($service_name, $params) {
        $endpoint = 'https://ecommerce.fancourier.ro/get-tariff';
        $service_type_id = self::get_service_type_id($service_name);

        $body = [
            'serviceTypeId' => $service_type_id,
            'recipientCounty' => $params['county'] ?? '',
            'recipientLocality' => $params['locality'] ?? '',
            'weight' => $params['weight'] ?? 1,
            'length' => $params['length'] ?? 1,
            'width' => $params['width'] ?? 1,
            'height' => $params['height'] ?? 1,
        ];

        if (class_exists('HGEZLPFCR_Logger')) {
            HGEZLPFCR_Logger::log('[PRO API] Get tariff request', [
                'service' => $service_name,
                'serviceTypeId' => $service_type_id,
                'params' => $body
            ]);
        }

        $response = self::post_authenticated($endpoint, $body);

        if (is_wp_error($response)) {
            return $response;
        }

        if (isset($response['tariff'])) {
            return ['price' => (float) $response['tariff']];
        }

        return new WP_Error('fc_tariff_error', 'Invalid tariff response');
    }

    /**
     * Check if service is available
     */
    public static function check_service($service_name, $params) {
        $endpoint = 'https://ecommerce.fancourier.ro/check-service';
        $service_type_id = self::get_service_type_id($service_name);

        $body = [
            'serviceTypeId' => $service_type_id,
            'recipientCounty' => $params['county'] ?? '',
            'recipientLocality' => $params['locality'] ?? '',
            'weight' => $params['weight'] ?? 1,
            'packageLength' => $params['length'] ?? 1,
            'packageWidth' => $params['width'] ?? 1,
            'packageHeight' => $params['height'] ?? 1,
        ];

        if (class_exists('HGEZLPFCR_Logger')) {
            HGEZLPFCR_Logger::log('[PRO API] Check service request', [
                'service' => $service_name,
                'serviceTypeId' => $service_type_id,
                'params' => $body
            ]);
        }

        $response = self::post_authenticated($endpoint, $body);

        if (is_wp_error($response)) {
            return $response;
        }

        // Response is "1" for available, "0" for not available
        $is_available = false;
        if (isset($response['available'])) {
            $is_available = (bool) $response['available'];
        } elseif (isset($response['raw'])) {
            $is_available = ($response['raw'] == '1');
        } elseif (is_string($response) || is_numeric($response)) {
            $is_available = ($response == '1' || $response === 1);
        }

        return ['available' => $is_available];
    }

    /**
     * Make authenticated POST request to FAN Courier API
     * Uses form data (application/x-www-form-urlencoded) like base plugin
     */
    private static function post_authenticated($endpoint, $body) {
        // Get token from base plugin
        $token = self::get_auth_token();
        if (is_wp_error($token)) {
            return $token;
        }

        // Add domain to all eCommerce API requests (like base plugin)
        $body['domain'] = site_url();

        $args = [
            'method' => 'POST',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ],
            'body' => http_build_query($body),
            'sslverify' => false,
        ];

        $response = wp_remote_post($endpoint, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $response_body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);

        if (class_exists('HGEZLPFCR_Logger')) {
            HGEZLPFCR_Logger::log('[PRO API] Response', [
                'endpoint' => $endpoint,
                'code' => $code,
                'body' => $response_body
            ]);
        }

        if ($code !== 200) {
            return new WP_Error('fc_api_error', 'API returned status ' . $code, ['response' => $response_body]);
        }

        // Try to decode JSON
        $data = json_decode($response_body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $data;
        }

        // Return raw response (for simple responses like "1" or "0")
        return ['raw' => $response_body, 'available' => $response_body == '1'];
    }

    /**
     * Get authentication token from base plugin
     */
    private static function get_auth_token() {
        // Try to use base plugin's API client if available
        if (class_exists('HGEZLPFCR_API_Client')) {
            $api = new HGEZLPFCR_API_Client();
            if (method_exists($api, 'get_token')) {
                return $api->get_token();
            }
        }

        // Fallback: get token ourselves
        return self::authenticate();
    }

    /**
     * Authenticate with FAN Courier API
     */
    private static function authenticate() {
        // Get credentials from base plugin settings
        if (!class_exists('HGEZLPFCR_Settings')) {
            return new WP_Error('fc_auth_error', 'Base plugin settings not available');
        }

        $client = HGEZLPFCR_Settings::get('hgezlpfcr_client', '');
        $user = HGEZLPFCR_Settings::get('hgezlpfcr_user', '');
        $password = HGEZLPFCR_Settings::get('hgezlpfcr_pass', '');

        if (empty($client) || empty($user) || empty($password)) {
            return new WP_Error('fc_auth_error', 'FAN Courier credentials not configured');
        }

        // Check for cached token
        $cached_token = get_transient('hgezlpfcr_pro_api_token');
        if ($cached_token) {
            return $cached_token;
        }

        // Get new token
        $endpoint = 'https://ecommerce.fancourier.ro/authShop';
        $response = wp_remote_post($endpoint, [
            'timeout' => 30,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'clientId' => $client,
                'userAccount' => $user,
                'password' => $password,
            ]),
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['token'])) {
            // Cache token for 23 hours (tokens usually expire in 24h)
            set_transient('hgezlpfcr_pro_api_token', $data['token'], 23 * HOUR_IN_SECONDS);
            return $data['token'];
        }

        return new WP_Error('fc_auth_error', 'Failed to get authentication token');
    }
}

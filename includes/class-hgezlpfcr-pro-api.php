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
                'User-Agent' => 'WooFanCourier-PRO/' . HGEZLPFCR_PRO_VERSION . '; ' . home_url(),
            ],
            'body' => http_build_query($body),
            'sslverify' => false,
            'redirection' => 1,
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
     * Get authentication token from base plugin or generate our own
     */
    private static function get_auth_token() {
        // Try to use base plugin's FC_API_Client token first (it's already working)
        if (class_exists('FC_API_Client')) {
            // FC_API_Client uses the same option names
            $cached_token = get_option('fc_api_token', null);
            $cached_expires = get_option('fc_api_token_expires', null);

            if ($cached_token && $cached_expires) {
                $expires_time = strtotime($cached_expires);
                if ($expires_time && $expires_time > (time() + 300)) { // 5 minutes buffer
                    if (class_exists('HGEZLPFCR_Logger')) {
                        HGEZLPFCR_Logger::log('[PRO API] Using cached token from Standard plugin');
                    }
                    return $cached_token;
                }
            }

            // Try to get fresh token via Standard plugin's API client
            try {
                $api = new FC_API_Client();
                // Force token refresh by making a simple request
                $reflection = new ReflectionClass($api);
                if ($reflection->hasMethod('get_auth_token')) {
                    $method = $reflection->getMethod('get_auth_token');
                    $method->setAccessible(true);
                    $token = $method->invoke($api);
                    if ($token && !is_wp_error($token)) {
                        if (class_exists('HGEZLPFCR_Logger')) {
                            HGEZLPFCR_Logger::log('[PRO API] Got fresh token from Standard plugin');
                        }
                        return $token;
                    }
                }
            } catch (Exception $e) {
                if (class_exists('HGEZLPFCR_Logger')) {
                    HGEZLPFCR_Logger::log('[PRO API] Could not get token from Standard plugin', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Fallback: Check for our own cached token
        $cached_token = get_option('fc_api_token', null);
        $cached_expires = get_option('fc_api_token_expires', null);

        if ($cached_token && $cached_expires) {
            $expires_time = strtotime($cached_expires);
            if ($expires_time && $expires_time > (time() + 300)) { // 5 minutes buffer
                return $cached_token;
            }
        }

        // Generate new token ourselves
        return self::authenticate();
    }

    /**
     * Authenticate with FAN Courier eCommerce API
     * Uses domain-based authentication like the standard plugin
     */
    private static function authenticate() {
        if (class_exists('HGEZLPFCR_Logger')) {
            HGEZLPFCR_Logger::log('[PRO API] Generating new eCommerce API token');
        }

        // eCommerce API uses domain-based authentication
        $endpoint = 'https://ecommerce.fancourier.ro/authShop';
        $domain = site_url();

        $response = wp_remote_post($endpoint, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => 'WooFanCourier-PRO/' . HGEZLPFCR_PRO_VERSION . '; ' . home_url(),
            ],
            'body' => http_build_query(['domain' => $domain]),
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            if (class_exists('HGEZLPFCR_Logger')) {
                HGEZLPFCR_Logger::log('[PRO API] Token generation failed', [
                    'error' => $response->get_error_message()
                ]);
            }
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (class_exists('HGEZLPFCR_Logger')) {
            HGEZLPFCR_Logger::log('[PRO API] Token generation response', [
                'code' => $code,
                'domain' => $domain,
                'response' => $data
            ]);
        }

        if ($code === 200 && isset($data['token'])) {
            $token = $data['token'];

            // Save token with 24 hour expiration
            $expires_at = gmdate('Y-m-d H:i:s', time() + (24 * 3600));
            update_option('fc_api_token', $token);
            update_option('fc_api_token_expires', $expires_at);

            if (class_exists('HGEZLPFCR_Logger')) {
                HGEZLPFCR_Logger::log('[PRO API] Token saved', [
                    'token' => substr($token, 0, 10) . '...',
                    'expires_at' => $expires_at
                ]);
            }

            return $token;
        }

        if (class_exists('HGEZLPFCR_Logger')) {
            HGEZLPFCR_Logger::log('[PRO API] Token generation failed - invalid response', [
                'code' => $code,
                'response' => $data
            ]);
        }

        return new WP_Error('fc_auth_error', 'Failed to get authentication token', [
            'code' => $code,
            'response' => $data
        ]);
    }
}

<?php
/**
 * FANBox Shipping Method
 * Livrare în lockere FANBox amplasate în diverse locații
 *
 * @package HgE_Pro_FAN_Courier
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * HGEZLPFCR_Pro_Shipping_Fanbox class
 *
 * Extends Abstract Base Class for FANBox locker delivery service
 */
class HGEZLPFCR_Pro_Shipping_Fanbox extends HGEZLPFCR_Pro_Shipping_Base {

	/**
	 * Service key for registry lookup
	 *
	 * @var string
	 */
	protected $service_key = 'fanbox';

	/**
	 * FAN Courier Service IDs
	 * 27 = FANBox Standard
	 * 28 = FANBox with COD
	 *
	 * @var int
	 */
	protected $service_id = 27;
	protected $service_id_cod = 28;

	/**
	 * Constructor
	 *
	 * @param int $instance_id Shipping zone instance ID
	 */
	public function __construct($instance_id = 0) {
		$this->id                 = 'fc_pro_fanbox';
		$this->instance_id        = absint($instance_id);
		$this->method_title       = __('FAN Courier FANBox (PRO)', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');
		$this->method_description = __('Livrare în lockere FANBox amplasate în diverse locații (magazine, benzinării, etc.). Clientul selectează FANbox-ul preferat din hartă la checkout.', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');
		$this->supports           = ['shipping-zones', 'instance-settings', 'instance-settings-modal'];

		// Set FANBox-specific service info (MUST be before parent::init())
		$this->service_name       = 'FANbox';
		$this->service_type_id    = 27; // FANBox Standard service ID
		$this->requires_pickup_point = true;

		// Initialize parent (handles common functionality)
		$this->init();
	}

	/**
	 * Get instance form fields specific to FANBox
	 * Extends parent form fields with FANBox-specific options
	 */
	public function get_instance_form_fields() {
		$fields = parent::get_instance_form_fields();

		// Customize title default
		$fields['title']['default'] = __('FAN Courier FANBox', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');
		$fields['title']['description'] = __('Numele afișat la checkout pentru metoda de livrare FANBox.', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');

		// Customize free shipping description
		$fields['free_shipping_min']['description'] = __('Valoarea minimă pentru transport gratuit FANBox. Lăsați 0 pentru a dezactiva.', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');

		// Add FANBox-specific info field
		$fields['fanbox_info'] = [
			'title'       => __('Informații FANBox', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
			'type'        => 'title',
			'description' => __('<strong>Despre serviciul FANBox:</strong><br>
				• Lockere disponibile non-stop (24/7)<br>
				• Disponibil în magazine, benzinării și alte locații<br>
				• Clientul selectează FANbox-ul preferat din hartă la checkout<br>
				• Cod de deschidere primit prin SMS<br>
				• <a href="https://www.fancourier.ro/fanbox/" target="_blank">Vezi harta completă FANBox</a>', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
			'class'       => 'fc-pro-info-notice',
		];

		return $fields;
	}

	/**
	 * Check if FANBox service is available for package
	 * Extends parent availability check with FANBox-specific logic
	 *
	 * @param array $package Package array
	 * @return bool True if available, false otherwise
	 */
	public function is_available($package) {
		// Check parent availability first (credentials, enabled status, etc.)
		if (!parent::is_available($package)) {
			return false;
		}

		// Check if FANBox was marked as unavailable due to API error (cached check)
		// This is set by calculate_shipping when API fails
		$cache_key = 'hgezlpfcr_fanbox_api_status';
		$api_status = get_transient($cache_key);

		if ($api_status === 'unavailable') {
			// Check if enough time has passed to retry (5 minutes)
			$last_check = get_transient('hgezlpfcr_fanbox_last_check');
			if ($last_check && (time() - $last_check) < 300) {
				// Still in cooldown, show as unavailable
				$this->show_unavailable_notice();
				return false;
			}
			// Cooldown passed, delete transient to allow retry
			delete_transient($cache_key);
		}

		// FANBox is available nationwide in Romania
		// No specific geographic restrictions needed
		// The map selector will show available FANBox locations based on user's city

		return true;
	}

	/**
	 * Show notice when FANBox is unavailable
	 */
	protected function show_unavailable_notice() {
		if (WC()->session && !WC()->session->get('hgezlpfcr_fanbox_notice_shown')) {
			wc_add_notice(
				__('Serviciul FANBox este temporar indisponibil. Vă rugăm să alegeți o altă metodă de livrare.', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
				'notice'
			);
			WC()->session->set('hgezlpfcr_fanbox_notice_shown', true);
		}
	}

	/**
	 * Calculate shipping cost for FANBox
	 * Override parent to use FANBox location instead of customer address for dynamic pricing
	 *
	 * @param array $package Package array
	 */
	public function calculate_shipping($package = []) {
		// Check license first
		if (!class_exists('HGEZLPFCR_Pro_License_Manager') || !HGEZLPFCR_Pro_License_Manager::is_license_active()) {
			return;
		}

		// Check weight limit (FANBox max 20kg)
		$package_weight = $this->calculate_package_weight($package);
		$max_allowed = (float) $this->get_instance_option('max_weight', $this->max_weight);
		if ($max_allowed > 0 && $package_weight > $max_allowed) {
			$this->log('Package weight exceeds FANBox limit', [
				'package_weight' => $package_weight,
				'max_allowed' => $max_allowed
			]);
			return;
		}

		$enable_dynamic = $this->get_instance_option('enable_dynamic_pricing', 'yes') === 'yes';

		// Check for free shipping first
		$free_shipping_min = (float) $this->get_instance_option('free_shipping_min', 0);
		$cart_total = WC()->cart ? WC()->cart->get_cart_contents_total() : 0;

		if ($free_shipping_min > 0 && $cart_total >= $free_shipping_min) {
			$cost = 0;
		} elseif ($enable_dynamic) {
			// Calculate dynamic pricing using FANBox location
			$cost = $this->get_fanbox_dynamic_cost($package);
			// If dynamic pricing fails, fallback to fixed cost
			if ($cost <= 0) {
				$cost = $this->get_location_based_cost($package);
			}
		} else {
			// Calculate location-based fixed cost
			$cost = $this->get_location_based_cost($package);
		}

		// Log FANBox-specific calculation
		if (class_exists('HGEZLPFCR_Logger')) {
			HGEZLPFCR_Logger::log('FANBox shipping calculated', [
				'method' => 'fc_pro_fanbox',
				'service_id' => $this->service_id,
				'enable_dynamic' => $enable_dynamic,
				'calculated_cost' => $cost,
				'destination' => $package['destination'] ?? [],
			]);
		}

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
	 * Get dynamic cost for FANBox using FANBox location from cookie
	 * Instead of customer address, we use the selected FANBox location
	 *
	 * @param array $package Package array
	 * @return float Cost or 0 on failure
	 */
	protected function get_fanbox_dynamic_cost($package) {
		try {
			if (!class_exists('HGEZLPFCR_Pro_API')) {
				$this->log('PRO API class not available for FANBox');
				return 0;
			}

			// Log all FANBox cookies for debugging
			$this->log('FANBox cookies raw', [
				'address_cookie' => isset($_COOKIE['hgezlpfcr_pro_fanbox_address']) ? $_COOKIE['hgezlpfcr_pro_fanbox_address'] : 'NOT SET',
				'full_address_cookie' => isset($_COOKIE['hgezlpfcr_pro_fanbox_full_address']) ? $_COOKIE['hgezlpfcr_pro_fanbox_full_address'] : 'NOT SET',
				'name_cookie' => isset($_COOKIE['hgezlpfcr_pro_fanbox_name']) ? $_COOKIE['hgezlpfcr_pro_fanbox_name'] : 'NOT SET',
			]);

			// Get FANBox location from cookie (set by JavaScript when user selects FANBox)
			// Cookie format: "County|Locality" (e.g., "Bucuresti|Bucuresti")
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$fanbox_address_raw = isset($_COOKIE['hgezlpfcr_pro_fanbox_address']) ? wp_unslash($_COOKIE['hgezlpfcr_pro_fanbox_address']) : '';
			$fanbox_address = urldecode($fanbox_address_raw);

			// Also try full address cookie for more details
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$fanbox_full_address_raw = isset($_COOKIE['hgezlpfcr_pro_fanbox_full_address']) ? wp_unslash($_COOKIE['hgezlpfcr_pro_fanbox_full_address']) : '';
			$fanbox_full_address = urldecode($fanbox_full_address_raw);

			$this->log('FANBox cookies decoded', [
				'address' => $fanbox_address,
				'full_address' => $fanbox_full_address,
			]);

			$county = '';
			$locality = '';

			// Parse FANBox address cookie (format: "County|Locality")
			if (!empty($fanbox_address) && strpos($fanbox_address, '|') !== false) {
				$parts = explode('|', $fanbox_address);
				$county = isset($parts[0]) ? sanitize_text_field($parts[0]) : '';
				$locality = isset($parts[1]) ? sanitize_text_field($parts[1]) : '';
			}

			// If address cookie didn't work, try parsing full address
			// Format: "County, City, Street, Number, PostalCode, Description"
			if ((empty($county) || empty($locality)) && !empty($fanbox_full_address)) {
				$parts = array_map('trim', explode(',', $fanbox_full_address));
				if (count($parts) >= 2) {
					$county = sanitize_text_field($parts[0]);
					$locality = sanitize_text_field($parts[1]);
				}
			}

			// Fallback to customer destination if no FANBox selected yet
			if (empty($county) || empty($locality)) {
				$destination = $package['destination'] ?? [];
				if (!empty($destination['city'])) {
					$county = $this->get_county_name($destination['state'] ?? '');
					$locality = $this->remove_diacritics($destination['city']);
					$this->log('FANBox dynamic pricing: Using customer destination (no FANBox selected yet)', [
						'county' => $county,
						'locality' => $locality
					]);
				} else {
					$this->log('FANBox dynamic pricing: No destination data available');
					return 0;
				}
			} else {
				// Remove diacritics for API compatibility
				$county = $this->remove_diacritics($county);
				$locality = $this->remove_diacritics($locality);
				$this->log('FANBox dynamic pricing: Using FANBox location from cookie', [
					'county' => $county,
					'locality' => $locality,
					'cookie_address' => $fanbox_address
				]);
			}

			// Filter out invalid values
			if ($county === 'undefined' || $locality === 'undefined') {
				$this->log('FANBox dynamic pricing: Invalid cookie values detected');
				return 0;
			}

			// Keep county and locality as-is from cookie (already capitalized from map selection)
			// API accepts both codes (BR) and full names (Braila)
			// Just ensure proper capitalization
			$county = $this->capitalize_location($county);
			$locality = $this->capitalize_location($locality);

			$this->log('FANBox dynamic pricing: Final params after processing', [
				'county' => $county,
				'locality' => $locality,
			]);

			// Prepare params for API
			$params = [
				'county' => $county,
				'locality' => $locality,
				'weight' => $this->calculate_package_weight($package),
				'length' => 30,
				'width' => 20,
				'height' => 10,
			];

			$this->log('FANBox dynamic pricing params', $params);

			// FANBox is available nationwide - skip check_service and go directly to get_tariff
			// This avoids issues where check_service might incorrectly return unavailable

			// Get tariff using PRO API
			$response = HGEZLPFCR_Pro_API::get_tariff($this->service_name, $params);

			$price = 0;
			$api_failed = false;

			if (is_wp_error($response)) {
				$this->log('FANBox API tariff calculation failed', [
					'error' => $response->get_error_message(),
					'error_data' => $response->get_error_data()
				]);
				$api_failed = true;
			} elseif (isset($response['error']) || isset($response['message'])) {
				$this->log('FANBox API returned error', $response);
				$api_failed = true;
			} else {
				$price = isset($response['price']) ? (float) $response['price'] : 0;
				if ($price <= 0) {
					$this->log('FANBox API returned zero or invalid price', ['response' => $response]);
					$api_failed = true;
				}
			}

			// If API failed, mark FANBox as unavailable for 5 minutes
			if ($api_failed) {
				$this->log('FANBox service temporarily unavailable due to API error');
				// Cache the unavailable status for 5 minutes
				set_transient('hgezlpfcr_fanbox_api_status', 'unavailable', 300);
				set_transient('hgezlpfcr_fanbox_last_check', time(), 300);
				// Show notice to customer
				$this->show_unavailable_notice();
				return 0; // Return 0 - method will be hidden on next refresh
			}

			$this->log('FANBox dynamic price calculated successfully', ['price' => $price]);

			// Clear unavailable status if API works
			delete_transient('hgezlpfcr_fanbox_api_status');
			delete_transient('hgezlpfcr_fanbox_last_check');
			if (WC()->session) {
				WC()->session->set('hgezlpfcr_fanbox_notice_shown', false);
			}

			return $price;

		} catch (Exception $e) {
			$this->log('FANBox dynamic pricing error', ['exception' => $e->getMessage()]);
			return 0;
		}
	}

	/**
	 * Validate FANBox selection before order placement
	 * Hooks into WooCommerce validation
	 */
	public static function validate_fanbox_selection() {
		$chosen_methods = WC()->session->get('chosen_shipping_methods');

		if (!empty($chosen_methods)) {
			$shipping_method = $chosen_methods[0];

			// Check if FANBox shipping method is selected
			if (strpos($shipping_method, 'fc_pro_fanbox') !== false) {
				// Check if FANBox was selected (saved in cookie by frontend)
				$fanbox_name = isset($_COOKIE['hgezlpfcr_pro_fanbox_name']) ? sanitize_text_field(wp_unslash($_COOKIE['hgezlpfcr_pro_fanbox_name'])) : '';

				if (empty($fanbox_name)) {
					wc_add_notice(
						__('Te rugăm să alegi un locker FANBox de pe hartă!', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
						'error'
					);
				}
			}
		}
	}

	/**
	 * Save FANBox selection to order meta
	 * Hooks into order creation
	 *
	 * @param int|WC_Order $order_id Order ID or order object
	 */
	public static function save_fanbox_selection($order_id) {
		$order = is_numeric($order_id) ? wc_get_order($order_id) : $order_id;
		if (!$order) {
			return;
		}

		$chosen_methods = WC()->session->get('chosen_shipping_methods');

		if (!empty($chosen_methods)) {
			$shipping_method = $chosen_methods[0];

			// Check if FANBox shipping method is selected
			if (strpos($shipping_method, 'fc_pro_fanbox') !== false) {
				// Get FANBox data from cookies (set by frontend)
				$fanbox_name         = isset($_COOKIE['hgezlpfcr_pro_fanbox_name']) ? sanitize_text_field(wp_unslash($_COOKIE['hgezlpfcr_pro_fanbox_name'])) : '';
				$fanbox_address      = isset($_COOKIE['hgezlpfcr_pro_fanbox_address']) ? sanitize_text_field(wp_unslash($_COOKIE['hgezlpfcr_pro_fanbox_address'])) : '';
				$fanbox_full_address = isset($_COOKIE['hgezlpfcr_pro_fanbox_full_address']) ? sanitize_text_field(wp_unslash($_COOKIE['hgezlpfcr_pro_fanbox_full_address'])) : '';
				$fanbox_description  = isset($_COOKIE['hgezlpfcr_pro_fanbox_description']) ? sanitize_text_field(wp_unslash($_COOKIE['hgezlpfcr_pro_fanbox_description'])) : '';
				$fanbox_schedule     = isset($_COOKIE['hgezlpfcr_pro_fanbox_schedule']) ? sanitize_text_field(wp_unslash($_COOKIE['hgezlpfcr_pro_fanbox_schedule'])) : '';

				// Log raw cookie values for debugging
				if (class_exists('HGEZLPFCR_Logger')) {
					HGEZLPFCR_Logger::log('FANBox cookies received', [
						'fanbox_name_raw'         => $fanbox_name,
						'fanbox_address_raw'      => $fanbox_address,
						'fanbox_full_address_raw' => $fanbox_full_address,
					]);
				}

				if (!empty($fanbox_name)) {
					$decoded_name = urldecode($fanbox_name);
					$decoded_full_address = urldecode($fanbox_full_address);
					$decoded_description = urldecode($fanbox_description);
					$decoded_schedule = urldecode($fanbox_schedule);

					// Validate - filter out "undefined" strings
					if ($decoded_name === 'undefined') {
						$decoded_name = '';
					}
					if ($decoded_full_address === 'undefined' || strpos($decoded_full_address, 'undefined') !== false) {
						$decoded_full_address = '';
					}
					if ($decoded_description === 'undefined') {
						$decoded_description = '';
					}
					if ($decoded_schedule === 'undefined') {
						$decoded_schedule = '';
					}

					// Save FANBox information to order meta
					$order->add_meta_data('_hgezlpfcr_pro_fanbox_name', $decoded_name);
					$order->add_meta_data('_hgezlpfcr_pro_fanbox_full_address', $decoded_full_address);
					$order->add_meta_data('_hgezlpfcr_pro_fanbox_description', $decoded_description);
					$order->add_meta_data('_hgezlpfcr_pro_fanbox_schedule', $decoded_schedule);

					$county = '';
					$locality = '';

					if (!empty($fanbox_address)) {
						$address_parts = explode('|', urldecode($fanbox_address));
						if (count($address_parts) === 2) {
							$county = $address_parts[0];
							$locality = $address_parts[1];

							// Validate - filter out "undefined" strings
							if ($county === 'undefined') {
								$county = '';
							}
							if ($locality === 'undefined') {
								$locality = '';
							}

							if ($county) {
								$order->add_meta_data('_hgezlpfcr_pro_fanbox_county', $county);
							}
							if ($locality) {
								$order->add_meta_data('_hgezlpfcr_pro_fanbox_locality', $locality);
							}
						}
					}

					// Use full address if available, otherwise build from parts
					$shipping_address = '';
					if (!empty($decoded_full_address)) {
						$shipping_address = $decoded_full_address;
					} elseif (!empty($locality) && !empty($county)) {
						$shipping_address = $locality . ', ' . $county;
					} elseif (!empty($locality)) {
						$shipping_address = $locality;
					} elseif (!empty($county)) {
						$shipping_address = $county;
					}

					if (!empty($shipping_address)) {
						$order->add_meta_data('_hgezlpfcr_pro_fanbox_address', $shipping_address);
					}

					// Override shipping address with FANBox location
					$order->set_shipping_company($decoded_name);

					if (!empty($shipping_address)) {
						$order->set_shipping_address_1($shipping_address);
					}
					if (!empty($decoded_description)) {
						$order->set_shipping_address_2($decoded_description);
					}
					if (!empty($locality)) {
						$order->set_shipping_city($locality);
					}
					if (!empty($county)) {
						$order->set_shipping_state($county);
					}
					$order->set_shipping_postcode('');

					$order->save();

					if (class_exists('HGEZLPFCR_Logger')) {
						HGEZLPFCR_Logger::log('FANBox selection saved to order', [
							'order_id'         => is_object($order) ? $order->get_id() : $order_id,
							'fanbox_name'      => $decoded_name,
							'shipping_address' => $shipping_address,
							'county'           => $county,
							'locality'         => $locality,
						]);
					}
				}
			}
		}
	}

	/**
	 * Display FANBox info in order details (customer view)
	 *
	 * @param WC_Order $order Order object
	 */
	public static function display_fanbox_info($order) {
		$fanbox_name         = $order->get_meta('_hgezlpfcr_pro_fanbox_name');
		$fanbox_full_address = $order->get_meta('_hgezlpfcr_pro_fanbox_full_address');
		$fanbox_address      = $order->get_meta('_hgezlpfcr_pro_fanbox_address');
		$fanbox_description  = $order->get_meta('_hgezlpfcr_pro_fanbox_description');
		$fanbox_schedule     = $order->get_meta('_hgezlpfcr_pro_fanbox_schedule');

		// Parse address to remove description if it's included at the end
		// Format: "County, City, Street, Number, PostalCode, Description"
		$display_address = $fanbox_full_address ? $fanbox_full_address : $fanbox_address;
		if ($display_address && $fanbox_description) {
			// Remove description from address if it appears at the end
			$desc_pos = strpos($display_address, $fanbox_description);
			if ($desc_pos !== false) {
				$display_address = trim(substr($display_address, 0, $desc_pos), ', ');
			}
		}

		if ($fanbox_name) {
			echo '<h2>' . esc_html__('Informații FANBox', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro') . '</h2>';
			echo '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 6px;">';
			echo '<p style="margin: 0 0 10px 0;"><strong style="font-size: 16px;">📦 ' . esc_html($fanbox_name) . '</strong></p>';
			if ($display_address) {
				echo '<p style="margin: 0 0 5px 0;"><strong>' . esc_html__('Adresă:', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro') . '</strong> ' . esc_html($display_address) . '</p>';
			}
			if ($fanbox_description) {
				echo '<p style="margin: 0 0 5px 0; color: #666; font-style: italic;">📍 ' . esc_html($fanbox_description) . '</p>';
			}
			if ($fanbox_schedule) {
				echo '<p style="margin: 0; color: #155724;"><strong>' . esc_html__('Program:', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro') . '</strong> ' . esc_html($fanbox_schedule) . '</p>';
			}
			echo '</div>';
		}
	}

	/**
	 * Display FANBox info in admin order details
	 *
	 * @param WC_Order $order Order object
	 */
	public static function display_fanbox_info_admin($order) {
		$fanbox_name         = $order->get_meta('_hgezlpfcr_pro_fanbox_name');
		$fanbox_full_address = $order->get_meta('_hgezlpfcr_pro_fanbox_full_address');
		$fanbox_address      = $order->get_meta('_hgezlpfcr_pro_fanbox_address');
		$fanbox_description  = $order->get_meta('_hgezlpfcr_pro_fanbox_description');
		$fanbox_schedule     = $order->get_meta('_hgezlpfcr_pro_fanbox_schedule');

		// Parse address to remove description if it's included at the end
		// Format: "County, City, Street, Number, PostalCode, Description"
		$display_address = $fanbox_full_address ? $fanbox_full_address : $fanbox_address;
		if ($display_address && $fanbox_description) {
			// Remove description from address if it appears at the end
			$desc_pos = strpos($display_address, $fanbox_description);
			if ($desc_pos !== false) {
				$display_address = trim(substr($display_address, 0, $desc_pos), ', ');
			}
		}

		if ($fanbox_name) {
			echo '<div class="fc-pro-fanbox-info" style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 10px 0; border-radius: 6px;">';
			echo '<p style="margin: 0 0 10px 0;"><strong style="color: #155724; font-size: 14px;">📦 FANBox selectat:</strong></p>';
			echo '<p style="margin: 0 0 5px 0; font-weight: bold;">' . esc_html($fanbox_name) . '</p>';
			if ($display_address) {
				echo '<p style="margin: 0 0 5px 0; font-size: 13px;"><strong>Adresă:</strong> ' . esc_html($display_address) . '</p>';
			}
			if ($fanbox_description) {
				echo '<p style="margin: 0 0 5px 0; font-size: 12px; color: #666; font-style: italic;">📍 ' . esc_html($fanbox_description) . '</p>';
			}
			if ($fanbox_schedule) {
				echo '<p style="margin: 0; font-size: 12px; color: #155724;"><strong>Program:</strong> ' . esc_html($fanbox_schedule) . '</p>';
			}
			echo '</div>';
		}
	}
}

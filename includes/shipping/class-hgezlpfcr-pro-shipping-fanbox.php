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
	 * Get service name for API requests
	 * Required by Abstract Base Class
	 *
	 * @return string Service name
	 */
	protected function get_service_name() {
		return 'FANBox';
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

		// FANBox is available nationwide in Romania
		// No specific geographic restrictions needed
		// The map selector will show available FANBox locations based on user's city

		return true;
	}

	/**
	 * Calculate shipping cost for FANBox
	 * Uses parent implementation but can be overridden if needed
	 *
	 * @param array $package Package array
	 */
	public function calculate_shipping($package = []) {
		// Use parent calculation (handles dynamic pricing, free shipping, COD)
		parent::calculate_shipping($package);

		// Log FANBox-specific calculation
		if (class_exists('HGEZLPFCR_Logger')) {
			HGEZLPFCR_Logger::log('FANBox shipping calculated', [
				'method' => 'fc_pro_fanbox',
				'service_id' => $this->service_id,
				'destination' => $package['destination'] ?? [],
			]);
		}
	}

	/**
	 * Get dynamic cost for FANBox from API
	 * Overrides parent to use FANBox-specific service name
	 *
	 * @param array $package Package array
	 * @return float Cost or 0 on failure
	 */
	protected function get_dynamic_cost($package) {
		// Set service_name for parent class to use
		$this->service_name = 'FANbox';
		$this->service_type_id = $this->service_id;

		// Use parent implementation which uses PRO API
		return parent::get_dynamic_cost($package);
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
				$fanbox_name    = isset($_COOKIE['hgezlpfcr_pro_fanbox_name']) ? sanitize_text_field(wp_unslash($_COOKIE['hgezlpfcr_pro_fanbox_name'])) : '';
				$fanbox_address = isset($_COOKIE['hgezlpfcr_pro_fanbox_address']) ? sanitize_text_field(wp_unslash($_COOKIE['hgezlpfcr_pro_fanbox_address'])) : '';

				if (!empty($fanbox_name)) {
					// Save FANBox information to order meta
					$order->add_meta_data('_hgezlpfcr_pro_fanbox_name', urldecode($fanbox_name));

					if (!empty($fanbox_address)) {
						$address_parts = explode('|', urldecode($fanbox_address));
						if (count($address_parts) === 2) {
							$order->add_meta_data('_hgezlpfcr_pro_fanbox_address', $address_parts[1] . ', ' . $address_parts[0]);
							$order->add_meta_data('_hgezlpfcr_pro_fanbox_county', $address_parts[0]);
							$order->add_meta_data('_hgezlpfcr_pro_fanbox_locality', $address_parts[1]);
						}
					}

					$order->save();

					HGEZLPFCR_Logger::log('FANBox selection saved to order', [
						'order_id'       => is_object($order) ? $order->get_id() : $order_id,
						'fanbox_name'    => urldecode($fanbox_name),
						'fanbox_address' => $fanbox_address ? urldecode($fanbox_address) : '',
					]);
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
		$fanbox_name    = $order->get_meta('_hgezlpfcr_pro_fanbox_name');
		$fanbox_address = $order->get_meta('_hgezlpfcr_pro_fanbox_address');

		if ($fanbox_name && $fanbox_address) {
			echo '<h2>' . esc_html__('Informații FANBox', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro') . '</h2>';
			echo '<p><strong>' . esc_html__('FANBox selectat:', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro') . '</strong><br>';
			echo esc_html($fanbox_name) . '<br>';
			echo esc_html($fanbox_address) . '</p>';
		}
	}

	/**
	 * Display FANBox info in admin order details
	 *
	 * @param WC_Order $order Order object
	 */
	public static function display_fanbox_info_admin($order) {
		$fanbox_name    = $order->get_meta('_hgezlpfcr_pro_fanbox_name');
		$fanbox_address = $order->get_meta('_hgezlpfcr_pro_fanbox_address');

		if ($fanbox_name && $fanbox_address) {
			echo '<div class="fc-pro-fanbox-info" style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; border-radius: 4px;">';
			echo '<p style="margin: 0;"><strong style="color: #155724;">' . esc_html__('📦 FANBox selectat:', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro') . '</strong><br>';
			echo '<span style="font-size: 14px;">' . esc_html($fanbox_name) . '</span><br>';
			echo '<span style="font-size: 13px; color: #666;">' . esc_html($fanbox_address) . '</span></p>';
			echo '</div>';
		}
	}
}

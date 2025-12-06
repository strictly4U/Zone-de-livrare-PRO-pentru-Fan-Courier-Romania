<?php
/**
 * FANBox Selector
 * Handles FANBox selection UI, validation, and data persistence
 *
 * @package HgE_Pro_FAN_Courier
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * HGEZLPFCR_Pro_Fanbox_Selector class
 *
 * Manages FANBox locker selection on checkout
 */
class HGEZLPFCR_Pro_Fanbox_Selector {

	/**
	 * Initialize selector hooks
	 */
	public static function init() {
		// Save selected FANBox to order
		add_action('woocommerce_checkout_update_order_meta', ['HGEZLPFCR_Pro_Shipping_Fanbox', 'save_fanbox_selection'], 10, 1);
		add_action('woocommerce_store_api_checkout_update_order_meta', ['HGEZLPFCR_Pro_Shipping_Fanbox', 'save_fanbox_selection'], 10, 1);

		// Display selected FANBox in order details
		add_action('woocommerce_order_details_after_order_table', ['HGEZLPFCR_Pro_Shipping_Fanbox', 'display_fanbox_info'], 10, 1);
		add_action('woocommerce_admin_order_data_after_shipping_address', ['HGEZLPFCR_Pro_Shipping_Fanbox', 'display_fanbox_info_admin'], 10, 1);

		// Add validation for FANBox selection
		add_action('woocommerce_checkout_process', ['HGEZLPFCR_Pro_Shipping_Fanbox', 'validate_fanbox_selection'], 10);
		add_action('woocommerce_store_api_checkout_update_order_meta', ['HGEZLPFCR_Pro_Shipping_Fanbox', 'validate_fanbox_selection'], 10);

		// Make shipping fields optional when FANBox is selected
		add_filter('woocommerce_checkout_fields', [__CLASS__, 'modify_shipping_fields_for_fanbox'], 100);

		// Enqueue scripts and styles
		add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 20);
	}

	/**
	 * Modify shipping fields to be optional when FANBox shipping is selected
	 * The JavaScript will populate these fields with FANBox data
	 *
	 * @param array $fields Checkout fields
	 * @return array Modified fields
	 */
	public static function modify_shipping_fields_for_fanbox($fields) {
		// Check if FANBox is the selected shipping method
		if (!self::is_fanbox_shipping_selected()) {
			return $fields;
		}

		// List of shipping fields that should be optional for FANBox
		$optional_fields = [
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_state',
			'shipping_postcode',
		];

		foreach ($optional_fields as $field_key) {
			if (isset($fields['shipping'][$field_key])) {
				$fields['shipping'][$field_key]['required'] = false;
				// Add a class to identify FANBox-modified fields
				if (!isset($fields['shipping'][$field_key]['class'])) {
					$fields['shipping'][$field_key]['class'] = [];
				}
				$fields['shipping'][$field_key]['class'][] = 'hgezlpfcr-fanbox-optional';
			}
		}

		if (class_exists('HGEZLPFCR_Logger')) {
			HGEZLPFCR_Logger::log('FANBox: Shipping fields made optional', [
				'fields' => $optional_fields,
			]);
		}

		return $fields;
	}

	/**
	 * Check if FANBox shipping method is currently selected
	 *
	 * @return bool
	 */
	public static function is_fanbox_shipping_selected() {
		// Check WooCommerce session for chosen shipping method
		if (WC()->session) {
			$chosen_methods = WC()->session->get('chosen_shipping_methods');
			if (!empty($chosen_methods) && is_array($chosen_methods)) {
				foreach ($chosen_methods as $method) {
					if (strpos($method, 'fc_pro_fanbox') !== false) {
						return true;
					}
				}
			}
		}

		// Also check POST data during checkout submission
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce
		if (isset($_POST['shipping_method']) && is_array($_POST['shipping_method'])) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			foreach ($_POST['shipping_method'] as $method) {
				if (strpos(sanitize_text_field(wp_unslash($method)), 'fc_pro_fanbox') !== false) {
					return true;
				}
			}
		}

		// Check cookie as fallback (set by JavaScript when FANBox is selected)
		if (isset($_COOKIE['hgezlpfcr_pro_fanbox_name']) && !empty($_COOKIE['hgezlpfcr_pro_fanbox_name'])) {
			return true;
		}

		return false;
	}

	/**
	 * Enqueue FANBox assets (CSS, JS, external map library)
	 */
	public static function enqueue_assets() {
		// Only load on checkout and cart pages
		if (!is_checkout() && !is_cart()) {
			return;
		}

		// Check if FANBox service is enabled
		if (!HGEZLPFCR_Pro_Service_Registry::is_service_enabled('fanbox')) {
			return;
		}

		// Ensure jQuery is loaded
		wp_enqueue_script('jquery');

		// Add FANmapDiv container to footer
		add_action('wp_footer', function() {
			echo '<div id="FANmapDiv"></div>';
		}, 5);

		// Enqueue FAN Courier map library (external CDN)
		// phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent,WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Required third-party library
		wp_enqueue_script(
			'hgezlpfcr-pro-fanbox-map-library',
			'https://unpkg.com/map-fanbox-points@latest/umd/map-fanbox-points.js', // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent
			[],
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- CDN uses @latest tag
			true
		);

		// Enqueue FANBox map integration JavaScript
		wp_enqueue_script(
			'hgezlpfcr-pro-fanbox-map',
			HGEZLPFCR_PRO_PLUGIN_URL . 'assets/js/fanbox-map.js',
			['jquery', 'hgezlpfcr-pro-checkout', 'hgezlpfcr-pro-fanbox-map-library'],
			HGEZLPFCR_PRO_VERSION,
			true
		);

		// Enqueue FANBox-specific CSS (optional, can reuse common CSS)
		wp_enqueue_style(
			'hgezlpfcr-pro-fanbox',
			HGEZLPFCR_PRO_PLUGIN_URL . 'assets/css/fanbox-selector.css',
			['hgezlpfcr-pro-checkout'],
			HGEZLPFCR_PRO_VERSION
		);

		// Localize script with i18n strings
		wp_localize_script('hgezlpfcr-pro-fanbox-map', 'hgezlpfcrProFanbox', [
			'ajaxUrl'  => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('hgezlpfcr_pro_fanbox'),
			'methodId' => 'fc_pro_fanbox',
			'i18n'     => [
				'loading'         => __('Se încarcă FANbox-urile...', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
				'error'           => __('Eroare la încărcarea FANbox-urilor', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
				'selectFanbox'    => __('Selectați FANbox pentru livrare:', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
				'mapButtonText'   => __('Afișează harta lockere', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
				'noSelection'     => __('Niciun FANbox selectat', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
				'chooseFromMap'   => __('Alege FANbox-ul cel mai aproape de tine', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
				'deliveryTo'      => __('Livrare la', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
				'validationError' => __('Te rugăm să alegi un locker FANBox de pe hartă!', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
				'mapLoadError'    => __('Nu s-a putut încărca harta. Verificați conexiunea la internet și încercați din nou.', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
			],
		]);

		if (class_exists('HGEZLPFCR_Logger')) {
			HGEZLPFCR_Logger::log('FANBox selector assets enqueued', [
				'page'    => is_checkout() ? 'checkout' : 'cart',
				'enabled' => 'yes',
			]);
		}
	}
}

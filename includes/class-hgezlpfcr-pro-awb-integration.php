<?php
/**
 * AWB Integration for PRO Services
 * Modifies AWB payload for specialized PRO shipping services
 *
 * @package HgE_Pro_FAN_Courier
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * HGEZLPFCR_Pro_AWB_Integration class
 *
 * Handles AWB generation modifications for PRO services
 */
class HGEZLPFCR_Pro_AWB_Integration {

	/**
	 * Initialize AWB integration
	 */
	public static function init() {
		// Hook into AWB shipment data filter (added in Standard plugin v1.0.3+)
		add_filter('hgezlpfcr_awb_shipment_data', [__CLASS__, 'modify_awb_payload'], 10, 2);

		if (class_exists('HGEZLPFCR_Logger')) {
			HGEZLPFCR_Logger::log('PRO AWB Integration initialized');
		}
	}

	/**
	 * Modify AWB payload for PRO services
	 *
	 * @param array    $payload AWB shipment payload
	 * @param WC_Order $order   WooCommerce order object
	 * @return array Modified payload
	 */
	public static function modify_awb_payload($payload, $order) {
		if (!$order || !is_a($order, 'WC_Order')) {
			if (class_exists('HGEZLPFCR_Logger')) {
				HGEZLPFCR_Logger::error('PRO AWB: Invalid order object', ['order' => $order]);
			}
			return $payload;
		}

		// Get shipping method from order
		$shipping_methods = $order->get_shipping_methods();
		if (empty($shipping_methods)) {
			if (class_exists('HGEZLPFCR_Logger')) {
				HGEZLPFCR_Logger::log('PRO AWB: No shipping methods found for order', [
					'order_id' => $order->get_id(),
				]);
			}
			return $payload;
		}

		$shipping_method = reset($shipping_methods);
		$method_id = $shipping_method->get_method_id();

		if (class_exists('HGEZLPFCR_Logger')) {
			HGEZLPFCR_Logger::log('PRO AWB: Processing order', [
				'order_id'  => $order->get_id(),
				'method_id' => $method_id,
			]);
		}

		// Route to appropriate service handler
		switch ($method_id) {
			case 'fc_pro_fanbox':
				$payload = self::modify_fanbox_awb($payload, $order);
				break;

			case 'fc_pro_paypoint':
				$payload = self::modify_paypoint_awb($payload, $order);
				break;

			case 'fc_pro_omv':
				$payload = self::modify_omv_awb($payload, $order);
				break;

			case 'fc_pro_express_loco':
			case 'fc_pro_redcode':
			case 'fc_pro_produse_albe':
			case 'fc_pro_cargo':
			case 'fc_pro_export':
				// These will be implemented in future phases
				if (class_exists('HGEZLPFCR_Logger')) {
					HGEZLPFCR_Logger::log('PRO AWB: Service not yet implemented', [
						'method_id' => $method_id,
					]);
				}
				break;

			default:
				// Not a PRO service, return unchanged
				break;
		}

		return $payload;
	}

	/**
	 * Modify AWB payload for FANBox service
	 *
	 * @param array    $payload AWB shipment payload
	 * @param WC_Order $order   WooCommerce order object
	 * @return array Modified payload
	 */
	private static function modify_fanbox_awb($payload, $order) {
		// Get FANBox selection from order meta
		$fanbox_name = $order->get_meta('_hgezlpfcr_pro_fanbox_name');

		if (empty($fanbox_name)) {
			if (class_exists('HGEZLPFCR_Logger')) {
				HGEZLPFCR_Logger::error('PRO AWB FANBox: No FANBox name found in order meta', [
					'order_id' => $order->get_id(),
				]);
			}
			// Return payload unchanged - this will cause AWB generation to fail
			// which is correct behavior (admin should fix order data)
			return $payload;
		}

		// Add pickupLocation field for FANBox
		$payload['pickupLocation'] = sanitize_text_field($fanbox_name);

		// Ensure correct Service ID is set
		// 27 = FANBox Standard, 28 = FANBox with COD
		$is_cod = self::is_cod_order($order);
		$service_id = $is_cod ? 28 : 27;

		// Override serviceId if it exists in payload
		if (isset($payload['serviceId'])) {
			$payload['serviceId'] = $service_id;
		}

		if (class_exists('HGEZLPFCR_Logger')) {
			HGEZLPFCR_Logger::log('PRO AWB FANBox: Payload modified', [
				'order_id'        => $order->get_id(),
				'fanbox_name'     => $fanbox_name,
				'service_id'      => $service_id,
				'is_cod'          => $is_cod,
				'pickup_location' => $payload['pickupLocation'],
			]);
		}

		return $payload;
	}

	/**
	 * Modify AWB payload for PayPoint service
	 * (Placeholder for future implementation)
	 *
	 * @param array    $payload AWB shipment payload
	 * @param WC_Order $order   WooCommerce order object
	 * @return array Modified payload
	 */
	private static function modify_paypoint_awb($payload, $order) {
		// Get PayPoint selection from order meta
		$paypoint_id = $order->get_meta('_hgezlpfcr_pro_paypoint_id');

		if (empty($paypoint_id)) {
			if (class_exists('HGEZLPFCR_Logger')) {
				HGEZLPFCR_Logger::error('PRO AWB PayPoint: No PayPoint ID found in order meta', [
					'order_id' => $order->get_id(),
				]);
			}
			return $payload;
		}

		// Add pickupLocation field for PayPoint
		$payload['pickupLocation'] = sanitize_text_field($paypoint_id);

		// Set correct Service ID (TBD from API documentation)
		$is_cod = self::is_cod_order($order);
		$service_id = $is_cod ? 12 : 7; // PayPoint: 7 standard, 12 COD

		if ($service_id > 0 && isset($payload['serviceId'])) {
			$payload['serviceId'] = $service_id;
		}

		if (class_exists('HGEZLPFCR_Logger')) {
			HGEZLPFCR_Logger::log('PRO AWB PayPoint: Payload modified', [
				'order_id'        => $order->get_id(),
				'paypoint_id'     => $paypoint_id,
				'service_id'      => $service_id,
				'is_cod'          => $is_cod,
			]);
		}

		return $payload;
	}

	/**
	 * Modify AWB payload for OMV/Petrom service
	 * (Placeholder for future implementation)
	 *
	 * @param array    $payload AWB shipment payload
	 * @param WC_Order $order   WooCommerce order object
	 * @return array Modified payload
	 */
	private static function modify_omv_awb($payload, $order) {
		// Get OMV/Petrom selection from order meta
		$omv_id = $order->get_meta('_hgezlpfcr_pro_omv_id');

		if (empty($omv_id)) {
			if (class_exists('HGEZLPFCR_Logger')) {
				HGEZLPFCR_Logger::error('PRO AWB OMV: No OMV/Petrom ID found in order meta', [
					'order_id' => $order->get_id(),
				]);
			}
			return $payload;
		}

		// Add pickupLocation field for OMV/Petrom
		$payload['pickupLocation'] = sanitize_text_field($omv_id);

		// Set correct Service ID
		$is_cod = self::is_cod_order($order);
		$service_id = $is_cod ? 11 : 6; // OMV: 6 standard, 11 COD

		if ($service_id > 0 && isset($payload['serviceId'])) {
			$payload['serviceId'] = $service_id;
		}

		if (class_exists('HGEZLPFCR_Logger')) {
			HGEZLPFCR_Logger::log('PRO AWB OMV: Payload modified', [
				'order_id'    => $order->get_id(),
				'omv_id'      => $omv_id,
				'service_id'  => $service_id,
				'is_cod'      => $is_cod,
			]);
		}

		return $payload;
	}

	/**
	 * Check if order is COD (Cash on Delivery)
	 *
	 * @param WC_Order $order WooCommerce order object
	 * @return bool True if COD, false otherwise
	 */
	private static function is_cod_order($order) {
		$payment_method = $order->get_payment_method();
		return $payment_method === 'cod';
	}
}

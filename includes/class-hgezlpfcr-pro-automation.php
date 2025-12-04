<?php
if (!defined('ABSPATH')) exit;

class HGEZLPFCR_Pro_Automation {

    /**
     * Meta key to track if order was processed by PRO plugin
     */
    const META_PRO_PROCESSED = '_hgezlpfcr_pro_awb_generated';
    const META_PRO_CLOSED = '_hgezlpfcr_pro_auto_closed';

    /**
     * Initialize the automation system
     */
    public static function init() {
        // Hook into order status changes with high priority to run BEFORE other actions
        // Priority 5 ensures we run early in the process
        add_action('woocommerce_order_status_changed', [__CLASS__, 'handle_order_status_change'], 5, 4);

        // Hook AFTER AWB generation succeeds (called from HGEZLPFCR_Admin_Order)
        add_action('hgezlpfcr_awb_generated_successfully', [__CLASS__, 'handle_awb_generated'], 10, 2);

        // Register async action handler for auto-close
        add_action('hgezlpfcr_pro_auto_close_order_async', [__CLASS__, 'handle_auto_close_async'], 10, 2);

        // Add debug logging to verify hooks are registered
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('init', function() {
                if (class_exists('HGEZLPFCR_Logger')) {
                    HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Automation hooks initialized');
                }
            });
        }
    }

    /**
     * Handle order status changes - triggers auto AWB generation
     *
     * @param int $order_id Order ID
     * @param string $old_status Old order status
     * @param string $new_status New order status
     * @param WC_Order $order Order object
     */
    public static function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        // LICENSE CHECK: Verify license is active before running automations
        if (!class_exists('HGEZLPFCR_Pro_License_Manager') || !HGEZLPFCR_Pro_License_Manager::is_license_active()) {
            if (class_exists('HGEZLPFCR_Logger')) {
                HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Auto AWB skipped - no active license', [
                    'order_id' => $order_id
                ]);
            }
            return;
        }

        // Debug logging
        if (class_exists('HGEZLPFCR_Logger')) {
            HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Order status changed', [
                'order_id' => $order_id,
                'old_status' => $old_status,
                'new_status' => $new_status
            ]);
        }

        // Check if auto AWB generation should run for this status
        if (!HGEZLPFCR_Pro_Settings::should_auto_generate_awb($new_status)) {
            if (class_exists('HGEZLPFCR_Logger')) {
                HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Auto AWB not configured for this status', [
                    'order_id' => $order_id,
                    'new_status' => $new_status,
                    'configured_statuses' => get_option('hgezlpfcr_pro_auto_awb_statuses', [])
                ]);
            }
            return;
        }

        // Skip if AWB already exists
        if ($order->get_meta('_fc_awb_number')) {
            if (class_exists('HGEZLPFCR_Logger')) {
                HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] AWB already exists, skipping', ['order_id' => $order_id]);
            }
            return;
        }

        // Skip if we already processed this order
        if ($order->get_meta(self::META_PRO_PROCESSED)) {
            if (class_exists('HGEZLPFCR_Logger')) {
                HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Order already processed for auto AWB generation', ['order_id' => $order_id]);
            }
            return;
        }

        // Log the automatic generation attempt
        $order->add_order_note(
            sprintf(
                '[HGEZLPFCR PRO] Automatic AWB generation triggered by status change to: %s',
                wc_get_order_status_name($new_status)
            )
        );

        if (class_exists('HGEZLPFCR_Logger')) {
            HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Starting automatic AWB generation', [
                'order_id' => $order_id,
                'status' => $new_status
            ]);
        }

        // Mark as being processed to avoid duplicate processing
        $order->update_meta_data(self::META_PRO_PROCESSED, current_time('mysql'));
        $order->save();

        // Trigger AWB generation via HGEZLPFCR_Admin_Order
        // Check if async is enabled in the standard plugin settings
        if (class_exists('HGEZLPFCR_Settings') && HGEZLPFCR_Settings::yes('hgezlpfcr_async') && function_exists('as_enqueue_async_action')) {
            // Schedule async AWB generation using the standard plugin's action
            as_enqueue_async_action('hgezlpfcr_generate_awb_async', [$order_id], 'woo-fancourier');
            $order->add_order_note('[HGEZLPFCR PRO] AWB scheduled for async generation');

            if (class_exists('HGEZLPFCR_Logger')) {
                HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] AWB scheduled for async generation', ['order_id' => $order_id]);
            }
        } else {
            // Fallback: call the async handler directly if Action Scheduler is not available
            if (class_exists('HGEZLPFCR_Admin_Order') && method_exists('HGEZLPFCR_Admin_Order', 'create_awb_for_order_async')) {
                if (class_exists('HGEZLPFCR_Logger')) {
                    HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Calling AWB generation directly (no Action Scheduler)', ['order_id' => $order_id]);
                }
                HGEZLPFCR_Admin_Order::create_awb_for_order_async($order_id);
            } else {
                $order->add_order_note('[HGEZLPFCR PRO] ERROR: HGEZLPFCR_Admin_Order class or method not found');
                if (class_exists('HGEZLPFCR_Logger')) {
                    HGEZLPFCR_Logger::error('[HGEZLPFCR PRO] HGEZLPFCR_Admin_Order unavailable', [
                        'order_id' => $order_id,
                        'class_exists' => class_exists('HGEZLPFCR_Admin_Order'),
                        'method_exists' => method_exists('HGEZLPFCR_Admin_Order', 'create_awb_for_order_async')
                    ]);
                }
            }
        }
    }

    /**
     * Handle successful AWB generation - triggers auto order close if configured
     * This is called AFTER AWB has been successfully generated
     * Delegates to async processing to avoid blocking
     *
     * @param int $order_id Order ID
     * @param string $awb_number Generated AWB number
     */
    public static function handle_awb_generated($order_id, $awb_number) {
        // LICENSE CHECK: Verify license is active before running auto-close
        if (!class_exists('HGEZLPFCR_Pro_License_Manager') || !HGEZLPFCR_Pro_License_Manager::is_license_active()) {
            if (class_exists('HGEZLPFCR_Logger')) {
                HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Auto-close skipped - no active license', [
                    'order_id' => $order_id,
                    'awb' => $awb_number
                ]);
            }
            return;
        }

        // Quick validation
        $order = wc_get_order($order_id);
        if (!$order) {
            if (class_exists('HGEZLPFCR_Logger')) {
                HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Invalid order for auto-close', ['order_id' => $order_id]);
            }
            return;
        }

        // Check if auto-close is enabled at all (quick check to avoid scheduling unnecessary tasks)
        if (!HGEZLPFCR_Pro_Settings::yes('hgezlpfcr_pro_auto_close_order')) {
            return;
        }

        // Use async processing if Action Scheduler is available
        if (HGEZLPFCR_Pro_Settings::yes('hgezlpfcr_pro_use_async') && function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('hgezlpfcr_pro_auto_close_order_async', [$order_id, $awb_number], 'woo-fancourier-pro');

            if (class_exists('HGEZLPFCR_Logger')) {
                HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Auto-close scheduled async', [
                    'order_id' => $order_id,
                    'awb' => $awb_number
                ]);
            }
        } else {
            // Fallback to sync if Action Scheduler not available or async is disabled
            self::handle_auto_close_async($order_id, $awb_number);
        }
    }

    /**
     * Async handler for auto-close order
     * This runs in background via Action Scheduler with throttling protection
     *
     * @param int $order_id Order ID
     * @param string $awb_number AWB number
     */
    public static function handle_auto_close_async($order_id, $awb_number) {
        // Throttling: Check if we need to delay this operation
        $throttle_delay = (int) get_option('hgezlpfcr_pro_throttle_delay', 2);
        if ($throttle_delay > 0) {
            $last_close_time = get_transient('hgezlpfcr_pro_last_auto_close_time');
            if ($last_close_time) {
                $time_since_last = time() - $last_close_time;
                if ($time_since_last < $throttle_delay) {
                    $wait_time = $throttle_delay - $time_since_last;
                    if (class_exists('HGEZLPFCR_Logger')) {
                        HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Throttling: waiting before auto-close', [
                            'order_id' => $order_id,
                            'wait_seconds' => $wait_time
                        ]);
                    }
                    sleep($wait_time);
                }
            }
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            if (class_exists('HGEZLPFCR_Logger')) {
                HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Invalid order for auto-close (async)', ['order_id' => $order_id]);
            }
            return;
        }

        $current_status = $order->get_status();

        if (class_exists('HGEZLPFCR_Logger')) {
            HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] AWB generated - checking auto-close conditions', [
                'order_id' => $order_id,
                'awb' => $awb_number,
                'current_status' => $current_status
            ]);
        }

        // Check if auto-close should run
        if (!HGEZLPFCR_Pro_Settings::should_auto_close_order($current_status)) {
            if (class_exists('HGEZLPFCR_Logger')) {
                HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Auto-close not configured for this status', [
                    'order_id' => $order_id,
                    'current_status' => $current_status,
                    'configured_statuses' => get_option('hgezlpfcr_pro_auto_close_from_statuses', [])
                ]);
            }
            return;
        }

        // Skip if already auto-closed
        if ($order->get_meta(self::META_PRO_CLOSED)) {
            if (class_exists('HGEZLPFCR_Logger')) {
                HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Order already auto-closed, skipping', ['order_id' => $order_id]);
            }
            return;
        }

        // Check if invoice is required
        if (HGEZLPFCR_Pro_Settings::requires_invoice_for_auto_close()) {
            $has_invoice = false;
            $invoice_source = '';
            $invoice_details = [];

            // Bypass WooCommerce object cache to get fresh invoice data from DB
            // This is critical when SmartBill saves invoice AFTER AWB generation
            // Using get_post_meta() directly makes 2 simple indexed queries (~1ms each)
            // which are safe for MySQL performance and won't cause blocking
            // Process already runs ASYNC with throttling protection (see line 224-237)
            $invoice_log = get_post_meta($order_id, 'smartbill_invoice_log', true);
            $custom_invoice_code = get_post_meta($order_id, 'hge_factura_cod_unic_smartbill', true);

            // PRIORITY 1: Check official SmartBill plugin - only invoice number is required
            if (!empty($invoice_log) && is_array($invoice_log) && !empty($invoice_log['smartbill_invoice_id'])) {
                $has_invoice = true;
                $invoice_source = 'smartbill_official';
                $invoice_details = [
                    'series' => $invoice_log['smartbill_series'] ?? '',
                    'number' => $invoice_log['smartbill_invoice_id'],
                    'status' => $invoice_log['smartbill_status'] ?? 'unknown'
                ];
            }

            // FALLBACK: Check custom theme meta key
            if (!$has_invoice) {
                if (!empty($custom_invoice_code)) {
                    $has_invoice = true;
                    $invoice_source = 'custom_theme';
                    $invoice_details = ['code' => $custom_invoice_code];
                }
            }

            if (!$has_invoice) {
                $order->add_order_note('[HGEZLPFCR PRO] Automatic order completion cancelled: no SmartBill invoice found');
                if (class_exists('HGEZLPFCR_Logger')) {
                    HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Order not auto-closed - no SmartBill invoice', [
                        'order_id' => $order_id,
                        'smartbill_invoice_log' => !empty($invoice_log) ? $invoice_log : 'missing',
                        'hge_factura_cod_unic_smartbill' => $custom_invoice_code ?: 'missing'
                    ]);
                }
                return;
            } else {
                if (class_exists('HGEZLPFCR_Logger')) {
                    HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] SmartBill invoice found', [
                        'order_id' => $order_id,
                        'source' => $invoice_source,
                        'details' => $invoice_details,
                        'all_meta_keys' => [
                            'smartbill_invoice_log' => $invoice_log ?: 'missing',
                            'hge_factura_cod_unic_smartbill' => $custom_invoice_code ?: 'missing'
                        ]
                    ]);
                }
            }
        }

        // Get target status
        $target_status = HGEZLPFCR_Pro_Settings::get_auto_close_target_status();

        // Don't change if already in target status
        if ($current_status === $target_status) {
            if (class_exists('HGEZLPFCR_Logger')) {
                HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Order already in target auto-close status', [
                    'order_id' => $order_id,
                    'current_status' => $current_status,
                    'target_status' => $target_status
                ]);
            }
            return;
        }

        if (class_exists('HGEZLPFCR_Logger')) {
            HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Auto-closing order', [
                'order_id' => $order_id,
                'from_status' => $current_status,
                'to_status' => $target_status,
                'awb' => $awb_number
            ]);
        }

        // Change order status
        $order->update_status(
            $target_status,
            sprintf(
                '[HGEZLPFCR PRO] Order automatically completed after AWB %s generation. Previous status: %s',
                $awb_number,
                wc_get_order_status_name($current_status)
            )
        );

        // Mark as auto-closed
        $order->update_meta_data(self::META_PRO_CLOSED, current_time('mysql'));
        $order->update_meta_data('_hgezlpfcr_pro_closed_from_status', $current_status);
        $order->update_meta_data('_hgezlpfcr_pro_closed_to_status', $target_status);
        $order->update_meta_data('_hgezlpfcr_pro_closed_awb', $awb_number);
        $order->save();

        // Update throttling timestamp
        set_transient('hgezlpfcr_pro_last_auto_close_time', time(), 60); // Store for 60 seconds

        if (class_exists('HGEZLPFCR_Logger')) {
            HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Order successfully completed', [
                'order_id' => $order_id,
                'new_status' => $target_status,
                'throttle_delay' => get_option('hgezlpfcr_pro_throttle_delay', 2) . 's'
            ]);
        }
    }
}

<?php
if (!defined('ABSPATH')) exit;

class HGEZLPFCR_Pro_Settings {
    public static function init() {
        // Add PRO section to HGEZLPFCR tab
        add_filter('woocommerce_get_sections_hgezlpfcr', [__CLASS__, 'add_pro_section'], 10);

        // Add PRO settings for the PRO section
        add_filter('woocommerce_get_settings_hgezlpfcr', [__CLASS__, 'get_pro_settings'], 10, 2);

        // Register custom field type for license HTML
        add_action('woocommerce_admin_field_hgezlpfcr_pro_license_html', [__CLASS__, 'render_license_field']);
    }

    /**
     * Add PRO section to Fan Courier tab
     *
     * @param array $sections Existing sections
     * @return array Modified sections with PRO added
     */
    public static function add_pro_section($sections) {
        // Add License section first
        $sections['license'] = __('PRO License', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');

        // Add PRO Automations section
        $sections['pro'] = __('PRO Automations', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');

        if (class_exists('HGEZLPFCR_Logger')) {
            HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Adding PRO sections to FC tab', [
                'sections' => array_keys($sections)
            ]);
        }

        return $sections;
    }

    /**
     * Get PRO settings for the PRO section
     *
     * @param array $settings Existing settings
     * @param string $current_section Current section ID
     * @return array Settings for current section
     */
    public static function get_pro_settings($settings, $current_section) {
        // Handle License section
        if ($current_section === 'license') {
            return self::get_license_settings();
        }

        // Only return PRO settings if we're in the PRO section
        if ($current_section !== 'pro') {
            return $settings;
        }

        // Debug logging
        if (class_exists('HGEZLPFCR_Logger')) {
            HGEZLPFCR_Logger::log('[HGEZLPFCR PRO] Loading PRO settings', [
                'section' => $current_section
            ]);
        }

        // Return ONLY PRO settings for this section
        $pro_settings = [
            [
                'title' => __('HgE PRO: Advanced Automations for FAN Courier Romania', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
                'type' => 'title',
                'desc' => 'Configure advanced automations for FAN Courier orders. These features require the HgE: Shipping Zones for FAN Courier Romania plugin to be active.',
                'id' => 'hgezlpfcr_pro_section'
            ],

            // SECTION 1: Auto AWB Generation
            [
                'title' => __('Automatic AWB Generation', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
                'type' => 'title',
                'desc' => 'Configure which order statuses should trigger automatic AWB generation for FAN Courier.',
                'id' => 'hgezlpfcr_pro_auto_awb_section'
            ],
            [
                'title' => 'Enable automatic AWB generation?',
                'id' => 'hgezlpfcr_pro_auto_awb_enabled',
                'type' => 'select',
                'desc' => 'If enabled, AWB will be generated automatically for the selected statuses below',
                'options' => ['no' => 'No', 'yes' => 'Yes'],
                'default' => 'no',
                'css' => 'min-width:300px'
            ],
            [
                'title' => 'Order statuses for automatic AWB generation',
                'desc' => 'Select the order statuses that should trigger automatic AWB generation. When an order reaches one of these statuses, the AWB will be generated automatically.',
                'id' => 'hgezlpfcr_pro_auto_awb_statuses',
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'options' => self::get_order_statuses_options(),
                'default' => '',
                'css' => 'min-width:300px'
            ],
            ['type' => 'sectionend', 'id' => 'hgezlpfcr_pro_auto_awb_section'],

            // SECTION 2: Auto Close Orders
            [
                'title' => __('Automatic Order Completion', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
                'type' => 'title',
                'desc' => 'Configure automatic order completion after AWB generation. This action executes AFTER the AWB has been generated.',
                'id' => 'hgezlpfcr_pro_auto_close_section'
            ],
            [
                'title' => 'Automatically complete order after AWB generation?',
                'id' => 'hgezlpfcr_pro_auto_close_order',
                'type' => 'select',
                'desc' => 'If enabled, the order will be automatically moved to the configured status below after the AWB has been successfully generated.',
                'options' => ['no' => 'No', 'yes' => 'Yes'],
                'default' => 'no',
                'css' => 'min-width:300px'
            ],
            [
                'title' => 'Apply only for these initial statuses',
                'desc' => 'Select the order statuses for which automatic completion should apply. Leave empty to apply for all statuses.',
                'id' => 'hgezlpfcr_pro_auto_close_from_statuses',
                'type' => 'multiselect',
                'class' => 'wc-enhanced-select',
                'options' => self::get_order_statuses_options(),
                'default' => '',
                'css' => 'min-width:300px'
            ],
            [
                'title' => 'New order status',
                'desc' => 'Select the status to which the order should be moved after AWB generation',
                'id' => 'hgezlpfcr_pro_auto_close_status',
                'type' => 'select',
                'options' => self::get_order_statuses_options(),
                'default' => 'completed',
                'css' => 'min-width:300px'
            ],
            [
                'title' => 'Only for orders with SmartBill invoice?',
                'id' => 'hgezlpfcr_pro_auto_close_only_with_invoice',
                'type' => 'checkbox',
                'desc' => 'Order will be completed only if a SmartBill invoice has been issued.',
                'default' => 'no'
            ],
            ['type' => 'sectionend', 'id' => 'hgezlpfcr_pro_auto_close_section'],

            // SECTION 3: Performance & Throttling
            [
                'title' => __('Performance and Server Protection', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
                'type' => 'title',
                'desc' => 'Configure limitations to protect your server and database from overload.',
                'id' => 'hgezlpfcr_pro_performance_section'
            ],
            [
                'title' => 'Asynchronous processing (Action Scheduler)',
                'id' => 'hgezlpfcr_pro_use_async',
                'type' => 'checkbox',
                'desc' => 'Use Action Scheduler for background processing (HIGHLY recommended)',
                'default' => 'yes'
            ],
            [
                'title' => 'Delay between automatic completions (seconds)',
                'id' => 'hgezlpfcr_pro_throttle_delay',
                'type' => 'number',
                'desc' => 'Minimum delay between automatic completions to avoid database overload. 0 = no delay.',
                'default' => '2',
                'custom_attributes' => ['min' => 0, 'max' => 60, 'step' => 1],
                'css' => 'min-width:100px'
            ],
            ['type' => 'sectionend', 'id' => 'hgezlpfcr_pro_performance_section'],

            // SECTION 4: Execution Order Info
            [
                'title' => __('Execution Order', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
                'type' => 'title',
                'desc' => '<strong>Automatic execution order:</strong><br>
                          1️⃣ Order status change is detected<br>
                          2️⃣ If status is in "Automatic AWB generation" list → AWB is generated <em>(async)</em><br>
                          3️⃣ After successful AWB generation → check if order should be completed<br>
                          4️⃣ If "Automatic completion" is enabled and conditions are met → order is moved to new status <em>(async with throttling)</em>',
                'id' => 'hgezlpfcr_pro_execution_order_section'
            ],
            ['type' => 'sectionend', 'id' => 'hgezlpfcr_pro_execution_order_section'],
        ];

        return $pro_settings;
    }

    /**
     * Get all WooCommerce order statuses for dropdown
     */
    public static function get_order_statuses_options() {
        $statuses = wc_get_order_statuses();
        $options = [];
        foreach ($statuses as $slug => $label) {
            // Remove "wc-" prefix from slug
            $slug = str_replace('wc-', '', $slug);
            $options[$slug] = $label;
        }
        return $options;
    }

    /**
     * Get setting value
     */
    public static function get($key, $default = '') {
        return get_option($key, $default);
    }

    /**
     * Check if setting is enabled (yes/no)
     */
    public static function yes($key) {
        return 'yes' === get_option($key, 'no');
    }

    /**
     * Check if auto AWB generation should run for given order status
     */
    public static function should_auto_generate_awb($order_status) {
        // Check if auto AWB is enabled
        if (!self::yes('hgezlpfcr_pro_auto_awb_enabled')) {
            return false;
        }

        // Get configured statuses
        $configured_statuses = get_option('hgezlpfcr_pro_auto_awb_statuses', []);

        // If no statuses configured, don't auto-generate
        if (empty($configured_statuses) || !is_array($configured_statuses)) {
            return false;
        }

        // Check if current order status is in the configured list
        return in_array($order_status, $configured_statuses);
    }

    /**
     * Check if order should be auto-closed after AWB generation
     */
    public static function should_auto_close_order($order_status) {
        // Check if auto close is enabled
        if (!self::yes('hgezlpfcr_pro_auto_close_order')) {
            return false;
        }

        // Get configured "from" statuses
        $from_statuses = get_option('hgezlpfcr_pro_auto_close_from_statuses', []);

        // If specific statuses are configured, check if current status is in the list
        if (!empty($from_statuses) && is_array($from_statuses)) {
            if (!in_array($order_status, $from_statuses)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the target status for auto-close
     */
    public static function get_auto_close_target_status() {
        return get_option('hgezlpfcr_pro_auto_close_status', 'completed');
    }

    /**
     * Check if invoice is required for auto-close
     */
    public static function requires_invoice_for_auto_close() {
        return self::yes('hgezlpfcr_pro_auto_close_only_with_invoice');
    }

    /**
     * Get License settings section
     */
    public static function get_license_settings() {
        return [
            [
                'title' => __('HgE PRO License', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
                'type' => 'title',
                'desc' => '',
                'id' => 'hgezlpfcr_pro_license_section'
            ],
            [
                'type' => 'hgezlpfcr_pro_license_html',
                'id' => 'hgezlpfcr_pro_license_field'
            ],
            [
                'type' => 'sectionend',
                'id' => 'hgezlpfcr_pro_license_section'
            ],
        ];
    }

    /**
     * Render custom license HTML field
     */
    public static function render_license_field() {
        $license_key = get_option('hgezlpfcr_pro_license_key', '');
        $license_status = get_option('hgezlpfcr_pro_license_status', 'inactive');
        $license_data = get_option('hgezlpfcr_pro_license_data', []);
        $expires_at = $license_data['expires_at'] ?? null;

        ?>
        <tr valign="top">
            <td colspan="2" style="padding: 0;">
                <div class="hgezlpfcr-pro-license-wrapper" style="max-width: 800px;">
                    <!-- License Status Card -->
                    <div class="hgezlpfcr-license-status-card" style="background: <?php echo $license_status === 'active' ? '#d4edda' : '#f8d7da'; ?>; border: 1px solid <?php echo $license_status === 'active' ? '#c3e6cb' : '#f5c6cb'; ?>; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <h3 style="margin: 0 0 10px 0; color: <?php echo $license_status === 'active' ? '#155724' : '#721c24'; ?>;">
                            <?php if ($license_status === 'active'): ?>
                                ✅ <?php esc_html_e('License Active', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>
                            <?php else: ?>
                                ❌ <?php esc_html_e('License Inactive', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>
                            <?php endif; ?>
                        </h3>

                        <?php if ($license_status === 'active' && $expires_at): ?>
                            <p style="margin: 5px 0; color: #666;">
                                <strong><?php esc_html_e('Expires:', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?></strong>
                                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($expires_at))); ?>
                                <?php
                                $days_remaining = floor((strtotime($expires_at) - time()) / DAY_IN_SECONDS);
                                if ($days_remaining < 30 && $days_remaining > 0) {
                                    echo '<span style="color: #d63638;"> (' . esc_html($days_remaining) . ' ' . esc_html__('days remaining', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro') . ')</span>';
                                }
                                ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($license_status === 'active'): ?>
                            <p style="margin: 5px 0; color: #666;">
                                <strong><?php esc_html_e('Domain:', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?></strong>
                                <?php echo esc_html(site_url()); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- License Form -->
                    <div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; border-radius: 4px;">
                        <h3 style="margin-top: 0;"><?php esc_html_e('License Key', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?></h3>

                        <?php if ($license_status === 'active'): ?>
                            <!-- Deactivate Form -->
                            <form id="hgezlpfcr-deactivate-form" method="post">
                                <?php wp_nonce_field('hgezlpfcr_pro_license_action', 'hgezlpfcr_pro_nonce'); ?>
                                <p>
                                    <input type="text" value="<?php echo esc_attr($license_key); ?>" class="regular-text" disabled readonly style="background: #f0f0f0; font-family: monospace;">
                                </p>
                                <p>
                                    <button type="submit" class="button button-secondary" id="deactivate-license-btn">
                                        <?php esc_html_e('Deactivate License', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>
                                    </button>
                                    <button type="button" class="button" id="check-license-btn">
                                        <?php esc_html_e('Check Status', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>
                                    </button>
                                </p>
                            </form>
                        <?php else: ?>
                            <!-- Activate Form -->
                            <form id="hgezlpfcr-activate-form" method="post">
                                <?php wp_nonce_field('hgezlpfcr_pro_license_action', 'hgezlpfcr_pro_nonce'); ?>
                                <p>
                                    <label for="license_key"><strong><?php esc_html_e('Enter your license key:', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?></strong></label>
                                </p>
                                <p>
                                    <input type="text" name="license_key" id="license_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" placeholder="XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX" required style="font-family: monospace;">
                                </p>
                                <p>
                                    <button type="submit" class="button button-primary" id="activate-license-btn">
                                        <?php esc_html_e('Activate License', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>
                                    </button>
                                </p>
                                <p class="description">
                                    <?php
                                    printf(
                                        /* translators: %s: purchase URL */
                                        esc_html__('Don\'t have a license? %s', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
                                        '<a href="https://web-production-c8792.up.railway.app/checkout.html" target="_blank">' . esc_html__('Purchase one here', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro') . '</a>'
                                    );
                                    ?>
                                </p>
                            </form>
                        <?php endif; ?>

                        <!-- Response Message -->
                        <div id="license-response-message" style="display: none; margin-top: 15px;"></div>
                    </div>
                </div>
            </td>
        </tr>

        <script>
        jQuery(document).ready(function($) {
            // Activate License
            $('#hgezlpfcr-activate-form').on('submit', function(e) {
                e.preventDefault();
                var $btn = $('#activate-license-btn');
                var $msg = $('#license-response-message');
                var licenseKey = $('input[name="license_key"]').val();

                $btn.prop('disabled', true).text('<?php esc_html_e('Activating...', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>');
                $msg.hide();

                $.post(ajaxurl, {
                    action: 'hgezlpfcr_pro_activate_license',
                    nonce: $('input[name="hgezlpfcr_pro_nonce"]').val(),
                    license_key: licenseKey
                }, function(response) {
                    if (response.success) {
                        $msg.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>').show();
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $msg.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>').show();
                        $btn.prop('disabled', false).text('<?php esc_html_e('Activate License', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>');
                    }
                });
            });

            // Deactivate License
            $('#hgezlpfcr-deactivate-form').on('submit', function(e) {
                e.preventDefault();
                if (!confirm('<?php esc_html_e('Are you sure you want to deactivate this license?', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>')) return;

                var $btn = $('#deactivate-license-btn');
                var $msg = $('#license-response-message');

                $btn.prop('disabled', true).text('<?php esc_html_e('Deactivating...', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>');
                $msg.hide();

                $.post(ajaxurl, {
                    action: 'hgezlpfcr_pro_deactivate_license',
                    nonce: $('input[name="hgezlpfcr_pro_nonce"]').val()
                }, function(response) {
                    if (response.success) {
                        $msg.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>').show();
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        $msg.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>').show();
                        $btn.prop('disabled', false).text('<?php esc_html_e('Deactivate License', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>');
                    }
                });
            });

            // Check License Status
            $('#check-license-btn').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $msg = $('#license-response-message');

                $btn.prop('disabled', true).text('<?php esc_html_e('Checking...', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>');
                $msg.hide();

                $.post(ajaxurl, {
                    action: 'hgezlpfcr_pro_check_license',
                    nonce: $('input[name="hgezlpfcr_pro_nonce"]').val()
                }, function(response) {
                    if (response.success) {
                        $msg.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>').show();
                    } else {
                        $msg.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>').show();
                    }
                    $btn.prop('disabled', false).text('<?php esc_html_e('Check Status', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>');
                    setTimeout(function() { location.reload(); }, 2000);
                });
            });
        });
        </script>
        <?php
    }
}

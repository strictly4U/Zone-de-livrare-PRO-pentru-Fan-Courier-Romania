<?php
/**
 * Plugin Name: HgE PRO: Additional Shipping Services for FAN Courier Romania
 * Plugin URI: https://github.com/georgeshurubaru/FcRapid1923
 * Description: Premium extension adding FANBox lockers, Express Loco, RedCode same-day, CollectPoint (PayPoint/OMV/Petrom), White Products, Cargo and Export shipping services. Requires "HgE: Shipping Zones for FAN Courier Romania" base plugin.
 * Version: 2.0.3
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 3.0
 * WC tested up to: 9.7
 * Text Domain: hge-zone-de-livrare-pentru-fan-courier-romania-pro
 * Domain Path: /languages
 * Author: Hurubaru George Emanuel
 * Author URI: https://www.linkedin.com/in/hurubarugeorgesemanuel/
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

// Plugin version and paths
define('HGEZLPFCR_PRO_PLUGIN_FILE', __FILE__);
define('HGEZLPFCR_PRO_VERSION', '2.0.3');
define('HGEZLPFCR_PRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HGEZLPFCR_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HGEZLPFCR_PRO_MIN_STANDARD_VERSION', '1.0.2'); // Minimum required Standard version

/**
 * Plugin Activation Hook
 * Runs when plugin is activated
 */
register_activation_hook(__FILE__, function () {
    // Set flag to show activation notice
    add_option('hgezlpfcr_pro_activation_redirect', true);

    // Check minimum requirements before activation
    if (version_compare(PHP_VERSION, '8.1', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            sprintf(
                /* translators: %s: PHP version */
                esc_html__('HgE PRO requires PHP 8.1 or higher. Your current PHP version is %s.', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
                esc_html(PHP_VERSION)
            )
        );
    }
});

/**
 * Plugin Deactivation Hook
 * Runs when plugin is deactivated
 */
register_deactivation_hook(__FILE__, function () {
    // Clean up temporary data on deactivation
    delete_transient('hgezlpfcr_pro_last_auto_close_time');

    // Do NOT delete permanent settings - user might reactivate later
    // Full cleanup is handled in uninstall.php
});

// Add Settings link in plugins list
add_filter('plugin_action_links_' . plugin_basename(HGEZLPFCR_PRO_PLUGIN_FILE), function ($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=hgezlpfcr&section=pro') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
});

// Add plugin meta links
add_filter('plugin_row_meta', function ($plugin_meta, $plugin_file) {
    if (plugin_basename(HGEZLPFCR_PRO_PLUGIN_FILE) === $plugin_file) {
        $plugin_meta[] = '<a href="' . admin_url('admin.php?page=wc-settings&tab=hgezlpfcr&section=pro') . '">PRO Configuration</a>';
        $plugin_meta[] = '<a href="https://github.com/georgeshurubaru/FcRapid1923/wiki" target="_blank">Documentation</a>';
    }
    return $plugin_meta;
}, 10, 2);

// Show admin notice after plugin activation
add_action('admin_notices', function () {
    if (get_option('hgezlpfcr_pro_activation_redirect', false)) {
        // Don't show notice if already on the settings page
        $current_screen = get_current_screen();
        if ($current_screen && $current_screen->id === 'woocommerce_page_wc-settings') {
            // Check if we're on the PRO section
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check
            if (isset($_GET['tab']) && sanitize_key($_GET['tab']) === 'hgezlpfcr' &&
                isset($_GET['section']) && sanitize_key($_GET['section']) === 'pro') {
                delete_option('hgezlpfcr_pro_activation_redirect');
                return;
            }
        }

        $settings_url = admin_url('admin.php?page=wc-settings&tab=hgezlpfcr&section=pro');

        // Enqueue inline script for notice dismissal
        wp_enqueue_script('jquery');
        $inline_script = "
            jQuery(document).ready(function($) {
                $('.hgezlpfcr-pro-activation-notice').on('click', '.notice-dismiss', function() {
                    $.post(ajaxurl, {
                        action: 'hgezlpfcr_pro_dismiss_activation_notice'
                    });
                });
                // Auto-dismiss when clicking the button
                $('.hgezlpfcr-pro-activation-notice .button').on('click', function() {
                    $.post(ajaxurl, {
                        action: 'hgezlpfcr_pro_dismiss_activation_notice'
                    });
                });
            });
        ";
        wp_add_inline_script('jquery', $inline_script);

        ?>
        <div class="notice notice-success is-dismissible hgezlpfcr-pro-activation-notice">
            <p>
                <strong>✓ HgE PRO: Advanced Automations for FAN Courier Romania</strong> has been activated successfully!
                <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary" style="margin-left: 10px;">Configure automations now</a>
            </p>
            <p style="margin-top: 5px;">
                <em>Configure automatic AWB generation and automatic order completion.</em>
            </p>
        </div>
        <?php
    }
});

// AJAX handler to dismiss activation notice
add_action('wp_ajax_hgezlpfcr_pro_dismiss_activation_notice', function() {
    delete_option('hgezlpfcr_pro_activation_redirect');
    wp_die();
});

// Check dependencies on admin_init
add_action('admin_init', function() {
    // Include plugin.php for is_plugin_active function
    if (!function_exists('is_plugin_active')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    // Check for WooCommerce
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong>HgE PRO: Additional Shipping Services for FAN Courier Romania</strong> requires <strong>WooCommerce</strong> to be installed and activated.
                    The plugin has been deactivated automatically.
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')); ?>" class="button button-primary">
                        Install WooCommerce
                    </a>
                </p>
            </div>
            <?php
        });
        deactivate_plugins(plugin_basename(__FILE__));
        delete_option('hgezlpfcr_pro_activation_redirect');
        return;
    }

    // Check for HgE: Shipping Zones for FAN Courier Romania plugin (Standard)
    // We check if the required classes exist instead of checking for specific plugin file
    // This allows the standard plugin to work from any folder/version
    if (!class_exists('HGEZLPFCR_Settings') || !class_exists('HGEZLPFCR_Admin_Order')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong>HgE PRO: Additional Shipping Services for FAN Courier Romania</strong> requires the <strong>HgE: Shipping Zones for FAN Courier Romania</strong> (Standard) plugin to be installed and activated.
                    The plugin has been deactivated automatically.
                </p>
                <p>
                    Please install and activate the <strong>HgE: Shipping Zones for FAN Courier Romania</strong> plugin first, then reactivate the PRO version.
                </p>
            </div>
            <?php
        });
        deactivate_plugins(plugin_basename(__FILE__));
        delete_option('hgezlpfcr_pro_activation_redirect');
        return;
    }

    // Check Standard plugin version compatibility
    if (defined('HGEZLPFCR_PLUGIN_VER')) {
        if (version_compare(HGEZLPFCR_PLUGIN_VER, HGEZLPFCR_PRO_MIN_STANDARD_VERSION, '<')) {
            add_action('admin_notices', function() {
                $current_version = esc_html(HGEZLPFCR_PLUGIN_VER);
                $required_version = esc_html(HGEZLPFCR_PRO_MIN_STANDARD_VERSION);
                ?>
                <div class="notice notice-error is-dismissible">
                    <p>
                        <strong>HgE PRO: Additional Shipping Services for FAN Courier Romania</strong> requires <strong>HgE: Shipping Zones for FAN Courier Romania</strong> version <strong><?php echo $required_version; ?></strong> or higher.
                    </p>
                    <p>
                        Your current version is <strong><?php echo $current_version; ?></strong>. Please update the Standard plugin to continue using PRO features.
                    </p>
                    <p>
                        <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="button button-primary">
                            Go to Plugins
                        </a>
                    </p>
                </div>
                <?php
            });
            deactivate_plugins(plugin_basename(__FILE__));
            delete_option('hgezlpfcr_pro_activation_redirect');
            return;
        }
    }
});

// Load plugin components
// Priority 20 ensures this runs AFTER the standard plugin (which runs at default priority 10)
add_action('plugins_loaded', function () {
    // Skip loading if WooCommerce is not available
    if (!class_exists('WooCommerce')) {
        return;
    }

    // Skip loading if HgE: Shipping Zones for FAN Courier Romania is not available
    // Dependencies are already checked in admin_init with proper notices
    if (!class_exists('HGEZLPFCR_Settings') || !class_exists('HGEZLPFCR_Admin_Order')) {
        return;
    }

    // Load core PRO classes
    require_once HGEZLPFCR_PRO_PLUGIN_DIR . 'includes/class-hgezlpfcr-pro-license-manager.php';
    require_once HGEZLPFCR_PRO_PLUGIN_DIR . 'includes/class-hgezlpfcr-pro-settings.php';
    require_once HGEZLPFCR_PRO_PLUGIN_DIR . 'includes/class-hgezlpfcr-pro-automation.php';
    require_once HGEZLPFCR_PRO_PLUGIN_DIR . 'includes/class-hgezlpfcr-pro-service-registry.php';
    require_once HGEZLPFCR_PRO_PLUGIN_DIR . 'includes/class-hgezlpfcr-pro-awb-integration.php';

    // Initialize License Manager FIRST
    HGEZLPFCR_Pro_License_Manager::init();

    // Initialize settings and automations
    HGEZLPFCR_Pro_Settings::init();
    HGEZLPFCR_Pro_Automation::init();

    // Initialize Service Registry (handles shipping methods)
    HGEZLPFCR_Pro_Service_Registry::init();

    // Initialize AWB Integration (modifies AWB payload for PRO services)
    HGEZLPFCR_Pro_AWB_Integration::init();
}, 20);

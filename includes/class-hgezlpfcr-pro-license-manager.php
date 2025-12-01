<?php
/**
 * License Manager
 * Gestionează verificarea și activarea licențelor PRO
 *
 * @package HgE_Pro_FAN_Courier
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * HGEZLPFCR_Pro_License_Manager class
 *
 * Manages license activation, verification, and updates
 */
class HGEZLPFCR_Pro_License_Manager {

	/**
	 * API Base URL
	 */
	const API_BASE_URL = 'https://web-production-c8792.up.railway.app/api/v1/';

	/**
	 * Product ID for this plugin
	 */
	const PRODUCT_ID = 'hge-zone-livrare-fan-courier-pro';

	/**
	 * Cache duration (12 hours)
	 */
	const CACHE_DURATION = 12 * HOUR_IN_SECONDS;

	/**
	 * Initialize License Manager
	 */
	public static function init() {
		// Add license settings page
		add_action('admin_menu', [__CLASS__, 'add_license_page'], 99);

		// Check license status daily
		add_action('admin_init', [__CLASS__, 'maybe_check_license']);

		// Admin notices for license status
		add_action('admin_notices', [__CLASS__, 'license_admin_notices']);

		// AJAX handlers
		add_action('wp_ajax_hgezlpfcr_pro_activate_license', [__CLASS__, 'ajax_activate_license']);
		add_action('wp_ajax_hgezlpfcr_pro_deactivate_license', [__CLASS__, 'ajax_deactivate_license']);
		add_action('wp_ajax_hgezlpfcr_pro_check_license', [__CLASS__, 'ajax_check_license']);

		HGEZLPFCR_Logger::log('PRO License Manager initialized');
	}

	/**
	 * Add license page to admin menu
	 */
	public static function add_license_page() {
		add_submenu_page(
			'woocommerce',
			__('HgE PRO License', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
			__('HgE PRO License', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
			'manage_woocommerce',
			'hgezlpfcr-pro-license',
			[__CLASS__, 'render_license_page']
		);
	}

	/**
	 * Render license activation page
	 */
	public static function render_license_page() {
		$license_key = get_option('hgezlpfcr_pro_license_key', '');
		$license_status = get_option('hgezlpfcr_pro_license_status', 'inactive');
		$license_data = get_option('hgezlpfcr_pro_license_data', []);
		$expires_at = $license_data['expires_at'] ?? null;

		?>
		<div class="wrap">
			<h1><?php esc_html_e('HgE PRO License Activation', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?></h1>

			<div class="hgezlpfcr-pro-license-wrapper" style="max-width: 800px; margin-top: 20px;">
				<!-- License Status Card -->
				<div class="hgezlpfcr-license-status-card" style="background: <?php echo $license_status === 'active' ? '#d4edda' : '#f8d7da'; ?>; border: 1px solid <?php echo $license_status === 'active' ? '#c3e6cb' : '#f5c6cb'; ?>; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
					<h2 style="margin: 0 0 10px 0; color: <?php echo $license_status === 'active' ? '#155724' : '#721c24'; ?>;">
						<?php if ($license_status === 'active'): ?>
							✅ <?php esc_html_e('License Active', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>
						<?php else: ?>
							❌ <?php esc_html_e('License Inactive', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>
						<?php endif; ?>
					</h2>

					<?php if ($license_status === 'active' && $expires_at): ?>
						<p style="margin: 5px 0; color: #666;">
							<strong><?php esc_html_e('Expires:', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?></strong>
							<?php echo esc_html(date_i18n(get_option('date_format'), strtotime($expires_at))); ?>
							<?php
							$days_remaining = floor((strtotime($expires_at) - time()) / DAY_IN_SECONDS);
							if ($days_remaining < 30) {
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
						<p style="margin: 5px 0; color: #666;">
							<strong><?php esc_html_e('Plan:', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?></strong>
							<?php echo esc_html(ucfirst($license_data['plan_type'] ?? 'monthly')); ?>
						</p>
					<?php endif; ?>
				</div>

				<!-- License Form -->
				<div class="card" style="padding: 20px;">
					<h2><?php esc_html_e('License Key', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?></h2>

					<?php if ($license_status === 'active'): ?>
						<!-- Deactivate Form -->
						<form id="hgezlpfcr-deactivate-form" method="post">
							<?php wp_nonce_field('hgezlpfcr_pro_license_action', 'hgezlpfcr_pro_nonce'); ?>

							<p>
								<input type="text" name="license_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" disabled readonly style="background: #f0f0f0;">
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
								<label for="license_key">
									<strong><?php esc_html_e('Enter your license key:', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?></strong>
								</label>
							</p>

							<p>
								<input type="text" name="license_key" id="license_key" value="<?php echo esc_attr($license_key); ?>" class="regular-text" placeholder="XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX" required>
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

				<!-- Features Info -->
				<div class="card" style="padding: 20px; margin-top: 20px;">
					<h2><?php esc_html_e('PRO Features', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?></h2>
					<ul style="list-style: none; padding: 0;">
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<span style="color: #2271b1; font-size: 20px; margin-right: 10px;">📦</span>
							<strong>FANBox Lockers</strong> - Livrare în lockere 24/7
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<span style="color: #2271b1; font-size: 20px; margin-right: 10px;">⚡</span>
							<strong>Express Loco 2H</strong> - Livrare în 2 ore
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<span style="color: #2271b1; font-size: 20px; margin-right: 10px;">🔴</span>
							<strong>RedCode</strong> - Same-day delivery
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<span style="color: #2271b1; font-size: 20px; margin-right: 10px;">🏪</span>
							<strong>CollectPoint</strong> - PayPoint & OMV/Petrom
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<span style="color: #2271b1; font-size: 20px; margin-right: 10px;">📺</span>
							<strong>Produse Albe</strong> - Transport electronice
						</li>
						<li style="padding: 8px 0; border-bottom: 1px solid #f0f0f0;">
							<span style="color: #2271b1; font-size: 20px; margin-right: 10px;">🚛</span>
							<strong>Cargo</strong> - Palete și greutăți mari
						</li>
						<li style="padding: 8px 0;">
							<span style="color: #2271b1; font-size: 20px; margin-right: 10px;">🌍</span>
							<strong>Export</strong> - Livrări internaționale
						</li>
					</ul>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			// Activate License
			$('#hgezlpfcr-activate-form').on('submit', function(e) {
				e.preventDefault();

				var $form = $(this);
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
						$msg.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').show();
						setTimeout(function() {
							location.reload();
						}, 1500);
					} else {
						$msg.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').show();
						$btn.prop('disabled', false).text('<?php esc_html_e('Activate License', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>');
					}
				});
			});

			// Deactivate License
			$('#hgezlpfcr-deactivate-form').on('submit', function(e) {
				e.preventDefault();

				if (!confirm('<?php esc_html_e('Are you sure you want to deactivate this license?', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>')) {
					return;
				}

				var $btn = $('#deactivate-license-btn');
				var $msg = $('#license-response-message');

				$btn.prop('disabled', true).text('<?php esc_html_e('Deactivating...', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>');
				$msg.hide();

				$.post(ajaxurl, {
					action: 'hgezlpfcr_pro_deactivate_license',
					nonce: $('input[name="hgezlpfcr_pro_nonce"]').val()
				}, function(response) {
					if (response.success) {
						$msg.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').show();
						setTimeout(function() {
							location.reload();
						}, 1500);
					} else {
						$msg.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').show();
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
						$msg.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').show();
					} else {
						$msg.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').show();
					}
					$btn.prop('disabled', false).text('<?php esc_html_e('Check Status', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>');

					setTimeout(function() {
						location.reload();
					}, 2000);
				});
			});
		});
		</script>

		<style>
		.hgezlpfcr-pro-license-wrapper input[type="text"] {
			font-family: 'Courier New', monospace;
			font-size: 14px;
			letter-spacing: 1px;
		}
		</style>
		<?php
	}

	/**
	 * AJAX: Activate License
	 */
	public static function ajax_activate_license() {
		check_ajax_referer('hgezlpfcr_pro_license_action', 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => __('Permission denied', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro')]);
		}

		$license_key = isset($_POST['license_key']) ? sanitize_text_field(wp_unslash($_POST['license_key'])) : '';

		if (empty($license_key)) {
			wp_send_json_error(['message' => __('License key is required', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro')]);
		}

		$result = self::activate_license($license_key);

		if (is_wp_error($result)) {
			wp_send_json_error(['message' => $result->get_error_message()]);
		}

		wp_send_json_success(['message' => __('License activated successfully!', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro')]);
	}

	/**
	 * AJAX: Deactivate License
	 */
	public static function ajax_deactivate_license() {
		check_ajax_referer('hgezlpfcr_pro_license_action', 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => __('Permission denied', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro')]);
		}

		$result = self::deactivate_license();

		if (is_wp_error($result)) {
			wp_send_json_error(['message' => $result->get_error_message()]);
		}

		wp_send_json_success(['message' => __('License deactivated successfully', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro')]);
	}

	/**
	 * AJAX: Check License Status
	 */
	public static function ajax_check_license() {
		check_ajax_referer('hgezlpfcr_pro_license_action', 'nonce');

		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(['message' => __('Permission denied', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro')]);
		}

		// Clear cache
		delete_transient('hgezlpfcr_pro_license_check');

		$result = self::verify_license(true);

		if (is_wp_error($result)) {
			wp_send_json_error(['message' => $result->get_error_message()]);
		}

		wp_send_json_success(['message' => __('License is valid and active', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro')]);
	}

	/**
	 * Activate License
	 */
	public static function activate_license($license_key) {
		$response = wp_remote_post(self::API_BASE_URL . 'activate', [
			'timeout' => 15,
			'body'    => wp_json_encode([
				'license_key' => $license_key,
				'product_id'  => self::PRODUCT_ID,
				'site_url'    => site_url(),
				'site_name'   => get_bloginfo('name'),
				'ip_address'  => self::get_server_ip(),
			]),
			'headers' => [
				'Content-Type' => 'application/json',
			],
		]);

		if (is_wp_error($response)) {
			HGEZLPFCR_Logger::error('License activation failed', ['error' => $response->get_error_message()]);
			return new WP_Error('api_error', __('Could not connect to license server', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'));
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = json_decode(wp_remote_retrieve_body($response), true);

		if ($code !== 200 || !isset($body['success']) || !$body['success']) {
			$error_message = $body['message'] ?? __('Unknown error', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');
			HGEZLPFCR_Logger::error('License activation rejected', ['error' => $error_message]);
			return new WP_Error('activation_failed', $error_message);
		}

		// Save license data
		update_option('hgezlpfcr_pro_license_key', $license_key);
		update_option('hgezlpfcr_pro_license_status', 'active');
		update_option('hgezlpfcr_pro_license_data', $body['license_data'] ?? []);
		delete_transient('hgezlpfcr_pro_license_check');

		HGEZLPFCR_Logger::log('License activated successfully', ['license_key' => substr($license_key, 0, 8) . '...']);

		return true;
	}

	/**
	 * Deactivate License
	 */
	public static function deactivate_license() {
		$license_key = get_option('hgezlpfcr_pro_license_key');

		if (empty($license_key)) {
			return new WP_Error('no_license', __('No license to deactivate', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'));
		}

		$response = wp_remote_post(self::API_BASE_URL . 'deactivate', [
			'timeout' => 15,
			'body'    => wp_json_encode([
				'license_key' => $license_key,
				'product_id'  => self::PRODUCT_ID,
				'site_url'    => site_url(),
			]),
			'headers' => [
				'Content-Type' => 'application/json',
			],
		]);

		// Even if API call fails, deactivate locally
		delete_option('hgezlpfcr_pro_license_key');
		update_option('hgezlpfcr_pro_license_status', 'inactive');
		delete_option('hgezlpfcr_pro_license_data');
		delete_transient('hgezlpfcr_pro_license_check');

		HGEZLPFCR_Logger::log('License deactivated');

		return true;
	}

	/**
	 * Verify License Status
	 *
	 * @param bool $force_check Force fresh check (bypass cache)
	 * @return bool|WP_Error True if valid, WP_Error if invalid
	 */
	public static function verify_license($force_check = false) {
		$license_key = get_option('hgezlpfcr_pro_license_key');

		if (empty($license_key)) {
			return new WP_Error('no_license', __('No license key found', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'));
		}

		// Check cache (unless forced)
		if (!$force_check) {
			$cached = get_transient('hgezlpfcr_pro_license_check');
			if ($cached === 'active') {
				return true;
			} elseif ($cached === 'invalid') {
				return new WP_Error('invalid_license', __('License is invalid', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'));
			}
		}

		// Verify with API (uses /check endpoint)
		$response = wp_remote_post(self::API_BASE_URL . 'check', [
			'timeout' => 15,
			'body'    => wp_json_encode([
				'license_key' => $license_key,
				'product_id'  => self::PRODUCT_ID,
				'site_url'    => site_url(),
			]),
			'headers' => [
				'Content-Type' => 'application/json',
			],
		]);

		if (is_wp_error($response)) {
			// On connection error, assume valid if previously active (grace period)
			if (get_option('hgezlpfcr_pro_license_status') === 'active') {
				return true;
			}
			return new WP_Error('api_error', __('Could not verify license', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'));
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = json_decode(wp_remote_retrieve_body($response), true);

		$is_valid = $code === 200 && isset($body['success']) && $body['success'] && isset($body['license_data']['status']) && $body['license_data']['status'] === 'active';

		if ($is_valid) {
			// Update license data
			update_option('hgezlpfcr_pro_license_status', 'active');
			update_option('hgezlpfcr_pro_license_data', $body['license_data']);
			set_transient('hgezlpfcr_pro_license_check', 'active', self::CACHE_DURATION);
			return true;
		} else {
			// License invalid or expired
			update_option('hgezlpfcr_pro_license_status', 'inactive');
			set_transient('hgezlpfcr_pro_license_check', 'invalid', self::CACHE_DURATION);

			$error_message = $body['message'] ?? __('License is invalid or expired', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro');
			return new WP_Error('invalid_license', $error_message);
		}
	}

	/**
	 * Check if has active license
	 *
	 * @return bool
	 */
	public static function has_active_license() {
		$result = self::verify_license();
		return !is_wp_error($result);
	}

	/**
	 * Maybe check license (daily cron)
	 */
	public static function maybe_check_license() {
		// Check once per day
		$last_check = get_option('hgezlpfcr_pro_license_last_check', 0);

		if (time() - $last_check > DAY_IN_SECONDS) {
			self::verify_license(true);
			update_option('hgezlpfcr_pro_license_last_check', time());
		}
	}

	/**
	 * Admin notices for license status
	 */
	public static function license_admin_notices() {
		$screen = get_current_screen();

		// Only show on WooCommerce pages
		if (!$screen || strpos($screen->id, 'woocommerce') === false) {
			return;
		}

		$license_status = get_option('hgezlpfcr_pro_license_status', 'inactive');

		if ($license_status !== 'active') {
			?>
			<div class="notice notice-warning is-dismissible">
				<p>
					<strong><?php esc_html_e('HgE PRO License:', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?></strong>
					<?php esc_html_e('Your license is inactive. PRO features are limited.', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>
					<a href="<?php echo esc_url(admin_url('admin.php?page=hgezlpfcr-pro-license')); ?>" class="button button-primary" style="margin-left: 10px;">
						<?php esc_html_e('Activate License', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>
					</a>
				</p>
			</div>
			<?php
			return;
		}

		// Check expiry warning (30 days)
		$license_data = get_option('hgezlpfcr_pro_license_data', []);
		$expires_at = $license_data['expires_at'] ?? null;

		if ($expires_at) {
			$days_remaining = floor((strtotime($expires_at) - time()) / DAY_IN_SECONDS);

			if ($days_remaining < 30 && $days_remaining > 0) {
				?>
				<div class="notice notice-warning is-dismissible">
					<p>
						<strong><?php esc_html_e('HgE PRO License:', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?></strong>
						<?php
						printf(
							/* translators: %d: days remaining */
							esc_html__('Your license expires in %d days. Renew to keep PRO features.', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'),
							esc_html($days_remaining)
						);
						?>
						<a href="https://web-production-c8792.up.railway.app/checkout.html" target="_blank" class="button button-primary" style="margin-left: 10px;">
							<?php esc_html_e('Renew License', 'hge-zone-de-livrare-pentru-fan-courier-romania-pro'); ?>
						</a>
					</p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Get server IP address
	 *
	 * @return string
	 */
	private static function get_server_ip() {
		if (!empty($_SERVER['SERVER_ADDR'])) {
			return sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR']));
		}
		if (!empty($_SERVER['LOCAL_ADDR'])) {
			return sanitize_text_field(wp_unslash($_SERVER['LOCAL_ADDR']));
		}
		return '';
	}
}

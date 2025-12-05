/**
 * FANBox Map Integration
 * Integrates with FAN Courier's external map library for FANBox selection
 *
 * @package HgE_Pro_FAN_Courier
 * @since 2.0.0
 */

(function($) {
	'use strict';

	/**
	 * FANBox Map Handler
	 */
	var HgezlpfcrFanboxMap = {

		/**
		 * Configuration
		 */
		config: {
			methodId: 'fc_pro_fanbox',
			cookieNameFanbox: 'hgezlpfcr_pro_fanbox_name',
			cookieNameAddress: 'hgezlpfcr_pro_fanbox_address',
			cookieExpireDays: 30
		},

		/**
		 * Selected pickup point
		 */
		selectedPickupPoint: null,

		/**
		 * Initialize
		 */
		init: function() {
			var self = this;

			console.log('[FANBox] Initializing FANBox integration');

			// Bind events
			this.bindEvents();

			// Check selected shipping method on load
			this.checkShippingMethod();

			// Also check after short delays to catch dynamic loading
			setTimeout(function() { self.checkShippingMethod(); }, 1000);
			setTimeout(function() { self.checkShippingMethod(); }, 3000);

			// Monitor for external library loading
			this.monitorLibraryLoading();
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			var self = this;

			// Listen for shipping method changes
			$(document).on('change', 'input[name^="shipping_method"]', function() {
				self.checkShippingMethod();
			});

			// Update on checkout update
			$(document.body).on('updated_checkout', function() {
				console.log('[FANBox] Checkout updated');
				self.checkShippingMethod();
			});

			// Cart updates
			$(document.body).on('updated_cart_totals updated_shipping_method', function() {
				console.log('[FANBox] Cart/shipping updated');
				self.checkShippingMethod();
			});

			// Map button click - use event delegation for dynamic elements
			$(document).on('click', '#hgezlpfcr-pro-fanbox-map-btn', function(e) {
				e.preventDefault();
				console.log('[FANBox] Map button clicked');
				self.openMap();
			});

			// Listen for map selection events from external library
			window.addEventListener('map:select-point', function(event) {
				self.onFanboxSelected(event.detail.item);
			});

			// Validation on checkout submit
			$(document).on('checkout_place_order', function() {
				return self.validateFanboxSelection();
			});
		},

		/**
		 * Check if FANBox shipping method is selected
		 */
		checkShippingMethod: function() {
			var selectedMethod = this.getSelectedShippingMethod();
			var isFanboxMethod = selectedMethod && selectedMethod.indexOf(this.config.methodId) !== -1;

			console.log('[FANBox] Shipping method check:', {
				selected: selectedMethod,
				isFanbox: isFanboxMethod
			});

			if (isFanboxMethod) {
				this.showFanboxSelector();
				this.updateShippingDestination();
				this.hideShippingAddressFields();
			} else {
				this.hideFanboxSelector();
				this.showShippingAddressFields();
			}
		},

		/**
		 * Get selected shipping method
		 */
		getSelectedShippingMethod: function() {
			var $selected = $('input[name^="shipping_method"]:checked');
			return $selected.length ? $selected.val() : null;
		},

		/**
		 * Show FANBox selector
		 */
		showFanboxSelector: function() {
			var self = this;

			// Remove existing selector
			$('.hgezlpfcr-pro-fanbox-row, .hgezlpfcr-pro-fanbox-li').remove();

			// Find insertion point
			var $insertionPoint = this.findInsertionPoint();

			if (!$insertionPoint || !$insertionPoint.length) {
				console.warn('[FANBox] Could not find insertion point');
				return;
			}

			var $fanboxElement;

			if (this.layoutType === 'list') {
				// Create list item for ul-based layouts
				$fanboxElement = $('<li class="hgezlpfcr-pro-fanbox-li hgezlpfcr-pro-pickup-selector" style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px;">')
					.append($('<button type="button" class="button alt wp-element-button" id="hgezlpfcr-pro-fanbox-map-btn" style="margin-right: 10px;">')
						.text(hgezlpfcrProFanbox.i18n.mapButtonText)
					)
					.append($('<strong>')
						.append($('<span id="hgezlpfcr-pro-fanbox-details" style="color: #155724;">'))
					);
			} else {
				// Create table row for table-based layouts
				$fanboxElement = $('<tr class="hgezlpfcr-pro-fanbox-row hgezlpfcr-pro-pickup-selector">')
					.append($('<th>')
						.append($('<button type="button" class="button alt wp-element-button" id="hgezlpfcr-pro-fanbox-map-btn">')
							.text(hgezlpfcrProFanbox.i18n.mapButtonText)
						)
					)
					.append($('<td>')
						.append($('<strong>')
							.append($('<span id="hgezlpfcr-pro-fanbox-details">'))
						)
					);
			}

			$insertionPoint.after($fanboxElement);

			console.log('[FANBox] Selector added, layout type:', this.layoutType);

			// Click handler is bound via event delegation in bindEvents()

			// Show saved selection
			this.displaySavedSelection();

			// Update shipping destination
			this.updateShippingDestination();
		},

		/**
		 * Hide FANBox selector
		 */
		hideFanboxSelector: function() {
			$('.hgezlpfcr-pro-fanbox-row, .hgezlpfcr-pro-fanbox-li').remove();
			this.clearCookies();
		},

		/**
		 * Hide shipping address fields and show FANBox info when FANBox is selected
		 */
		hideShippingAddressFields: function() {
			var self = this;

			// Wait a bit for the page to stabilize
			setTimeout(function() {
				// Get saved FANBox data from cookies
				var fanboxName = self.getCookie(self.config.cookieNameFanbox);
				var fanboxFullAddress = self.getCookie('hgezlpfcr_pro_fanbox_full_address');
				var fanboxDescription = self.getCookie('hgezlpfcr_pro_fanbox_description');
				var fanboxSchedule = self.getCookie('hgezlpfcr_pro_fanbox_schedule');

				// Decode values
				fanboxName = fanboxName ? decodeURIComponent(fanboxName) : '';
				fanboxFullAddress = fanboxFullAddress ? decodeURIComponent(fanboxFullAddress) : '';
				fanboxDescription = fanboxDescription ? decodeURIComponent(fanboxDescription) : '';
				fanboxSchedule = fanboxSchedule ? decodeURIComponent(fanboxSchedule) : '';

				console.log('[FANBox] hideShippingAddressFields with cookies:', {
					name: fanboxName,
					address: fanboxFullAddress
				});

				// Remove any existing container
				$('#hgezlpfcr-fanbox-shipping-info').remove();

				// Create the info container
				self.createFanboxInfoContainer(fanboxName, fanboxFullAddress, fanboxDescription, fanboxSchedule);

				console.log('[FANBox] Shipping section updated with FANBox info');
			}, 150);
		},

		/**
		 * Update the FANBox shipping info display
		 */
		updateShippingWithFanboxInfo: function(name, fullAddress, description, schedule) {
			var $infoContainer = $('#hgezlpfcr-fanbox-shipping-info');

			if ($infoContainer.length) {
				var infoHtml = '<h4 style="margin: 0 0 15px 0; color: #155724;">📦 Livrare la FANBox</h4>';
				infoHtml += '<p style="margin: 0 0 10px 0;"><strong style="font-size: 16px;">' + name + '</strong></p>';
				if (fullAddress) {
					infoHtml += '<p style="margin: 0 0 5px 0; color: #666;"><strong>Adresă:</strong> ' + fullAddress + '</p>';
				}
				if (description) {
					infoHtml += '<p style="margin: 0 0 5px 0; color: #666; font-style: italic;">' + description + '</p>';
				}
				if (schedule) {
					infoHtml += '<p style="margin: 0 0 15px 0; color: #155724;"><strong>Program:</strong> ' + schedule + '</p>';
				}
				infoHtml += '<button type="button" class="button" id="hgezlpfcr-change-fanbox-btn" style="margin-top: 10px;">🔄 Alege alt FANBox</button>';

				$infoContainer.html(infoHtml);

				// Rebind button event
				var self = this;
				$('#hgezlpfcr-change-fanbox-btn').on('click', function(e) {
					e.preventDefault();
					self.openMap();
				});
			}
		},

		/**
		 * Show shipping address fields when non-FANBox method is selected
		 */
		showShippingAddressFields: function() {
			// Remove FANBox info container
			$('#hgezlpfcr-fanbox-shipping-info').remove();

			// Show shipping fields
			$('.woocommerce-shipping-fields__field-wrapper').removeClass('hgezlpfcr-hidden-for-fanbox').show();

			console.log('[FANBox] Shipping address fields restored');
		},

		/**
		 * Find insertion point for selector
		 */
		findInsertionPoint: function() {
			// Try multiple selectors for different themes and layouts
			// First try table-based layouts
			var tableSelectors = [
				'.woocommerce-shipping-totals tr:last-child',
				'.shop_table tfoot tr:last-child',
				'#shipping_method tr:last-child',
				'.cart-collaterals .shipping tr:last-child',
				'#order_review .shop_table tbody tr:last-child',
				'.cart_totals table tbody tr:last-child'
			];

			for (var i = 0; i < tableSelectors.length; i++) {
				var $point = $(tableSelectors[i]);
				if ($point.length) {
					this.layoutType = 'table';
					return $point;
				}
			}

			// Try list-based layouts (ul#shipping_method)
			var listSelectors = [
				'#shipping_method li:last-child',
				'ul.woocommerce-shipping-methods li:last-child',
				'.shipping-methods li:last-child'
			];

			for (var j = 0; j < listSelectors.length; j++) {
				var $listPoint = $(listSelectors[j]);
				if ($listPoint.length) {
					this.layoutType = 'list';
					return $listPoint;
				}
			}

			return null;
		},

		/**
		 * Display saved FANBox selection
		 */
		displaySavedSelection: function() {
			var fanboxName = this.getCookie(this.config.cookieNameFanbox);

			if (fanboxName) {
				$('#hgezlpfcr-pro-fanbox-details').html(decodeURIComponent(fanboxName));
			} else {
				$('#hgezlpfcr-pro-fanbox-details').html('<span style="color: #e74c3c; font-style: italic;">' + hgezlpfcrProFanbox.i18n.noSelection + '</span>');
			}
		},

		/**
		 * Open FANBox map
		 */
		openMap: function() {
			var self = this;

			console.log('[FANBox] Opening map');

			// Check if external library is loaded
			if (typeof window.LoadMapFanBox === 'undefined') {
				console.error('[FANBox] Map library not loaded');
				alert(hgezlpfcrProFanbox.i18n.mapLoadError);
				return;
			}

			// Get map container
			var rootNode = document.getElementById('FANmapDiv');
			if (!rootNode) {
				console.error('[FANBox] FANmapDiv container not found');
				alert(hgezlpfcrProFanbox.i18n.mapLoadError);
				return;
			}

			// Get shipping address for filtering
			var county = this.getShippingCounty();
			var locality = this.getShippingLocality();

			console.log('[FANBox] Opening map with params:', {
				county: county,
				locality: locality,
				selectedPoint: this.selectedPickupPoint
			});

			// Open map with FAN Courier's library
			window.LoadMapFanBox({
				pickUpPoint: this.selectedPickupPoint,
				county: county,
				locality: locality,
				rootNode: rootNode
			});
		},

		/**
		 * Romanian county codes to names mapping (without diacritics)
		 */
		countyMap: {
			'AB': 'Alba', 'AR': 'Arad', 'AG': 'Arges', 'BC': 'Bacau', 'BH': 'Bihor',
			'BN': 'Bistrita-Nasaud', 'BT': 'Botosani', 'BV': 'Brasov', 'BR': 'Braila',
			'B': 'Bucuresti', 'BZ': 'Buzau', 'CS': 'Caras-Severin', 'CL': 'Calarasi',
			'CJ': 'Cluj', 'CT': 'Constanta', 'CV': 'Covasna', 'DB': 'Dambovita',
			'DJ': 'Dolj', 'GL': 'Galati', 'GR': 'Giurgiu', 'GJ': 'Gorj', 'HR': 'Harghita',
			'HD': 'Hunedoara', 'IL': 'Ialomita', 'IS': 'Iasi', 'IF': 'Ilfov',
			'MM': 'Maramures', 'MH': 'Mehedinti', 'MS': 'Mures', 'NT': 'Neamt',
			'OT': 'Olt', 'PH': 'Prahova', 'SM': 'Satu Mare', 'SJ': 'Salaj',
			'SB': 'Sibiu', 'SV': 'Suceava', 'TR': 'Teleorman', 'TM': 'Timis',
			'TL': 'Tulcea', 'VS': 'Vaslui', 'VL': 'Valcea', 'VN': 'Vrancea'
		},

		/**
		 * Remove diacritics from string
		 */
		removeDiacritics: function(str) {
			return String(str).normalize("NFD").replace(/[\u0300-\u036f]/g, "");
		},

		/**
		 * Get shipping county (without diacritics)
		 */
		getShippingCounty: function() {
			var self = this;
			var countyCode = '';
			var countyText = '';

			// Check if "Ship to different address" is checked
			var useShipping = $('#ship-to-different-address-checkbox').is(':checked');

			if (useShipping) {
				// Try shipping fields first
				countyCode = $('#shipping_state').val() || '';
				countyText = $('select#shipping_state option:selected').text() || $('#shipping_state').val() || '';
			}

			// Fallback to billing
			if (!countyCode) {
				countyCode = $('#billing_state').val() || '';
			}
			if (!countyText) {
				countyText = $('select#billing_state option:selected').text() || $('#billing_state').val() || '';
			}

			// If we have a code, map it to full name
			if (countyCode && this.countyMap[countyCode.toUpperCase()]) {
				console.log('[FANBox] County code mapped:', countyCode, '->', this.countyMap[countyCode.toUpperCase()]);
				return this.countyMap[countyCode.toUpperCase()];
			}

			// Remove diacritics from the text
			var result = this.removeDiacritics(countyText);
			console.log('[FANBox] County text:', countyText, '->', result);
			return result;
		},

		/**
		 * Get shipping locality (without diacritics)
		 */
		getShippingLocality: function() {
			var locality = '';

			// Check if "Ship to different address" is checked
			var useShipping = $('#ship-to-different-address-checkbox').is(':checked');

			if (useShipping) {
				// Try shipping fields first - multiple selectors for different themes
				locality = $('#shipping_city').val() ||
					$('input[name="shipping_city"]').val() ||
					$('select#shipping_city option:selected').text() ||
					$('[id*="shipping"][id*="city"]').val() ||
					'';
			}

			// Fallback to billing
			if (!locality) {
				locality = $('#billing_city').val() ||
					$('input[name="billing_city"]').val() ||
					$('select#billing_city option:selected').text() ||
					$('[id*="billing"][id*="city"]').val() ||
					'';
			}

			// Remove diacritics
			var result = this.removeDiacritics(locality);
			console.log('[FANBox] Locality:', locality, '->', result);
			return result;
		},

		/**
		 * Handle FANBox selection from map
		 */
		onFanboxSelected: function(pickupPoint) {
			var self = this;

			// Log full object for debugging
			try {
				console.log('[FANBox] FANBox selected (raw):', pickupPoint);
				console.log('[FANBox] FANBox address property:', pickupPoint ? pickupPoint.address : 'pickupPoint is null');
			} catch(e) {
				console.error('[FANBox] Error logging pickupPoint:', e);
			}

			if (!pickupPoint) {
				console.error('[FANBox] No pickup point received');
				return;
			}

			this.selectedPickupPoint = pickupPoint;

			// Safely extract data from FANBox library response
			var name = '';
			var description = '';
			var schedule = '';
			var fullAddress = '';

			// Extract name
			if (pickupPoint.name && typeof pickupPoint.name === 'string' && pickupPoint.name !== 'undefined') {
				name = pickupPoint.name;
			}

			// Extract description
			if (pickupPoint.description && typeof pickupPoint.description === 'string' && pickupPoint.description !== 'undefined') {
				description = pickupPoint.description;
			}

			// Extract schedule
			if (pickupPoint.schedule && typeof pickupPoint.schedule === 'string' && pickupPoint.schedule !== 'undefined') {
				schedule = pickupPoint.schedule;
			}

			// Extract address - this is the critical one
			if (pickupPoint.address && typeof pickupPoint.address === 'string') {
				fullAddress = pickupPoint.address;
				// Check for "undefined" in the address
				if (fullAddress === 'undefined' || fullAddress.indexOf('undefined') === 0) {
					console.warn('[FANBox] Invalid address detected:', fullAddress);
					fullAddress = '';
				}
			}

			console.log('[FANBox] Extracted values:', {
				name: name,
				fullAddress: fullAddress,
				description: description,
				schedule: schedule
			});

			// Parse county and locality from address field
			// Format: "County, City, Street, Number, PostalCode, Description"
			var county = '';
			var locality = '';

			if (fullAddress && fullAddress.length > 0) {
				var addressParts = fullAddress.split(',');
				if (addressParts.length >= 2) {
					county = addressParts[0].trim();
					locality = addressParts[1].trim();

					// Validate extracted values
					if (county === 'undefined') county = '';
					if (locality === 'undefined') locality = '';
				}
			}

			console.log('[FANBox] Parsed address:', {
				county: county,
				locality: locality
			});

			// Save to cookies - only if we have values
			if (name) {
				this.setCookie(this.config.cookieNameFanbox, encodeURIComponent(name), this.config.cookieExpireDays);
			}

			// Always set address cookie (even if empty parts, to overwrite old data)
			this.setCookie(this.config.cookieNameAddress, encodeURIComponent(county + '|' + locality), this.config.cookieExpireDays);

			if (fullAddress) {
				this.setCookie('hgezlpfcr_pro_fanbox_full_address', encodeURIComponent(fullAddress), this.config.cookieExpireDays);
			}

			if (description) {
				this.setCookie('hgezlpfcr_pro_fanbox_description', encodeURIComponent(description), this.config.cookieExpireDays);
			}

			if (schedule) {
				this.setCookie('hgezlpfcr_pro_fanbox_schedule', encodeURIComponent(schedule), this.config.cookieExpireDays);
			}

			// Verify cookies were set
			console.log('[FANBox] Cookies verification:', {
				name: this.getCookie(this.config.cookieNameFanbox),
				address: this.getCookie(this.config.cookieNameAddress),
				fullAddress: this.getCookie('hgezlpfcr_pro_fanbox_full_address'),
				description: this.getCookie('hgezlpfcr_pro_fanbox_description'),
				schedule: this.getCookie('hgezlpfcr_pro_fanbox_schedule')
			});

			// Update display in shipping method list
			$('#hgezlpfcr-pro-fanbox-details').html(name || 'FANBox selectat');

			// Remove old container and create new one with fresh data
			$('#hgezlpfcr-fanbox-shipping-info').remove();

			// Create/update the FANBox info container
			this.createFanboxInfoContainer(name, fullAddress, description, schedule);

			// Update shipping destination text
			this.updateShippingDestination();

			// Trigger checkout update with a delay to avoid race conditions
			setTimeout(function() {
				$('body').trigger('update_checkout');
			}, 500);
		},

		/**
		 * Create FANBox info container in checkout
		 */
		createFanboxInfoContainer: function(name, fullAddress, description, schedule) {
			var self = this;

			// Check "Ship to different address" checkbox
			var $checkbox = $('#ship-to-different-address-checkbox');
			if ($checkbox.length && !$checkbox.is(':checked')) {
				$checkbox.prop('checked', true).trigger('change');
			}

			// Build info HTML
			var infoHtml = '<div id="hgezlpfcr-fanbox-shipping-info" style="background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 6px; margin-top: 15px;">';
			infoHtml += '<h4 style="margin: 0 0 15px 0; color: #155724;">📦 Livrare la FANBox</h4>';

			if (name) {
				infoHtml += '<p style="margin: 0 0 10px 0;"><strong style="font-size: 16px;">' + name + '</strong></p>';
				if (fullAddress) {
					infoHtml += '<p style="margin: 0 0 5px 0; color: #666;"><strong>Adresă:</strong> ' + fullAddress + '</p>';
				}
				if (description) {
					infoHtml += '<p style="margin: 0 0 5px 0; color: #666; font-style: italic;">' + description + '</p>';
				}
				if (schedule) {
					infoHtml += '<p style="margin: 0 0 15px 0; color: #155724;"><strong>Program:</strong> ' + schedule + '</p>';
				}
				infoHtml += '<button type="button" class="button" id="hgezlpfcr-change-fanbox-btn" style="margin-top: 10px;">🔄 Alege alt FANBox</button>';
			} else {
				infoHtml += '<p style="margin: 0 0 15px 0; color: #856404;">' + hgezlpfcrProFanbox.i18n.noSelection + '</p>';
				infoHtml += '<button type="button" class="button alt" id="hgezlpfcr-select-fanbox-btn">' + hgezlpfcrProFanbox.i18n.mapButtonText + '</button>';
			}

			infoHtml += '</div>';

			// Hide original shipping address fields
			var $shippingWrapper = $('.woocommerce-shipping-fields__field-wrapper');
			if ($shippingWrapper.length) {
				$shippingWrapper.addClass('hgezlpfcr-hidden-for-fanbox').hide();
				$shippingWrapper.after(infoHtml);
			} else if ($('.woocommerce-shipping-fields').length) {
				$('.woocommerce-shipping-fields').append(infoHtml);
			} else if ($('#ship-to-different-address').length) {
				$('#ship-to-different-address').after(infoHtml);
			} else {
				// Fallback: append to checkout form
				$('.woocommerce-checkout').append(infoHtml);
			}

			// Bind button click handlers using event delegation
			$(document).off('click.fanbox', '#hgezlpfcr-change-fanbox-btn, #hgezlpfcr-select-fanbox-btn');
			$(document).on('click.fanbox', '#hgezlpfcr-change-fanbox-btn, #hgezlpfcr-select-fanbox-btn', function(e) {
				e.preventDefault();
				self.openMap();
			});

			console.log('[FANBox] Info container created with data:', { name: name, fullAddress: fullAddress });
		},

		/**
		 * Update shipping destination text
		 */
		updateShippingDestination: function() {
			var selectedMethod = this.getSelectedShippingMethod();
			if (!selectedMethod || selectedMethod.indexOf(this.config.methodId) === -1) {
				return;
			}

			var $destination = $('.woocommerce-shipping-destination');
			if (!$destination.length) {
				return;
			}

			var fanboxName = this.getCookie(this.config.cookieNameFanbox);
			var fanboxAddress = this.getCookie(this.config.cookieNameAddress);

			if (fanboxName) {
				// FANBox selected - show FANBox info
				var displayText = hgezlpfcrProFanbox.i18n.deliveryTo + ' <strong>FANBox ' + decodeURIComponent(fanboxName) + '</strong>';

				if (fanboxAddress) {
					var addressParts = decodeURIComponent(fanboxAddress).split('|');
					if (addressParts.length === 2) {
						displayText += '<br><small style="color: #666;">' + addressParts[1] + ', ' + addressParts[0] + '</small>';
					}
				}

				$destination.html(displayText);
			} else {
				// No FANBox selected - show prompt
				var popupHtml = '<a href="#" id="hgezlpfcr-pro-fanbox-popup-link" class="fanbox-popup-trigger">' +
					hgezlpfcrProFanbox.i18n.chooseFromMap + '</a>';
				$destination.html(popupHtml);

				// Bind click event
				$('#hgezlpfcr-pro-fanbox-popup-link').on('click', function(e) {
					e.preventDefault();
					var $mapBtn = $('#hgezlpfcr-pro-fanbox-map-btn');
					if ($mapBtn.length) {
						$mapBtn.trigger('click');
					}
				});
			}
		},

		/**
		 * Validate FANBox selection before order placement
		 */
		validateFanboxSelection: function() {
			var selectedMethod = this.getSelectedShippingMethod();

			if (selectedMethod && selectedMethod.indexOf(this.config.methodId) !== -1) {
				var fanboxName = this.getCookie(this.config.cookieNameFanbox);

				if (!fanboxName) {
					// Show error notice
					$('.woocommerce-NoticeGroup-checkout, .woocommerce-notices-wrapper').first().html(
						'<div class="woocommerce-error" role="alert">' +
						hgezlpfcrProFanbox.i18n.validationError +
						'</div>'
					);

					// Scroll to error
					$('html, body').animate({
						scrollTop: $('.woocommerce-error').offset().top - 100
					}, 500);

					return false;
				}
			}

			return true;
		},

		/**
		 * Set cookie
		 */
		setCookie: function(key, value, days) {
			var d = new Date();
			d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
			var expires = "expires=" + d.toUTCString();
			document.cookie = key + "=" + value + ";" + expires + ";path=/";
		},

		/**
		 * Get cookie
		 */
		getCookie: function(key) {
			var cookie = '';
			var cookies = document.cookie.split(';');
			for (var i = 0; i < cookies.length; i++) {
				var c = cookies[i].trim();
				var eqPos = c.indexOf('=');
				if (eqPos > -1) {
					var cookieName = c.substring(0, eqPos);
					if (cookieName === key) {
						cookie = c.substring(eqPos + 1);
						break;
					}
				}
			}
			return cookie || '';
		},

		/**
		 * Clear cookies
		 */
		clearCookies: function() {
			this.setCookie(this.config.cookieNameFanbox, '', -1);
			this.setCookie(this.config.cookieNameAddress, '', -1);
			this.setCookie('hgezlpfcr_pro_fanbox_full_address', '', -1);
			this.setCookie('hgezlpfcr_pro_fanbox_description', '', -1);
			this.setCookie('hgezlpfcr_pro_fanbox_schedule', '', -1);
		},

		/**
		 * Monitor for external library loading
		 */
		monitorLibraryLoading: function() {
			var attempts = 0;
			var maxAttempts = 20; // 10 seconds max

			var checkLibrary = function() {
				attempts++;

				if (typeof window.LoadMapFanBox !== 'undefined') {
					console.log('[FANBox] Map library loaded successfully after', attempts, 'attempts');
					return;
				}

				if (attempts < maxAttempts) {
					setTimeout(checkLibrary, 500);
				} else {
					console.error('[FANBox] Map library failed to load after', attempts, 'attempts');
				}
			};

			setTimeout(checkLibrary, 500);
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		// Check if config is available
		if (typeof hgezlpfcrProFanbox !== 'undefined') {
			HgezlpfcrFanboxMap.init();
		} else {
			console.error('[FANBox] Configuration not found');
		}
	});

	// Expose globally for debugging
	window.HgezlpfcrFanboxMap = HgezlpfcrFanboxMap;

})(jQuery);

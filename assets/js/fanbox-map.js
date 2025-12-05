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

			// Check "Ship to different address" checkbox
			var $checkbox = $('#ship-to-different-address-checkbox');
			if (!$checkbox.is(':checked')) {
				$checkbox.prop('checked', true).trigger('change');
			}

			// Wait for shipping fields to be visible
			setTimeout(function() {
				// Hide original shipping address fields
				$('.woocommerce-shipping-fields__field-wrapper').addClass('hgezlpfcr-hidden-for-fanbox').hide();

				// Create FANBox info container if not exists
				if (!$('#hgezlpfcr-fanbox-shipping-info').length) {
					var fanboxName = decodeURIComponent(self.getCookie(self.config.cookieNameFanbox) || '');
					var fanboxFullAddress = decodeURIComponent(self.getCookie('hgezlpfcr_pro_fanbox_full_address') || '');
					var fanboxDescription = decodeURIComponent(self.getCookie('hgezlpfcr_pro_fanbox_description') || '');

					var infoHtml = '<div id="hgezlpfcr-fanbox-shipping-info" style="background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 6px; margin-top: 15px;">';
					infoHtml += '<h4 style="margin: 0 0 15px 0; color: #155724;">📦 Livrare la FANBox</h4>';

					if (fanboxName) {
						infoHtml += '<p style="margin: 0 0 10px 0;"><strong style="font-size: 16px;">' + fanboxName + '</strong></p>';
						if (fanboxFullAddress) {
							infoHtml += '<p style="margin: 0 0 5px 0; color: #666;"><strong>Adresă:</strong> ' + fanboxFullAddress + '</p>';
						}
						if (fanboxDescription) {
							infoHtml += '<p style="margin: 0 0 15px 0; color: #666; font-style: italic;">' + fanboxDescription + '</p>';
						}
						infoHtml += '<button type="button" class="button" id="hgezlpfcr-change-fanbox-btn" style="margin-top: 10px;">🔄 Alege alt FANBox</button>';
					} else {
						infoHtml += '<p style="margin: 0 0 15px 0; color: #856404;">' + hgezlpfcrProFanbox.i18n.noSelection + '</p>';
						infoHtml += '<button type="button" class="button alt" id="hgezlpfcr-select-fanbox-btn">' + hgezlpfcrProFanbox.i18n.mapButtonText + '</button>';
					}

					infoHtml += '</div>';

					$('.woocommerce-shipping-fields__field-wrapper').after(infoHtml);

					// Bind button events
					$('#hgezlpfcr-change-fanbox-btn, #hgezlpfcr-select-fanbox-btn').on('click', function(e) {
						e.preventDefault();
						self.openMap();
					});
				}

				console.log('[FANBox] Shipping section updated with FANBox info');
			}, 100);
		},

		/**
		 * Update the FANBox shipping info display
		 */
		updateShippingWithFanboxInfo: function(name, fullAddress, description) {
			var $infoContainer = $('#hgezlpfcr-fanbox-shipping-info');

			if ($infoContainer.length) {
				var infoHtml = '<h4 style="margin: 0 0 15px 0; color: #155724;">📦 Livrare la FANBox</h4>';
				infoHtml += '<p style="margin: 0 0 10px 0;"><strong style="font-size: 16px;">' + name + '</strong></p>';
				if (fullAddress) {
					infoHtml += '<p style="margin: 0 0 5px 0; color: #666;"><strong>Adresă:</strong> ' + fullAddress + '</p>';
				}
				if (description) {
					infoHtml += '<p style="margin: 0 0 15px 0; color: #666; font-style: italic;">' + description + '</p>';
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

			// Try to get county code first
			var countyCode = $('#shipping_state').val() || $('#billing_state').val() || '';

			// If we have a code, map it to full name
			if (countyCode && this.countyMap[countyCode.toUpperCase()]) {
				return this.countyMap[countyCode.toUpperCase()];
			}

			// Try to get county name from select text
			var countyText = $('select[name="shipping_state"] option:selected').text() ||
				$('select[name="billing_state"] option:selected').text() ||
				$('#shipping_state').val() ||
				$('#billing_state').val() ||
				'';

			// Remove diacritics from the text
			return this.removeDiacritics(countyText);
		},

		/**
		 * Get shipping locality (without diacritics)
		 */
		getShippingLocality: function() {
			var locality = $('#shipping_city').val() ||
				$('#billing_city').val() ||
				$('input[name="shipping_city"]').val() ||
				'';

			// Remove diacritics
			return this.removeDiacritics(locality);
		},

		/**
		 * Handle FANBox selection from map
		 */
		onFanboxSelected: function(pickupPoint) {
			console.log('[FANBox] FANBox selected (full object):', pickupPoint);

			this.selectedPickupPoint = pickupPoint;

			// Extract data - try multiple property names as library may vary
			var name = pickupPoint.name || pickupPoint.Name || '';
			var county = pickupPoint.countyName || pickupPoint.county || pickupPoint.CountyName || pickupPoint.County || '';
			var locality = pickupPoint.localityName || pickupPoint.locality || pickupPoint.LocalityName || pickupPoint.Locality || pickupPoint.city || pickupPoint.City || '';
			var address = pickupPoint.address || pickupPoint.Address || pickupPoint.streetName || pickupPoint.StreetName || '';
			var streetNo = pickupPoint.streetNo || pickupPoint.StreetNo || pickupPoint.streetNumber || '';
			var postalCode = pickupPoint.postalCode || pickupPoint.PostalCode || pickupPoint.zipCode || '';
			var description = pickupPoint.description || pickupPoint.Description || pickupPoint.details || '';

			// Build full address string
			var fullAddress = '';
			if (address) {
				fullAddress = address;
				if (streetNo) fullAddress += ' ' + streetNo;
			}
			if (postalCode) fullAddress += ', ' + postalCode;
			if (locality) fullAddress += ', ' + locality;
			if (county) fullAddress += ', ' + county;

			console.log('[FANBox] Extracted data:', {
				name: name,
				county: county,
				locality: locality,
				address: address,
				fullAddress: fullAddress,
				description: description
			});

			// Save to cookies - encode to handle special characters
			this.setCookie(this.config.cookieNameFanbox, encodeURIComponent(name), this.config.cookieExpireDays);
			this.setCookie(this.config.cookieNameAddress, encodeURIComponent(county + '|' + locality), this.config.cookieExpireDays);
			this.setCookie('hgezlpfcr_pro_fanbox_full_address', encodeURIComponent(fullAddress), this.config.cookieExpireDays);
			this.setCookie('hgezlpfcr_pro_fanbox_description', encodeURIComponent(description), this.config.cookieExpireDays);

			// Update display
			$('#hgezlpfcr-pro-fanbox-details').html(name);

			// Update shipping section with FANBox info
			this.updateShippingWithFanboxInfo(name, fullAddress, description);

			// Trigger checkout update to save selection
			$('body').trigger('update_checkout');
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
			document.cookie.split(';').forEach(function(value) {
				if (value.split('=')[0].trim() === key) {
					cookie = value.split('=')[1];
				}
			});
			return cookie || '';
		},

		/**
		 * Clear cookies
		 */
		clearCookies: function() {
			this.setCookie(this.config.cookieNameFanbox, '', -1);
			this.setCookie(this.config.cookieNameAddress, '', -1);
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

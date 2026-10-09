/* WP Booking System – Public JS */
(function ($) {
	'use strict';

	var WPBS = {
		currentStep:   1,
		selectedService: null,
		selectedDate:   null,
		selectedTime:   null,
		currentBookingId: null,
		currentBookingRef: null,
		calendarYear:  new Date().getFullYear(),
		calendarMonth: new Date().getMonth() + 1,
		stripe: null,
		stripeElements: null,
		stripeCardElement: null,

		init: function () {
			if (!$('#wpbs-booking-app').length) {
				WPBS.initDashboard();
				return;
			}
			WPBS.initCalendar();
			WPBS.bindEvents();
			if (typeof Stripe !== 'undefined' && wpbsData.stripeKey) {
				WPBS.initStripe();
			}
		},

		// ── Navigation ──

		goToStep: function (step) {
			$('.wpbs-step-content').removeClass('active');
			$('.wpbs-step-content[data-step="' + step + '"]').addClass('active');
			$('.wpbs-step').removeClass('active completed');
			$('.wpbs-step').each(function () {
				var s = parseInt($(this).data('step'));
				if (s < step) $(this).addClass('completed');
				else if (s === step) $(this).addClass('active');
			});
			WPBS.currentStep = step;
			$('html, body').animate({ scrollTop: $('#wpbs-booking-app').offset().top - 20 }, 300);
		},

		// ── Step 1: Service ──

		bindEvents: function () {
			$(document).on('click', '.wpbs-select-service', function () {
				var card = $(this).closest('.wpbs-service-card');
				WPBS.selectedService = {
					id:       card.data('service-id'),
					name:     card.find('h3').text(),
					duration: card.data('duration'),
					price:    card.data('price')
				};
				$('.wpbs-service-card').removeClass('selected');
				card.addClass('selected');
				WPBS.updateSummary();
				WPBS.goToStep(2);
				WPBS.loadCalendarMonth();
			});

			// Step back buttons
			$(document).on('click', '.wpbs-btn-back', function () {
				WPBS.goToStep(parseInt($(this).data('target-step')));
			});

			// Step 2 → 3
			$('#wpbs-step2-next').on('click', function () {
				if (!WPBS.selectedDate || !WPBS.selectedTime) {
					WPBS.showMessage('error', wpbsData.i18n.select_date + ' ' + wpbsData.i18n.select_time);
					return;
				}
				WPBS.goToStep(3);
			});

			// Step 3 → 4 (create booking)
			$('#wpbs-step3-next').on('click', function () {
				WPBS.createBooking();
			});

			// Payment method toggle
			$(document).on('change', 'input[name="payment_method"]', function () {
				$('#wpbs-stripe-section').toggle($(this).val() === 'stripe');
			});

			// Pay now
			$('#wpbs-pay-btn').on('click', function () {
				WPBS.processPayment();
			});
		},

		// ── Calendar ──

		initCalendar: function () {
			$('#wpbs-prev-month').on('click', function () {
				if (--WPBS.calendarMonth < 1) { WPBS.calendarMonth = 12; WPBS.calendarYear--; }
				WPBS.loadCalendarMonth();
			});
			$('#wpbs-next-month').on('click', function () {
				if (++WPBS.calendarMonth > 12) { WPBS.calendarMonth = 1; WPBS.calendarYear++; }
				WPBS.loadCalendarMonth();
			});
		},

		loadCalendarMonth: function () {
			if (!WPBS.selectedService) return;
			var label = new Date(WPBS.calendarYear, WPBS.calendarMonth - 1, 1)
				.toLocaleString('default', { month: 'long', year: 'numeric' });
			$('#wpbs-month-label').text(label);

			$.ajax({
				url:    wpbsData.restUrl + 'available-dates',
				method: 'GET',
				data: {
					service_id: WPBS.selectedService.id,
					month:      WPBS.calendarMonth,
					year:       WPBS.calendarYear
				},
				beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', wpbsData.nonce); },
				success: function (res) { WPBS.renderCalendar(res.dates); }
			});
		},

		renderCalendar: function (availableDates) {
			var container = $('#wpbs-cal-days').empty();
			var year  = WPBS.calendarYear;
			var month = WPBS.calendarMonth;
			var firstDay = new Date(year, month - 1, 1).getDay();
			var daysInMonth = new Date(year, month, 0).getDate();
			var today = new Date().toISOString().slice(0, 10);

			for (var i = 0; i < firstDay; i++) {
				container.append('<div></div>');
			}

			for (var d = 1; d <= daysInMonth; d++) {
				var date   = year + '-' + String(month).padStart(2, '0') + '-' + String(d).padStart(2, '0');
				var avail  = availableDates.indexOf(date) !== -1;
				var cls    = avail ? 'available' : 'unavailable';
				if (date === today) cls += ' today';
				if (date === WPBS.selectedDate) cls += ' selected';
				var $day = $('<div class="wpbs-cal-day ' + cls + '" data-date="' + date + '">' + d + '</div>');
				if (avail) {
					$day.on('click', function () {
						WPBS.selectedDate = $(this).data('date');
						WPBS.selectedTime = null;
						$('.wpbs-cal-day').removeClass('selected');
						$(this).addClass('selected');
						WPBS.loadTimeSlots();
						WPBS.updateSummary();
					});
				}
				container.append($day);
			}
		},

		loadTimeSlots: function () {
			var $slots = $('#wpbs-time-slots').html('<p>' + wpbsData.i18n.loading + '</p>');
			$.ajax({
				url:    wpbsData.restUrl + 'availability',
				method: 'GET',
				data: {
					service_id: WPBS.selectedService.id,
					date:       WPBS.selectedDate
				},
				beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', wpbsData.nonce); },
				success: function (res) {
					$slots.empty();
					if (!res.slots || !res.slots.length) {
						$slots.html('<p class="wpbs-hint">' + wpbsData.i18n.no_slots + '</p>');
						$('#wpbs-step2-next').prop('disabled', true);
						return;
					}
					res.slots.forEach(function (slot) {
						var $btn = $('<button type="button" class="wpbs-time-slot" data-time="' + slot + '">' + slot + '</button>');
						$btn.on('click', function () {
							WPBS.selectedTime = slot;
							$('.wpbs-time-slot').removeClass('selected');
							$(this).addClass('selected');
							$('#wpbs-step2-next').prop('disabled', false);
							WPBS.updateSummary();
						});
						$slots.append($btn);
					});
				}
			});
		},

		updateSummary: function () {
			if (!WPBS.selectedService) return;
			$('#wpbs-summary-service').text(WPBS.selectedService.name);
			$('#wpbs-summary-date').text(WPBS.selectedDate || '—');
			$('#wpbs-summary-time').text(WPBS.selectedTime || '—');
			$('#wpbs-summary-duration').text(WPBS.selectedService.duration + ' min');
			$('#wpbs-summary-price').text(wpbsData.currency + parseFloat(WPBS.selectedService.price).toFixed(2));
		},

		// ── Stripe ──

		initStripe: function () {
			$.get({
				url:    wpbsData.restUrl + 'payments/stripe/config',
				beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', wpbsData.nonce); }
			}).done(function (res) {
				if (res && res.publishable_key) {
					WPBS.stripe = Stripe(res.publishable_key);
					WPBS.stripeElements = WPBS.stripe.elements();
					WPBS.stripeCardElement = WPBS.stripeElements.create('card', {
						style: {
							base: { fontSize: '16px', color: '#32325d', '::placeholder': { color: '#aab7c4' } }
						}
					});
					WPBS.stripeCardElement.mount('#wpbs-card-element');
					WPBS.stripeCardElement.on('change', function (e) {
						$('#wpbs-card-errors').text(e.error ? e.error.message : '');
					});
				}
			});
		},

		// ── Create Booking ──

		createBooking: function () {
			if (!WPBS.selectedService || !WPBS.selectedDate || !WPBS.selectedTime) {
				WPBS.showMessage('error', wpbsData.i18n.select_service);
				return;
			}

			if (!wpbsData.isLoggedIn) {
				window.location.href = wpbsData.loginUrl;
				return;
			}

			var paymentMethod = $('input[name="payment_method"]:checked').val() || '';
			$('#wpbs-step3-next').prop('disabled', true).text(wpbsData.i18n.loading);

			$.post({
				url:  wpbsData.ajaxUrl,
				data: {
					action:         'wpbs_public_action',
					sub_action:     'create_booking',
					nonce:          wpbsData.publicNonce,
					service_id:     WPBS.selectedService.id,
					booking_date:   WPBS.selectedDate,
					booking_time:   WPBS.selectedTime,
					payment_method: paymentMethod,
					notes:          $('#wpbs-notes').val()
				}
			}).done(function (res) {
				if (res.success) {
					WPBS.currentBookingId  = res.data.booking_id;
					WPBS.currentBookingRef = res.data.booking_ref;
					if (parseFloat(WPBS.selectedService.price) <= 0) {
						WPBS.showConfirmation();
					} else {
						WPBS.goToStep(4);
					}
				} else {
					WPBS.showMessage('error', res.data.message || wpbsData.i18n.booking_error);
				}
			}).fail(function () {
				WPBS.showMessage('error', wpbsData.i18n.booking_error);
			}).always(function () {
				$('#wpbs-step3-next').prop('disabled', false).text('Continue to Payment');
			});
		},

		// ── Process Payment ──

		processPayment: function () {
			var method = $('input[name="payment_method"]:checked').val();
			if (!method) { WPBS.showMessage('error', 'Please select a payment method.'); return; }
			if (!WPBS.currentBookingId) { WPBS.showMessage('error', wpbsData.i18n.booking_error); return; }

			$('#wpbs-payment-processing').show();
			$('#wpbs-pay-btn').prop('disabled', true);
			$('#wpbs-payment-message').hide();

			if ('paypal' === method) {
				WPBS.processPayPal();
			} else if ('stripe' === method) {
				WPBS.processStripe();
			}
		},

		processPayPal: function () {
			$.post({
				url: wpbsData.ajaxUrl,
				data: {
					action:     'wpbs_public_action',
					sub_action:  'paypal_create',
					nonce:       wpbsData.publicNonce,
					booking_id:  WPBS.currentBookingId
				}
			}).done(function (res) {
				if (res.success && res.data.approval_url) {
					window.location.href = res.data.approval_url;
				} else {
					WPBS.showPaymentError(res.data.message || wpbsData.i18n.payment_error);
				}
			}).fail(function () {
				WPBS.showPaymentError(wpbsData.i18n.payment_error);
			});
		},

		processStripe: function () {
			if (!WPBS.stripe || !WPBS.stripeCardElement) {
				WPBS.showPaymentError('Stripe is not initialised.');
				return;
			}
			$.post({
				url: wpbsData.restUrl + 'payments/stripe/create-intent',
				data: JSON.stringify({ booking_id: WPBS.currentBookingId }),
				contentType: 'application/json',
				beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', wpbsData.nonce); }
			}).done(function (res) {
				WPBS.stripe.confirmCardPayment(res.client_secret, {
					payment_method: { card: WPBS.stripeCardElement }
				}).then(function (result) {
					if (result.error) {
						WPBS.showPaymentError(result.error.message);
					} else if ('succeeded' === result.paymentIntent.status) {
						$.post({
							url: wpbsData.restUrl + 'payments/stripe/confirm',
							data: JSON.stringify({
								payment_intent_id: result.paymentIntent.id,
								booking_id:        WPBS.currentBookingId
							}),
							contentType: 'application/json',
							beforeSend:  function (xhr) { xhr.setRequestHeader('X-WP-Nonce', wpbsData.nonce); }
						}).done(function (confirmRes) {
							if (confirmRes.success) { WPBS.showConfirmation(); }
							else { WPBS.showPaymentError(confirmRes.message || wpbsData.i18n.payment_error); }
						});
					}
				});
			}).fail(function () {
				WPBS.showPaymentError(wpbsData.i18n.payment_error);
			});
		},

		showPaymentError: function (msg) {
			$('#wpbs-payment-processing').hide();
			$('#wpbs-pay-btn').prop('disabled', false);
			$('#wpbs-payment-message').removeClass('success').addClass('error').text(msg).show();
		},

		showConfirmation: function () {
			$('#wpbs-confirm-message').text(wpbsData.i18n.payment_success);
			$('#wpbs-confirm-details').html(
				'<p><strong>Reference:</strong> ' + WPBS.currentBookingRef + '</p>' +
				'<p>' + wpbsData.i18n.booking_success + '</p>'
			);
			WPBS.goToStep(5);
		},

		showMessage: function (type, text) {
			var $msg = $('.wpbs-message').first();
			$msg.removeClass('success error').addClass(type).text(text).show();
			setTimeout(function () { $msg.fadeOut(); }, 5000);
		},

		// ── Customer Dashboard ──

		initDashboard: function () {
			$(document).on('click', '.wpbs-cancel-booking', function () {
				if (!window.confirm(wpbsData.i18n.confirm_cancel)) return;
				var bookingId = $(this).data('booking-id');
				var $btn = $(this).prop('disabled', true);
				$.post({
					url:  wpbsData.ajaxUrl,
					data: {
						action:     'wpbs_public_action',
						sub_action: 'cancel_booking',
						nonce:      wpbsData.publicNonce,
						booking_id: bookingId
					}
				}).done(function (res) {
					if (res.success) {
						$('#wpbs-message').removeClass('error').addClass('success message').text(res.data.message).show();
						$btn.closest('.wpbs-booking-card').fadeOut();
					} else {
						$('#wpbs-message').removeClass('success').addClass('error message').text(res.data.message).show();
						$btn.prop('disabled', false);
					}
				}).fail(function () {
					$('#wpbs-message').addClass('error message').text(wpbsData.i18n.error || 'Error').show();
					$btn.prop('disabled', false);
				});
			});

			$(document).on('click', '.wpbs-download-invoice', function () {
				var bookingId = $(this).data('booking-id');
				$.post({
					url:  wpbsData.ajaxUrl,
					data: { action: 'wpbs_public_action', sub_action: 'get_invoice', nonce: wpbsData.publicNonce, booking_id: bookingId }
				}).done(function (res) {
					if (res.success) {
						var b = res.data.booking, p = res.data.payment;
						var html = '<table style="border-collapse:collapse;width:100%">';
						html += '<tr><th style="text-align:left;padding:6px">Ref</th><td style="padding:6px">' + b.booking_ref + '</td></tr>';
						html += '<tr><th style="text-align:left;padding:6px">Service</th><td style="padding:6px">' + b.service_name + '</td></tr>';
						html += '<tr><th style="text-align:left;padding:6px">Date</th><td style="padding:6px">' + b.booking_date + ' ' + b.booking_time + '</td></tr>';
						html += '<tr><th style="text-align:left;padding:6px">Amount</th><td style="padding:6px">' + wpbsData.currency + parseFloat(b.amount).toFixed(2) + '</td></tr>';
						if (p) { html += '<tr><th style="text-align:left;padding:6px">Transaction</th><td style="padding:6px">' + (p.transaction_id || 'N/A') + '</td></tr>'; }
						html += '</table>';
						var win = window.open('', '_blank');
						win.document.write('<html><head><title>Invoice – ' + b.booking_ref + '</title></head><body>' + html + '<p><button onclick="window.print()">Print</button></p></body></html>');
						win.document.close();
					}
				});
			});
		}
	};

	$(document).ready(function () { WPBS.init(); });

}(jQuery));

/* WP Booking System – Admin JS */
(function ($) {
	'use strict';

	// Bulk select all
	$('#wpbs-select-all').on('change', function () {
		$('input[name="booking_ids[]"]').prop('checked', this.checked);
	});

	// Prevent accidental bulk delete
	$('#wpbs-bookings-form').on('submit', function (e) {
		var action = $('#wpbs-bulk-action').val();
		if ('delete' === action) {
			if (!window.confirm(wpbsAdmin.i18n.confirm_delete)) {
				e.preventDefault();
			}
		}
	});

}(jQuery));

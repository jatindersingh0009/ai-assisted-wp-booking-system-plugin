<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap wpbs-admin">
	<h1><?php esc_html_e( 'Booking Calendar', 'wp-booking-system' ); ?></h1>

	<div class="wpbs-calendar-filters">
		<label><?php esc_html_e( 'Filter by Service:', 'wp-booking-system' ); ?></label>
		<select id="wpbs-calendar-service">
			<option value="0"><?php esc_html_e( 'All Services', 'wp-booking-system' ); ?></option>
			<?php foreach ( $services as $svc ) : ?>
			<option value="<?php echo esc_attr( $svc['id'] ); ?>"><?php echo esc_html( $svc['name'] ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>

	<div id="wpbs-calendar" class="wpbs-calendar-wrapper"></div>

	<!-- Booking Detail Modal -->
	<div id="wpbs-booking-modal" class="wpbs-modal" style="display:none">
		<div class="wpbs-modal-content">
			<span class="wpbs-modal-close">&times;</span>
			<h2><?php esc_html_e( 'Booking Details', 'wp-booking-system' ); ?></h2>
			<div id="wpbs-modal-body"></div>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var calendarEl = document.getElementById('wpbs-calendar');
	if (!calendarEl || typeof FullCalendar === 'undefined') return;

	var calendar = new FullCalendar.Calendar(calendarEl, {
		initialView: 'dayGridMonth',
		headerToolbar: {
			left: 'prev,next today',
			center: 'title',
			right: 'dayGridMonth,timeGridWeek,timeGridDay'
		},
		events: function(info, successCallback, failureCallback) {
			var serviceId = document.getElementById('wpbs-calendar-service').value;
			jQuery.post(wpbsAdmin.ajaxUrl, {
				action: 'wpbs_admin_action',
				sub_action: 'get_calendar_events',
				nonce: wpbsAdmin.adminNonce,
				service_id: serviceId,
				start: info.startStr,
				end: info.endStr
			}, function(response) {
				if (response.success) {
					successCallback(response.data);
				} else {
					failureCallback(response.data);
				}
			});
		},
		eventClick: function(info) {
			var props = info.event.extendedProps;
			var modal = document.getElementById('wpbs-booking-modal');
			var body  = document.getElementById('wpbs-modal-body');
			body.innerHTML = '<table class="wpbs-detail-table">' +
				'<tr><th><?php esc_html_e( 'Reference', 'wp-booking-system' ); ?></th><td>' + props.booking_ref + '</td></tr>' +
				'<tr><th><?php esc_html_e( 'Customer', 'wp-booking-system' ); ?></th><td>' + props.customer + '</td></tr>' +
				'<tr><th><?php esc_html_e( 'Status', 'wp-booking-system' ); ?></th><td>' + props.status + '</td></tr>' +
				'<tr><th><?php esc_html_e( 'Amount', 'wp-booking-system' ); ?></th><td><?php echo esc_js( get_option( 'wpbs_currency_symbol', '$' ) ); ?>' + parseFloat(props.amount).toFixed(2) + '</td></tr>' +
				'</table>' +
				'<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbs-bookings&action=view&id=' ) ); ?>' + info.event.id + '" class="button button-primary"><?php esc_html_e( 'View Full Details', 'wp-booking-system' ); ?></a></p>';
			modal.style.display = 'flex';
		}
	});

	calendar.render();

	document.getElementById('wpbs-calendar-service').addEventListener('change', function() {
		calendar.refetchEvents();
	});

	document.querySelector('.wpbs-modal-close').addEventListener('click', function() {
		document.getElementById('wpbs-booking-modal').style.display = 'none';
	});
});
</script>

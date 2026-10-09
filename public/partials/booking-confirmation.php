<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wpbs-booking-confirmation-wrap">
<?php if ( $booking ) : ?>
	<div class="wpbs-confirmation">
		<div class="wpbs-confirmation-icon <?php echo 'confirmed' === $booking['booking_status'] || 'paid' === $booking['payment_status'] ? 'wpbs-icon-success' : 'wpbs-icon-pending'; ?>">
			<?php echo 'confirmed' === $booking['booking_status'] ? '✓' : '⏳'; ?>
		</div>
		<h2><?php 'confirmed' === $booking['booking_status'] ? esc_html_e( 'Booking Confirmed!', 'wp-booking-system' ) : esc_html_e( 'Booking Received', 'wp-booking-system' ); ?></h2>

		<div class="wpbs-confirmation-details">
			<table class="wpbs-detail-table">
				<tr><th><?php esc_html_e( 'Reference', 'wp-booking-system' ); ?></th><td><strong><?php echo esc_html( $booking['booking_ref'] ); ?></strong></td></tr>
				<tr><th><?php esc_html_e( 'Service', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['service_name'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Date', 'wp-booking-system' ); ?></th><td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking['booking_date'] ) ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Time', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['booking_time'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Duration', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['duration'] ); ?> <?php esc_html_e( 'minutes', 'wp-booking-system' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Amount', 'wp-booking-system' ); ?></th><td><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) $booking['amount'], 2 ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Payment Status', 'wp-booking-system' ); ?></th><td><span class="wpbs-badge wpbs-badge-<?php echo esc_attr( $booking['payment_status'] ); ?>"><?php echo esc_html( ucfirst( $booking['payment_status'] ) ); ?></span></td></tr>
				<tr><th><?php esc_html_e( 'Booking Status', 'wp-booking-system' ); ?></th><td><span class="wpbs-badge wpbs-badge-<?php echo esc_attr( $booking['booking_status'] ); ?>"><?php echo esc_html( ucfirst( $booking['booking_status'] ) ); ?></span></td></tr>
			</table>
		</div>

		<p><?php esc_html_e( 'A confirmation email has been sent to your email address.', 'wp-booking-system' ); ?></p>

		<div class="wpbs-confirm-actions">
			<a href="<?php echo esc_url( get_option( 'wpbs_customer_dashboard' ) ? get_permalink( get_option( 'wpbs_customer_dashboard' ) ) : home_url() ); ?>" class="wpbs-btn wpbs-btn-primary"><?php esc_html_e( 'View My Bookings', 'wp-booking-system' ); ?></a>
		</div>
	</div>
<?php else : ?>
	<div class="wpbs-error-state">
		<h2><?php esc_html_e( 'Booking Not Found', 'wp-booking-system' ); ?></h2>
		<p><?php esc_html_e( 'The booking you are looking for could not be found.', 'wp-booking-system' ); ?></p>
		<a href="<?php echo esc_url( home_url() ); ?>" class="wpbs-btn wpbs-btn-primary"><?php esc_html_e( 'Go Home', 'wp-booking-system' ); ?></a>
	</div>
<?php endif; ?>
</div>

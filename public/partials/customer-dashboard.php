<?php defined( 'ABSPATH' ) || exit;
$current_user = wp_get_current_user();
?>
<div class="wpbs-customer-dashboard">
	<div class="wpbs-dashboard-header">
		<h2><?php echo esc_html( sprintf( __( 'Welcome, %s', 'wp-booking-system' ), $current_user->display_name ) ); ?></h2>
		<a href="<?php echo esc_url( get_option( 'wpbs_booking_page' ) ? get_permalink( get_option( 'wpbs_booking_page' ) ) : home_url() ); ?>" class="wpbs-btn wpbs-btn-primary"><?php esc_html_e( '+ New Booking', 'wp-booking-system' ); ?></a>
	</div>

	<nav class="wpbs-dashboard-tabs">
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'upcoming' ) ); ?>" class="<?php echo 'upcoming' === $tab ? 'active' : ''; ?>"><?php esc_html_e( 'Upcoming', 'wp-booking-system' ); ?></a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'history' ) ); ?>" class="<?php echo 'history' === $tab ? 'active' : ''; ?>"><?php esc_html_e( 'History', 'wp-booking-system' ); ?></a>
	</nav>

	<div id="wpbs-message" class="wpbs-message" style="display:none"></div>

	<?php if ( empty( $bookings ) ) : ?>
	<div class="wpbs-empty-state">
		<p><?php 'upcoming' === $tab ? esc_html_e( 'You have no upcoming bookings.', 'wp-booking-system' ) : esc_html_e( 'No booking history found.', 'wp-booking-system' ); ?></p>
		<a href="<?php echo esc_url( get_option( 'wpbs_booking_page' ) ? get_permalink( get_option( 'wpbs_booking_page' ) ) : home_url() ); ?>" class="wpbs-btn wpbs-btn-primary"><?php esc_html_e( 'Book Now', 'wp-booking-system' ); ?></a>
	</div>
	<?php else : ?>
	<div class="wpbs-bookings-list">
		<?php foreach ( $bookings as $b ) :
			$can_cancel = WPBS_Booking::can_cancel( $b );
		?>
		<div class="wpbs-booking-card" data-booking-id="<?php echo esc_attr( $b['id'] ); ?>">
			<div class="wpbs-booking-card-header">
				<span class="wpbs-booking-ref"><?php echo esc_html( $b['booking_ref'] ); ?></span>
				<span class="wpbs-badge wpbs-badge-<?php echo esc_attr( $b['booking_status'] ); ?>"><?php echo esc_html( ucfirst( $b['booking_status'] ) ); ?></span>
			</div>
			<div class="wpbs-booking-card-body">
				<div class="wpbs-booking-detail">
					<span class="wpbs-detail-icon">📋</span>
					<span><?php echo esc_html( $b['service_name'] ); ?></span>
				</div>
				<div class="wpbs-booking-detail">
					<span class="wpbs-detail-icon">📅</span>
					<span><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $b['booking_date'] . ' ' . $b['booking_time'] ) ) ); ?></span>
				</div>
				<div class="wpbs-booking-detail">
					<span class="wpbs-detail-icon">⏱</span>
					<span><?php echo esc_html( $b['duration'] . ' ' . __( 'minutes', 'wp-booking-system' ) ); ?></span>
				</div>
				<div class="wpbs-booking-detail">
					<span class="wpbs-detail-icon">💳</span>
					<span><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) $b['amount'], 2 ) ); ?> — <span class="wpbs-badge wpbs-badge-<?php echo esc_attr( $b['payment_status'] ); ?>"><?php echo esc_html( ucfirst( $b['payment_status'] ) ); ?></span></span>
				</div>
			</div>
			<div class="wpbs-booking-card-footer">
				<?php if ( $can_cancel && in_array( $b['booking_status'], array( 'pending', 'confirmed' ), true ) ) : ?>
				<button class="wpbs-btn wpbs-btn-danger wpbs-btn-sm wpbs-cancel-booking" data-booking-id="<?php echo esc_attr( $b['id'] ); ?>"><?php esc_html_e( 'Cancel', 'wp-booking-system' ); ?></button>
				<?php endif; ?>
				<?php if ( 'paid' === $b['payment_status'] ) : ?>
				<button class="wpbs-btn wpbs-btn-outline wpbs-btn-sm wpbs-download-invoice" data-booking-id="<?php echo esc_attr( $b['id'] ); ?>"><?php esc_html_e( 'Invoice', 'wp-booking-system' ); ?></button>
				<?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
</div>

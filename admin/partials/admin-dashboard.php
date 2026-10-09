<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap wpbs-admin">
	<h1><?php esc_html_e( 'Booking System Dashboard', 'wp-booking-system' ); ?></h1>

	<div class="wpbs-stats-grid">
		<div class="wpbs-stat-card">
			<span class="wpbs-stat-icon dashicons dashicons-calendar-alt"></span>
			<div class="wpbs-stat-content">
				<span class="wpbs-stat-number"><?php echo esc_html( number_format( $stats['total_bookings'] ) ); ?></span>
				<span class="wpbs-stat-label"><?php esc_html_e( 'Total Bookings', 'wp-booking-system' ); ?></span>
			</div>
		</div>
		<div class="wpbs-stat-card wpbs-stat-warning">
			<span class="wpbs-stat-icon dashicons dashicons-clock"></span>
			<div class="wpbs-stat-content">
				<span class="wpbs-stat-number"><?php echo esc_html( number_format( $stats['pending_bookings'] ) ); ?></span>
				<span class="wpbs-stat-label"><?php esc_html_e( 'Pending', 'wp-booking-system' ); ?></span>
			</div>
		</div>
		<div class="wpbs-stat-card wpbs-stat-success">
			<span class="wpbs-stat-icon dashicons dashicons-yes-alt"></span>
			<div class="wpbs-stat-content">
				<span class="wpbs-stat-number"><?php echo esc_html( number_format( $stats['confirmed_bookings'] ) ); ?></span>
				<span class="wpbs-stat-label"><?php esc_html_e( 'Confirmed', 'wp-booking-system' ); ?></span>
			</div>
		</div>
		<div class="wpbs-stat-card wpbs-stat-info">
			<span class="wpbs-stat-icon dashicons dashicons-money-alt"></span>
			<div class="wpbs-stat-content">
				<span class="wpbs-stat-number"><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) $stats['revenue']['total_revenue'], 2 ) ); ?></span>
				<span class="wpbs-stat-label"><?php esc_html_e( 'Total Revenue', 'wp-booking-system' ); ?></span>
			</div>
		</div>
	</div>

	<div class="wpbs-dashboard-row">
		<div class="wpbs-dashboard-col">
			<div class="wpbs-card">
				<h2><?php esc_html_e( 'Recent Bookings', 'wp-booking-system' ); ?></h2>
				<table class="wpbs-table widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Ref', 'wp-booking-system' ); ?></th>
							<th><?php esc_html_e( 'Customer', 'wp-booking-system' ); ?></th>
							<th><?php esc_html_e( 'Service', 'wp-booking-system' ); ?></th>
							<th><?php esc_html_e( 'Date', 'wp-booking-system' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wp-booking-system' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'wp-booking-system' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php if ( empty( $stats['recent_bookings'] ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No bookings yet.', 'wp-booking-system' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $stats['recent_bookings'] as $booking ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbs-bookings&action=view&id=' . $booking['id'] ) ); ?>"><?php echo esc_html( $booking['booking_ref'] ); ?></a></td>
							<td><?php echo esc_html( $booking['customer_name'] ); ?></td>
							<td><?php echo esc_html( $booking['service_name'] ); ?></td>
							<td><?php echo esc_html( $booking['booking_date'] . ' ' . $booking['booking_time'] ); ?></td>
							<td><span class="wpbs-badge wpbs-badge-<?php echo esc_attr( $booking['booking_status'] ); ?>"><?php echo esc_html( ucfirst( $booking['booking_status'] ) ); ?></span></td>
							<td><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) $booking['amount'], 2 ) ); ?></td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
				<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbs-bookings' ) ); ?>" class="button"><?php esc_html_e( 'View All Bookings', 'wp-booking-system' ); ?></a></p>
			</div>
		</div>

		<div class="wpbs-dashboard-col wpbs-dashboard-col-sidebar">
			<div class="wpbs-card">
				<h2><?php esc_html_e( 'Quick Actions', 'wp-booking-system' ); ?></h2>
				<ul class="wpbs-quick-actions">
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbs-services&action=new' ) ); ?>" class="button button-primary"><?php esc_html_e( '+ Add Service', 'wp-booking-system' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbs-calendar' ) ); ?>" class="button"><?php esc_html_e( 'View Calendar', 'wp-booking-system' ); ?></a></li>
					<li><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wpbs-bookings&wpbs_export=csv' ), 'wpbs_export_csv' ) ); ?>" class="button"><?php esc_html_e( 'Export CSV', 'wp-booking-system' ); ?></a></li>
					<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbs-settings' ) ); ?>" class="button"><?php esc_html_e( 'Settings', 'wp-booking-system' ); ?></a></li>
				</ul>
			</div>

			<div class="wpbs-card">
				<h2><?php esc_html_e( "Today's Bookings", 'wp-booking-system' ); ?></h2>
				<p class="wpbs-big-number"><?php echo esc_html( $stats['today_bookings'] ); ?></p>
				<p><?php echo esc_html( sprintf( __( 'bookings on %s', 'wp-booking-system' ), current_time( 'F j, Y' ) ) ); ?></p>
			</div>
		</div>
	</div>
</div>

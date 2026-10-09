<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap wpbs-admin">
	<h1><?php esc_html_e( 'Reports', 'wp-booking-system' ); ?></h1>

	<form method="get" class="wpbs-filter-form">
		<input type="hidden" name="page" value="wpbs-reports">
		<label><?php esc_html_e( 'From:', 'wp-booking-system' ); ?> <input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>"></label>
		<label><?php esc_html_e( 'To:', 'wp-booking-system' ); ?> <input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>"></label>
		<button type="submit" class="button"><?php esc_html_e( 'Apply', 'wp-booking-system' ); ?></button>
	</form>

	<div class="wpbs-stats-grid">
		<div class="wpbs-stat-card wpbs-stat-success">
			<div class="wpbs-stat-content">
				<span class="wpbs-stat-number"><?php echo esc_html( number_format( $stats['total_bookings'] ) ); ?></span>
				<span class="wpbs-stat-label"><?php esc_html_e( 'Paid Bookings', 'wp-booking-system' ); ?></span>
			</div>
		</div>
		<div class="wpbs-stat-card">
			<div class="wpbs-stat-content">
				<span class="wpbs-stat-number"><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) $stats['total_revenue'], 2 ) ); ?></span>
				<span class="wpbs-stat-label"><?php esc_html_e( 'Total Revenue', 'wp-booking-system' ); ?></span>
			</div>
		</div>
		<div class="wpbs-stat-card">
			<div class="wpbs-stat-content">
				<span class="wpbs-stat-number"><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) $stats['avg_revenue'], 2 ) ); ?></span>
				<span class="wpbs-stat-label"><?php esc_html_e( 'Average per Booking', 'wp-booking-system' ); ?></span>
			</div>
		</div>
	</div>

	<div class="wpbs-dashboard-row">
		<div class="wpbs-dashboard-col">
			<div class="wpbs-card">
				<h2><?php esc_html_e( 'Revenue by Service', 'wp-booking-system' ); ?></h2>
				<table class="wpbs-table widefat striped">
					<thead><tr><th><?php esc_html_e( 'Service', 'wp-booking-system' ); ?></th><th><?php esc_html_e( 'Bookings', 'wp-booking-system' ); ?></th><th><?php esc_html_e( 'Revenue', 'wp-booking-system' ); ?></th></tr></thead>
					<tbody>
					<?php if ( empty( $by_service ) ) : ?>
						<tr><td colspan="3"><?php esc_html_e( 'No data.', 'wp-booking-system' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $by_service as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['service_name'] ); ?></td>
							<td><?php echo esc_html( number_format( $row['count'] ) ); ?></td>
							<td><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) $row['revenue'], 2 ) ); ?></td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="wpbs-dashboard-col">
			<div class="wpbs-card">
				<h2><?php esc_html_e( 'Daily Bookings', 'wp-booking-system' ); ?></h2>
				<div id="wpbs-revenue-chart" style="height:300px;display:flex;align-items:flex-end;gap:4px;padding:10px 0;">
					<?php
					$max_revenue = max( array_column( $daily_data, 'revenue' ) ?: array( 1 ) );
					foreach ( $daily_data as $day ) :
						$height = max( 4, round( ( (float) $day['revenue'] / $max_revenue ) * 260 ) );
					?>
					<div class="wpbs-chart-bar" style="flex:1;height:<?php echo esc_attr( $height ); ?>px;background:#0073aa;border-radius:3px 3px 0 0;" title="<?php echo esc_attr( $day['booking_date'] . ': ' . get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) $day['revenue'], 2 ) ); ?>"></div>
					<?php endforeach; ?>
				</div>
				<table class="wpbs-table widefat striped">
					<thead><tr><th><?php esc_html_e( 'Date', 'wp-booking-system' ); ?></th><th><?php esc_html_e( 'Bookings', 'wp-booking-system' ); ?></th><th><?php esc_html_e( 'Revenue', 'wp-booking-system' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $daily_data as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['booking_date'] ); ?></td>
						<td><?php echo esc_html( $row['count'] ); ?></td>
						<td><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) $row['revenue'], 2 ) ); ?></td>
					</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<p>
		<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'wpbs-bookings', 'wpbs_export' => 'csv', 'date_from' => $date_from, 'date_to' => $date_to ), admin_url( 'admin.php' ) ), 'wpbs_export_csv' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Export CSV', 'wp-booking-system' ); ?></a>
	</p>
</div>

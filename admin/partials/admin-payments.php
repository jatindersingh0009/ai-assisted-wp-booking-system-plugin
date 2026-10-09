<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap wpbs-admin">
	<h1><?php esc_html_e( 'Payments', 'wp-booking-system' ); ?></h1>

	<!-- Summary Cards -->
	<div class="wpbs-stats-grid">
	<?php
	$totals = array( 'paypal' => array( 'count' => 0, 'total' => 0 ), 'stripe' => array( 'count' => 0, 'total' => 0 ) );
	foreach ( $summary as $row ) {
		$totals[ $row['gateway'] ] = $row;
	}
	?>
		<div class="wpbs-stat-card">
			<span class="wpbs-stat-icon dashicons dashicons-money"></span>
			<div class="wpbs-stat-content">
				<span class="wpbs-stat-number"><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( array_sum( array_column( $summary, 'total' ) ), 2 ) ); ?></span>
				<span class="wpbs-stat-label"><?php esc_html_e( 'Total Collected', 'wp-booking-system' ); ?></span>
			</div>
		</div>
		<div class="wpbs-stat-card">
			<span class="wpbs-stat-icon dashicons dashicons-paypal"></span>
			<div class="wpbs-stat-content">
				<span class="wpbs-stat-number"><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) ( $totals['paypal']['total'] ?? 0 ), 2 ) ); ?></span>
				<span class="wpbs-stat-label">PayPal (<?php echo esc_html( $totals['paypal']['count'] ?? 0 ); ?> <?php esc_html_e( 'txn', 'wp-booking-system' ); ?>)</span>
			</div>
		</div>
		<div class="wpbs-stat-card">
			<span class="wpbs-stat-icon dashicons dashicons-lock"></span>
			<div class="wpbs-stat-content">
				<span class="wpbs-stat-number"><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) ( $totals['stripe']['total'] ?? 0 ), 2 ) ); ?></span>
				<span class="wpbs-stat-label">Stripe (<?php echo esc_html( $totals['stripe']['count'] ?? 0 ); ?> <?php esc_html_e( 'txn', 'wp-booking-system' ); ?>)</span>
			</div>
		</div>
		<div class="wpbs-stat-card wpbs-stat-warning">
			<span class="wpbs-stat-icon dashicons dashicons-undo"></span>
			<div class="wpbs-stat-content">
				<span class="wpbs-stat-number"><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( array_sum( array_column( $summary, 'refunded' ) ), 2 ) ); ?></span>
				<span class="wpbs-stat-label"><?php esc_html_e( 'Total Refunded', 'wp-booking-system' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Filters -->
	<form method="get" class="wpbs-filter-form">
		<input type="hidden" name="page" value="wpbs-payments">
		<select name="gateway">
			<option value=""><?php esc_html_e( 'All Gateways', 'wp-booking-system' ); ?></option>
			<option value="paypal" <?php selected( $_GET['gateway'] ?? '', 'paypal' ); ?>>PayPal</option>
			<option value="stripe" <?php selected( $_GET['gateway'] ?? '', 'stripe' ); ?>>Stripe</option>
		</select>
		<select name="status">
			<option value=""><?php esc_html_e( 'All Statuses', 'wp-booking-system' ); ?></option>
			<?php foreach ( array( 'pending', 'completed', 'failed', 'refunded', 'partially_refunded' ) as $s ) : ?>
			<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $_GET['status'] ?? '', $s ); ?>><?php echo esc_html( ucwords( str_replace( '_', ' ', $s ) ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="date" name="date_from" value="<?php echo esc_attr( $_GET['date_from'] ?? '' ); ?>">
		<input type="date" name="date_to" value="<?php echo esc_attr( $_GET['date_to'] ?? '' ); ?>">
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-booking-system' ); ?></button>
	</form>

	<table class="wpbs-table widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Transaction ID', 'wp-booking-system' ); ?></th>
				<th><?php esc_html_e( 'Booking', 'wp-booking-system' ); ?></th>
				<th><?php esc_html_e( 'Customer', 'wp-booking-system' ); ?></th>
				<th><?php esc_html_e( 'Gateway', 'wp-booking-system' ); ?></th>
				<th><?php esc_html_e( 'Amount', 'wp-booking-system' ); ?></th>
				<th><?php esc_html_e( 'Refunded', 'wp-booking-system' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wp-booking-system' ); ?></th>
				<th><?php esc_html_e( 'Date', 'wp-booking-system' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $payments ) ) : ?>
			<tr><td colspan="8"><?php esc_html_e( 'No payments found.', 'wp-booking-system' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $payments as $p ) : ?>
			<tr>
				<td><code><?php echo esc_html( substr( $p['transaction_id'] ?? 'N/A', 0, 20 ) . '...' ); ?></code></td>
				<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbs-bookings&action=view&id=' . $p['booking_id'] ) ); ?>"><?php echo esc_html( $p['booking_ref'] ?? '#' . $p['booking_id'] ); ?></a></td>
				<td><?php echo esc_html( $p['customer_name'] ?? '—' ); ?></td>
				<td><span class="wpbs-gateway-badge wpbs-gateway-<?php echo esc_attr( $p['gateway'] ); ?>"><?php echo esc_html( ucfirst( $p['gateway'] ) ); ?></span></td>
				<td><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) $p['amount'], 2 ) ); ?></td>
				<td><?php echo $p['refund_amount'] > 0 ? esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) $p['refund_amount'], 2 ) ) : '—'; ?></td>
				<td><span class="wpbs-badge wpbs-badge-<?php echo esc_attr( $p['status'] ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $p['status'] ) ) ); ?></span></td>
				<td><?php echo esc_html( $p['created_at'] ); ?></td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>
</div>

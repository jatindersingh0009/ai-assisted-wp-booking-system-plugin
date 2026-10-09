<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap wpbs-admin">

<?php if ( $booking ) : ?>
	<!-- Single Booking View -->
	<h1>
		<?php esc_html_e( 'Booking Details', 'wp-booking-system' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbs-bookings' ) ); ?>" class="page-title-action"><?php esc_html_e( '← Back', 'wp-booking-system' ); ?></a>
	</h1>

	<div class="wpbs-booking-detail-grid">
		<div class="wpbs-card wpbs-booking-info">
			<h2><?php esc_html_e( 'Booking Information', 'wp-booking-system' ); ?></h2>
			<table class="wpbs-detail-table">
				<tr><th><?php esc_html_e( 'Reference', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['booking_ref'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Customer', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['customer_name'] . ' (' . $booking['customer_email'] . ')' ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Service', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['service_name'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Date & Time', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['booking_date'] . ' at ' . $booking['booking_time'] ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Duration', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['duration'] . ' ' . __( 'minutes', 'wp-booking-system' ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Amount', 'wp-booking-system' ); ?></th><td><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) $booking['amount'], 2 ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Payment Method', 'wp-booking-system' ); ?></th><td><?php echo esc_html( ucfirst( $booking['payment_method'] ?? 'N/A' ) ); ?></td></tr>
				<tr><th><?php esc_html_e( 'Payment Status', 'wp-booking-system' ); ?></th><td><span class="wpbs-badge wpbs-badge-<?php echo esc_attr( $booking['payment_status'] ); ?>"><?php echo esc_html( ucfirst( $booking['payment_status'] ) ); ?></span></td></tr>
				<tr><th><?php esc_html_e( 'Booking Status', 'wp-booking-system' ); ?></th><td><span class="wpbs-badge wpbs-badge-<?php echo esc_attr( $booking['booking_status'] ); ?>"><?php echo esc_html( ucfirst( $booking['booking_status'] ) ); ?></span></td></tr>
				<?php if ( $booking['notes'] ) : ?>
				<tr><th><?php esc_html_e( 'Customer Notes', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['notes'] ); ?></td></tr>
				<?php endif; ?>
				<tr><th><?php esc_html_e( 'Created', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['created_at'] ); ?></td></tr>
			</table>
		</div>

		<div class="wpbs-card">
			<h2><?php esc_html_e( 'Actions', 'wp-booking-system' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'wpbs_admin_action', 'wpbs_admin_nonce' ); ?>
				<input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking['id'] ); ?>">
				<p>
					<label><?php esc_html_e( 'Update Status', 'wp-booking-system' ); ?></label>
					<select name="action" class="widefat">
						<option value="confirm"><?php esc_html_e( 'Confirm', 'wp-booking-system' ); ?></option>
						<option value="cancel"><?php esc_html_e( 'Cancel', 'wp-booking-system' ); ?></option>
					</select>
				</p>
				<p>
					<label><?php esc_html_e( 'Admin Notes', 'wp-booking-system' ); ?></label>
					<textarea name="admin_notes" class="widefat" rows="3"><?php echo esc_textarea( $booking['admin_notes'] ?? '' ); ?></textarea>
				</p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Update Booking', 'wp-booking-system' ); ?></button></p>
			</form>

			<?php if ( ! empty( $payment ) && 'completed' === $payment['status'] ) : ?>
			<hr>
			<h3><?php esc_html_e( 'Process Refund', 'wp-booking-system' ); ?></h3>
			<form method="post">
				<?php wp_nonce_field( 'wpbs_refund_action', 'wpbs_refund_nonce' ); ?>
				<input type="hidden" name="payment_id" value="<?php echo esc_attr( $payment['id'] ); ?>">
				<p>
					<label><?php esc_html_e( 'Refund Amount', 'wp-booking-system' ); ?></label>
					<input type="number" name="refund_amount" step="0.01" min="0.01" max="<?php echo esc_attr( (float) $payment['amount'] - (float) $payment['refund_amount'] ); ?>" class="widefat" value="<?php echo esc_attr( $payment['amount'] ); ?>">
				</p>
				<p><button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Confirm refund?', 'wp-booking-system' ); ?>')"><?php esc_html_e( 'Process Refund', 'wp-booking-system' ); ?></button></p>
			</form>
			<?php endif; ?>
		</div>
	</div>

<?php else : ?>
	<!-- Bookings List -->
	<h1>
		<?php esc_html_e( 'All Bookings', 'wp-booking-system' ); ?>
		<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wpbs-bookings&wpbs_export=csv' ), 'wpbs_export_csv' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'wp-booking-system' ); ?></a>
	</h1>

	<!-- Filters -->
	<form method="get" class="wpbs-filter-form">
		<input type="hidden" name="page" value="wpbs-bookings">
		<select name="status">
			<option value=""><?php esc_html_e( 'All Statuses', 'wp-booking-system' ); ?></option>
			<?php foreach ( array( 'pending', 'confirmed', 'cancelled', 'completed', 'rejected' ) as $s ) : ?>
			<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $_GET['status'] ?? '', $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="date" name="date_from" value="<?php echo esc_attr( $_GET['date_from'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'From Date', 'wp-booking-system' ); ?>">
		<input type="date" name="date_to" value="<?php echo esc_attr( $_GET['date_to'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'To Date', 'wp-booking-system' ); ?>">
		<input type="search" name="s" value="<?php echo esc_attr( $_GET['s'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Search...', 'wp-booking-system' ); ?>">
		<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wp-booking-system' ); ?></button>
	</form>

	<form method="post" id="wpbs-bookings-form">
		<?php wp_nonce_field( 'wpbs_admin_action', 'wpbs_admin_nonce' ); ?>
		<div class="wpbs-bulk-actions">
			<select name="action" id="wpbs-bulk-action">
				<option value=""><?php esc_html_e( 'Bulk Action', 'wp-booking-system' ); ?></option>
				<option value="confirm"><?php esc_html_e( 'Confirm', 'wp-booking-system' ); ?></option>
				<option value="cancel"><?php esc_html_e( 'Cancel', 'wp-booking-system' ); ?></option>
				<option value="delete"><?php esc_html_e( 'Delete', 'wp-booking-system' ); ?></option>
			</select>
			<button type="submit" class="button" id="wpbs-bulk-apply"><?php esc_html_e( 'Apply', 'wp-booking-system' ); ?></button>
		</div>

		<table class="wpbs-table widefat striped">
			<thead>
				<tr>
					<th><input type="checkbox" id="wpbs-select-all"></th>
					<th><?php esc_html_e( 'Ref', 'wp-booking-system' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'wp-booking-system' ); ?></th>
					<th><?php esc_html_e( 'Service', 'wp-booking-system' ); ?></th>
					<th><?php esc_html_e( 'Date & Time', 'wp-booking-system' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'wp-booking-system' ); ?></th>
					<th><?php esc_html_e( 'Payment', 'wp-booking-system' ); ?></th>
					<th><?php esc_html_e( 'Status', 'wp-booking-system' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'wp-booking-system' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $bookings ) ) : ?>
				<tr><td colspan="9" style="text-align:center"><?php esc_html_e( 'No bookings found.', 'wp-booking-system' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $bookings as $b ) : ?>
				<tr>
					<td><input type="checkbox" name="booking_ids[]" value="<?php echo esc_attr( $b['id'] ); ?>"></td>
					<td><strong><?php echo esc_html( $b['booking_ref'] ); ?></strong></td>
					<td><?php echo esc_html( $b['customer_name'] ); ?><br><small><?php echo esc_html( $b['customer_email'] ); ?></small></td>
					<td><?php echo esc_html( $b['service_name'] ); ?></td>
					<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $b['booking_date'] ) ) ); ?><br><small><?php echo esc_html( $b['booking_time'] ); ?></small></td>
					<td><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) $b['amount'], 2 ) ); ?></td>
					<td><span class="wpbs-badge wpbs-badge-<?php echo esc_attr( $b['payment_status'] ); ?>"><?php echo esc_html( ucfirst( $b['payment_status'] ) ); ?></span></td>
					<td><span class="wpbs-badge wpbs-badge-<?php echo esc_attr( $b['booking_status'] ); ?>"><?php echo esc_html( ucfirst( $b['booking_status'] ) ); ?></span></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbs-bookings&action=view&id=' . $b['id'] ) ); ?>" class="button button-small"><?php esc_html_e( 'View', 'wp-booking-system' ); ?></a>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
	</form>

	<?php
	$total_pages = ceil( $total / 20 );
	$current_page = absint( $_GET['paged'] ?? 1 );
	if ( $total_pages > 1 ) :
		echo '<div class="wpbs-pagination">';
		for ( $i = 1; $i <= $total_pages; $i++ ) {
			$url = add_query_arg( 'paged', $i );
			printf(
				'<a href="%s" class="button%s">%d</a> ',
				esc_url( $url ),
				$i === $current_page ? ' button-primary' : '',
				$i
			);
		}
		echo '</div>';
	endif;
	?>
<?php endif; ?>
</div>

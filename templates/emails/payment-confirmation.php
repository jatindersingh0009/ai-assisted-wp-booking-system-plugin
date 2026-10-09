<?php defined( 'ABSPATH' ) || exit; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
.email-wrap { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; }
.email-header { background: #28a745; color: #fff; padding: 32px 40px; text-align: center; }
.email-header h1 { margin: 0; font-size: 24px; }
.email-body { padding: 32px 40px; }
.detail-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
.detail-table th { background: #f8f9fa; padding: 10px 14px; text-align: left; font-weight: 600; width: 40%; border-bottom: 1px solid #e5e7eb; }
.detail-table td { padding: 10px 14px; border-bottom: 1px solid #e5e7eb; }
.total-row { font-size: 18px; font-weight: 700; color: #28a745; }
.email-footer { background: #f8f9fa; padding: 20px 40px; text-align: center; font-size: 12px; color: #888; }
</style>
</head>
<body>
<div class="email-wrap">
	<div class="email-header">
		<h1>💳 <?php esc_html_e( 'Payment Received', 'wp-booking-system' ); ?></h1>
	</div>
	<div class="email-body">
		<p><?php echo esc_html( sprintf( __( 'Hi %s,', 'wp-booking-system' ), $customer_name ) ); ?></p>
		<p><?php esc_html_e( 'Your payment has been successfully processed. Your booking is now confirmed.', 'wp-booking-system' ); ?></p>

		<table class="detail-table">
			<tr><th><?php esc_html_e( 'Booking Reference', 'wp-booking-system' ); ?></th><td><strong><?php echo esc_html( $booking['booking_ref'] ); ?></strong></td></tr>
			<tr><th><?php esc_html_e( 'Service', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['service_name'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Appointment', 'wp-booking-system' ); ?></th><td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking['booking_date'] ) ) . ' at ' . $booking['booking_time'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Payment Method', 'wp-booking-system' ); ?></th><td><?php echo esc_html( ucfirst( $payment['gateway'] ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Transaction ID', 'wp-booking-system' ); ?></th><td><code><?php echo esc_html( $payment['transaction_id'] ); ?></code></td></tr>
			<tr class="total-row"><th><?php esc_html_e( 'Amount Paid', 'wp-booking-system' ); ?></th><td><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) $payment['amount'], 2 ) ); ?></td></tr>
		</table>

		<p><?php esc_html_e( 'Please keep this email as your receipt. See you at your appointment!', 'wp-booking-system' ); ?></p>
	</div>
	<div class="email-footer">
		<p><?php echo esc_html( $site_name ); ?> &bull; <a href="<?php echo esc_url( home_url() ); ?>"><?php echo esc_html( home_url() ); ?></a></p>
	</div>
</div>
</body>
</html>

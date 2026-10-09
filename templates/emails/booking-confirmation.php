<?php defined( 'ABSPATH' ) || exit; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width">
<style>
body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
.email-wrap { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; }
.email-header { background: #0073aa; color: #fff; padding: 32px 40px; text-align: center; }
.email-header h1 { margin: 0; font-size: 24px; }
.email-body { padding: 32px 40px; }
.detail-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
.detail-table th { background: #f8f9fa; padding: 10px 14px; text-align: left; font-weight: 600; width: 40%; border-bottom: 1px solid #e5e7eb; }
.detail-table td { padding: 10px 14px; border-bottom: 1px solid #e5e7eb; }
.badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
.badge-confirmed { background: #d4edda; color: #155724; }
.badge-pending   { background: #fff3cd; color: #856404; }
.email-footer { background: #f8f9fa; padding: 20px 40px; text-align: center; font-size: 12px; color: #888; border-top: 1px solid #e5e7eb; }
.btn { display: inline-block; padding: 12px 24px; background: #0073aa; color: #fff !important; border-radius: 5px; text-decoration: none; font-weight: 600; margin: 16px 0; }
</style>
</head>
<body>
<div class="email-wrap">
	<div class="email-header">
		<h1>✓ <?php esc_html_e( 'Booking Confirmed', 'wp-booking-system' ); ?></h1>
	</div>
	<div class="email-body">
		<p><?php echo esc_html( sprintf( __( 'Hi %s,', 'wp-booking-system' ), $customer_name ) ); ?></p>
		<p><?php esc_html_e( 'Your booking has been received. Below are your booking details:', 'wp-booking-system' ); ?></p>

		<table class="detail-table">
			<tr><th><?php esc_html_e( 'Booking Reference', 'wp-booking-system' ); ?></th><td><strong><?php echo esc_html( $booking['booking_ref'] ); ?></strong></td></tr>
			<tr><th><?php esc_html_e( 'Service', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['service_name'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Date', 'wp-booking-system' ); ?></th><td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking['booking_date'] ) ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Time', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['booking_time'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Duration', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['duration'] . ' ' . __( 'minutes', 'wp-booking-system' ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Amount', 'wp-booking-system' ); ?></th><td><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) $booking['amount'], 2 ) ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Status', 'wp-booking-system' ); ?></th><td><span class="badge badge-<?php echo esc_attr( $booking['booking_status'] ); ?>"><?php echo esc_html( ucfirst( $booking['booking_status'] ) ); ?></span></td></tr>
		</table>

		<?php if ( get_option( 'wpbs_customer_dashboard' ) ) : ?>
		<p style="text-align:center">
			<a href="<?php echo esc_url( get_permalink( get_option( 'wpbs_customer_dashboard' ) ) ); ?>" class="btn"><?php esc_html_e( 'View My Bookings', 'wp-booking-system' ); ?></a>
		</p>
		<?php endif; ?>

		<p><?php esc_html_e( 'If you need to make any changes, please contact us.', 'wp-booking-system' ); ?></p>
		<p><?php esc_html_e( 'Thank you for your booking!', 'wp-booking-system' ); ?></p>
	</div>
	<div class="email-footer">
		<p><?php echo esc_html( $site_name ); ?> &bull; <a href="<?php echo esc_url( home_url() ); ?>"><?php echo esc_html( home_url() ); ?></a></p>
	</div>
</div>
</body>
</html>

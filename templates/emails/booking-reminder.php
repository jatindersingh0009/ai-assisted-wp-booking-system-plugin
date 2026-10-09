<?php defined( 'ABSPATH' ) || exit; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
.email-wrap { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; }
.email-header { background: #17a2b8; color: #fff; padding: 32px 40px; text-align: center; }
.email-header h1 { margin: 0; font-size: 24px; }
.email-body { padding: 32px 40px; }
.reminder-box { background: #e8f4f8; border-left: 4px solid #17a2b8; padding: 16px 20px; border-radius: 0 6px 6px 0; margin: 20px 0; font-size: 18px; font-weight: 600; }
.detail-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
.detail-table th { background: #f8f9fa; padding: 10px 14px; text-align: left; width: 40%; border-bottom: 1px solid #e5e7eb; }
.detail-table td { padding: 10px 14px; border-bottom: 1px solid #e5e7eb; }
.email-footer { background: #f8f9fa; padding: 20px 40px; text-align: center; font-size: 12px; color: #888; }
</style>
</head>
<body>
<div class="email-wrap">
	<div class="email-header">
		<h1>⏰ <?php esc_html_e( 'Appointment Reminder', 'wp-booking-system' ); ?></h1>
	</div>
	<div class="email-body">
		<p><?php echo esc_html( sprintf( __( 'Hi %s,', 'wp-booking-system' ), $customer_name ) ); ?></p>
		<p><?php esc_html_e( 'This is a friendly reminder that you have an appointment tomorrow:', 'wp-booking-system' ); ?></p>

		<div class="reminder-box">
			📅 <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking['booking_date'] ) ) . ' at ' . $booking['booking_time'] ); ?>
		</div>

		<table class="detail-table">
			<tr><th><?php esc_html_e( 'Reference', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['booking_ref'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Service', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['service_name'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Duration', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['duration'] . ' ' . __( 'minutes', 'wp-booking-system' ) ); ?></td></tr>
		</table>

		<p><?php esc_html_e( 'We look forward to seeing you. If you need to cancel, please do so as soon as possible.', 'wp-booking-system' ); ?></p>
	</div>
	<div class="email-footer">
		<p><?php echo esc_html( $site_name ); ?> &bull; <a href="<?php echo esc_url( home_url() ); ?>"><?php echo esc_html( home_url() ); ?></a></p>
	</div>
</div>
</body>
</html>

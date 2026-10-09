<?php defined( 'ABSPATH' ) || exit; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
.email-wrap { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; }
.email-header { background: #dc3545; color: #fff; padding: 32px 40px; text-align: center; }
.email-header h1 { margin: 0; font-size: 24px; }
.email-body { padding: 32px 40px; }
.detail-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
.detail-table th { background: #f8f9fa; padding: 10px 14px; text-align: left; width: 40%; border-bottom: 1px solid #e5e7eb; }
.detail-table td { padding: 10px 14px; border-bottom: 1px solid #e5e7eb; }
.reason-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px 16px; border-radius: 0 6px 6px 0; margin: 16px 0; }
.email-footer { background: #f8f9fa; padding: 20px 40px; text-align: center; font-size: 12px; color: #888; }
.btn { display: inline-block; padding: 12px 24px; background: #0073aa; color: #fff !important; border-radius: 5px; text-decoration: none; font-weight: 600; margin-top: 16px; }
</style>
</head>
<body>
<div class="email-wrap">
	<div class="email-header">
		<h1>✕ <?php esc_html_e( 'Booking Cancelled', 'wp-booking-system' ); ?></h1>
	</div>
	<div class="email-body">
		<p><?php echo esc_html( sprintf( __( 'Hi %s,', 'wp-booking-system' ), $customer_name ) ); ?></p>
		<p><?php esc_html_e( 'Your booking has been cancelled. Details below:', 'wp-booking-system' ); ?></p>

		<table class="detail-table">
			<tr><th><?php esc_html_e( 'Reference', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['booking_ref'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Service', 'wp-booking-system' ); ?></th><td><?php echo esc_html( $booking['service_name'] ); ?></td></tr>
			<tr><th><?php esc_html_e( 'Date', 'wp-booking-system' ); ?></th><td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking['booking_date'] ) ) . ' at ' . $booking['booking_time'] ); ?></td></tr>
		</table>

		<?php if ( $reason ) : ?>
		<div class="reason-box">
			<strong><?php esc_html_e( 'Reason:', 'wp-booking-system' ); ?></strong> <?php echo esc_html( $reason ); ?>
		</div>
		<?php endif; ?>

		<?php if ( in_array( $booking['payment_status'], array( 'paid', 'partially_refunded' ), true ) ) : ?>
		<p><?php esc_html_e( 'If a refund is applicable, it will be processed within 5–10 business days.', 'wp-booking-system' ); ?></p>
		<?php endif; ?>

		<p><?php esc_html_e( 'We hope to see you again soon.', 'wp-booking-system' ); ?></p>

		<?php if ( get_option( 'wpbs_booking_page' ) ) : ?>
		<p style="text-align:center">
			<a href="<?php echo esc_url( get_permalink( get_option( 'wpbs_booking_page' ) ) ); ?>" class="btn"><?php esc_html_e( 'Book Again', 'wp-booking-system' ); ?></a>
		</p>
		<?php endif; ?>
	</div>
	<div class="email-footer">
		<p><?php echo esc_html( $site_name ); ?> &bull; <a href="<?php echo esc_url( home_url() ); ?>"><?php echo esc_html( home_url() ); ?></a></p>
	</div>
</div>
</body>
</html>

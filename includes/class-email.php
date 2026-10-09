<?php
defined( 'ABSPATH' ) || exit;

class WPBS_Email {

	public static function send_booking_confirmation( array $booking ): bool {
		$customer = get_userdata( (int) $booking['customer_id'] );
		if ( ! $customer ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %s: booking reference */
			__( 'Booking Confirmed – %s', 'wp-booking-system' ),
			$booking['booking_ref']
		);

		$message = self::get_template( 'booking-confirmation', array(
			'booking'       => $booking,
			'customer_name' => $customer->display_name,
			'site_name'     => get_bloginfo( 'name' ),
		) );

		$sent = self::send( $customer->user_email, $subject, $message );

		// Also notify admin
		self::send(
			get_option( 'wpbs_admin_email', get_option( 'admin_email' ) ),
			sprintf( __( 'New Booking – %s', 'wp-booking-system' ), $booking['booking_ref'] ),
			$message
		);

		return $sent;
	}

	public static function send_payment_confirmation( array $booking, array $payment ): bool {
		$customer = get_userdata( (int) $booking['customer_id'] );
		if ( ! $customer ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %s: booking reference */
			__( 'Payment Received – %s', 'wp-booking-system' ),
			$booking['booking_ref']
		);

		$message = self::get_template( 'payment-confirmation', array(
			'booking'       => $booking,
			'payment'       => $payment,
			'customer_name' => $customer->display_name,
			'site_name'     => get_bloginfo( 'name' ),
		) );

		return self::send( $customer->user_email, $subject, $message );
	}

	public static function send_booking_reminder( array $booking ): bool {
		$customer = get_userdata( (int) $booking['customer_id'] );
		if ( ! $customer ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %s: booking reference */
			__( 'Reminder: Your Appointment Tomorrow – %s', 'wp-booking-system' ),
			$booking['booking_ref']
		);

		$message = self::get_template( 'booking-reminder', array(
			'booking'       => $booking,
			'customer_name' => $customer->display_name,
			'site_name'     => get_bloginfo( 'name' ),
		) );

		// Mark reminder sent
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'reminder_sent' => 1 ),
			array( 'id' => $booking['id'] ),
			array( '%d' ),
			array( '%d' )
		);

		return self::send( $customer->user_email, $subject, $message );
	}

	public static function send_cancellation( array $booking, string $reason = '' ): bool {
		$customer = get_userdata( (int) $booking['customer_id'] );
		if ( ! $customer ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %s: booking reference */
			__( 'Booking Cancelled – %s', 'wp-booking-system' ),
			$booking['booking_ref']
		);

		$message = self::get_template( 'booking-cancellation', array(
			'booking'       => $booking,
			'customer_name' => $customer->display_name,
			'reason'        => $reason,
			'site_name'     => get_bloginfo( 'name' ),
		) );

		return self::send( $customer->user_email, $subject, $message );
	}

	private static function send( string $to, string $subject, string $message ): bool {
		$from_name  = get_option( 'wpbs_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'wpbs_from_email', get_option( 'admin_email' ) );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			"From: {$from_name} <{$from_email}>",
		);

		return wp_mail( $to, $subject, $message, $headers );
	}

	private static function get_template( string $template, array $vars = array() ): string {
		$file = WPBS_PLUGIN_DIR . "templates/emails/{$template}.php";

		if ( ! file_exists( $file ) ) {
			return '';
		}

		// Extract vars into local scope for the template
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		extract( $vars );

		ob_start();
		include $file;
		return ob_get_clean();
	}

	/**
	 * Cron callback: send reminders for bookings happening tomorrow.
	 */
	public static function send_scheduled_reminders(): void {
		global $wpdb;
		$tomorrow = ( new DateTime( 'tomorrow' ) )->format( 'Y-m-d' );
		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bookings
				 WHERE booking_date = %s
				   AND booking_status = 'confirmed'
				   AND reminder_sent = 0",
				$tomorrow
			),
			ARRAY_A
		);

		if ( $bookings ) {
			foreach ( $bookings as $booking ) {
				self::send_booking_reminder( $booking );
			}
		}
	}
}

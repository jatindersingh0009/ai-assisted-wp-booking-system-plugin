<?php
defined( 'ABSPATH' ) || exit;

class WPBS_Deactivator {

	public static function deactivate(): void {
		flush_rewrite_rules();
		wp_clear_scheduled_hook( 'wpbs_send_booking_reminders' );
		wp_clear_scheduled_hook( 'wpbs_cleanup_expired_bookings' );
	}
}

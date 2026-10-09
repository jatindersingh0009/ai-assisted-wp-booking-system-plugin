<?php
/**
 * Fired when the plugin is uninstalled.
 * Only runs when the user clicks "Delete" in the Plugins screen.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Only delete data if the user opted in to full cleanup
if ( ! get_option( 'wpbs_delete_data_on_uninstall' ) ) {
	return;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-database.php';
WPBS_Database::drop_tables();

// Remove all plugin options
$options = array(
	'wpbs_db_version',
	'wpbs_business_hours',
	'wpbs_timezone',
	'wpbs_booking_rules',
	'wpbs_currency',
	'wpbs_currency_symbol',
	'wpbs_paypal_mode',
	'wpbs_paypal_client_id',
	'wpbs_paypal_secret',
	'wpbs_paypal_webhook_id',
	'wpbs_stripe_mode',
	'wpbs_stripe_publishable_key',
	'wpbs_stripe_secret_key',
	'wpbs_stripe_webhook_secret',
	'wpbs_admin_email',
	'wpbs_from_name',
	'wpbs_from_email',
	'wpbs_booking_page',
	'wpbs_customer_dashboard',
	'wpbs_booking_confirmation',
	'wpbs_delete_data_on_uninstall',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

wp_clear_scheduled_hook( 'wpbs_send_booking_reminders' );
wp_clear_scheduled_hook( 'wpbs_cleanup_expired_bookings' );

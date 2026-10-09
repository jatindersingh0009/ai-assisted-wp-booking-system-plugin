<?php
defined( 'ABSPATH' ) || exit;

class WPBS_Activator {

	public static function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			$sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
			foreach ( $sites as $site_id ) {
				switch_to_blog( $site_id );
				self::run_activation();
				restore_current_blog();
			}
		} else {
			self::run_activation();
		}
	}

	private static function run_activation(): void {
		require_once WPBS_PLUGIN_DIR . 'includes/class-database.php';
		WPBS_Database::create_tables();
		self::create_pages();
		self::set_default_options();
		flush_rewrite_rules();
		update_option( 'wpbs_db_version', WPBS_VERSION );
	}

	private static function create_pages(): void {
		$pages = array(
			'wpbs_booking_page'       => array(
				'title'   => __( 'Book Appointment', 'wp-booking-system' ),
				'content' => '[wpbs_booking_form]',
			),
			'wpbs_customer_dashboard' => array(
				'title'   => __( 'My Bookings', 'wp-booking-system' ),
				'content' => '[wpbs_customer_dashboard]',
			),
			'wpbs_booking_confirmation' => array(
				'title'   => __( 'Booking Confirmed', 'wp-booking-system' ),
				'content' => '[wpbs_booking_confirmation]',
			),
		);

		foreach ( $pages as $option_key => $page_data ) {
			$existing = get_option( $option_key );
			if ( $existing && get_post( $existing ) ) {
				continue;
			}
			$page_id = wp_insert_post( array(
				'post_title'   => $page_data['title'],
				'post_content' => $page_data['content'],
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_author'  => 1,
			) );
			if ( $page_id && ! is_wp_error( $page_id ) ) {
				update_option( $option_key, $page_id );
			}
		}
	}

	private static function set_default_options(): void {
		$defaults = array(
			'wpbs_business_hours'       => array(
				'monday'    => array( 'open' => '09:00', 'close' => '17:00', 'enabled' => true ),
				'tuesday'   => array( 'open' => '09:00', 'close' => '17:00', 'enabled' => true ),
				'wednesday' => array( 'open' => '09:00', 'close' => '17:00', 'enabled' => true ),
				'thursday'  => array( 'open' => '09:00', 'close' => '17:00', 'enabled' => true ),
				'friday'    => array( 'open' => '09:00', 'close' => '17:00', 'enabled' => true ),
				'saturday'  => array( 'open' => '10:00', 'close' => '14:00', 'enabled' => false ),
				'sunday'    => array( 'open' => '10:00', 'close' => '14:00', 'enabled' => false ),
			),
			'wpbs_timezone'             => 'UTC',
			'wpbs_booking_rules'        => array(
				'min_advance_hours'     => 2,
				'max_advance_days'      => 60,
				'cancellation_hours'    => 24,
				'slot_interval_minutes' => 30,
			),
			'wpbs_currency'             => 'USD',
			'wpbs_currency_symbol'      => '$',
			'wpbs_paypal_mode'          => 'sandbox',
			'wpbs_paypal_client_id'     => '',
			'wpbs_paypal_secret'        => '',
			'wpbs_stripe_mode'          => 'test',
			'wpbs_stripe_publishable_key' => '',
			'wpbs_stripe_secret_key'    => '',
			'wpbs_stripe_webhook_secret' => '',
			'wpbs_admin_email'          => get_option( 'admin_email' ),
			'wpbs_from_name'            => get_option( 'blogname' ),
			'wpbs_from_email'           => get_option( 'admin_email' ),
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				update_option( $key, $value );
			}
		}
	}
}

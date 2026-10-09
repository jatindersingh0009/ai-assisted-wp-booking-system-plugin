<?php
defined( 'ABSPATH' ) || exit;

class WPBS_Plugin {

	private static ?self $instance = null;

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function run(): void {
		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_common_hooks();
	}

	private function load_dependencies(): void {
		require_once WPBS_PLUGIN_DIR . 'includes/class-database.php';
		require_once WPBS_PLUGIN_DIR . 'includes/class-service.php';
		require_once WPBS_PLUGIN_DIR . 'includes/class-availability.php';
		require_once WPBS_PLUGIN_DIR . 'includes/class-booking.php';
		require_once WPBS_PLUGIN_DIR . 'includes/class-payment.php';
		require_once WPBS_PLUGIN_DIR . 'includes/class-email.php';
		require_once WPBS_PLUGIN_DIR . 'includes/class-rest-api.php';
		require_once WPBS_PLUGIN_DIR . 'includes/gateways/class-paypal-gateway.php';
		require_once WPBS_PLUGIN_DIR . 'includes/gateways/class-stripe-gateway.php';

		if ( is_admin() ) {
			require_once WPBS_PLUGIN_DIR . 'admin/class-admin.php';
		}

		require_once WPBS_PLUGIN_DIR . 'public/class-public.php';
	}

	private function define_admin_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}
		$admin = new WPBS_Admin();
		add_action( 'admin_menu', array( $admin, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_wpbs_admin_action', array( $admin, 'handle_ajax' ) );

		// Handle DB upgrades
		add_action( 'plugins_loaded', array( $this, 'check_db_version' ) );
	}

	private function define_public_hooks(): void {
		$public = new WPBS_Public();
		add_action( 'wp_enqueue_scripts', array( $public, 'enqueue_scripts' ) );
		add_shortcode( 'wpbs_booking_form', array( $public, 'render_booking_form' ) );
		add_shortcode( 'wpbs_customer_dashboard', array( $public, 'render_customer_dashboard' ) );
		add_shortcode( 'wpbs_booking_confirmation', array( $public, 'render_booking_confirmation' ) );
		add_action( 'wp_ajax_wpbs_public_action', array( $public, 'handle_ajax' ) );
		add_action( 'wp_ajax_nopriv_wpbs_public_action', array( $public, 'handle_ajax_nopriv' ) );
	}

	private function define_common_hooks(): void {
		// REST API
		add_action( 'rest_api_init', function() {
			$api = new WPBS_REST_API();
			$api->register_routes();
		} );

		// Webhook endpoints
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_webhook_requests' ) );

		// Cron jobs
		add_action( 'wpbs_send_booking_reminders', array( 'WPBS_Email', 'send_scheduled_reminders' ) );
		add_action( 'wpbs_cleanup_expired_bookings', array( $this, 'cleanup_expired_bookings' ) );

		if ( ! wp_next_scheduled( 'wpbs_send_booking_reminders' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 08:00:00' ), 'daily', 'wpbs_send_booking_reminders' );
		}
		if ( ! wp_next_scheduled( 'wpbs_cleanup_expired_bookings' ) ) {
			wp_schedule_event( time(), 'daily', 'wpbs_cleanup_expired_bookings' );
		}

		// Email notifications
		add_action( 'wpbs_booking_created', array( 'WPBS_Email', 'send_booking_confirmation' ) );
		add_action( 'wpbs_payment_completed', array( $this, 'on_payment_completed' ) );
		add_action( 'wpbs_booking_status_updated', array( $this, 'on_booking_status_updated' ), 10, 2 );

		// i18n
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	public function register_rewrite_rules(): void {
		add_rewrite_rule( '^wpbs-webhook/paypal/?$', 'index.php?wpbs_webhook=paypal', 'top' );
		add_rewrite_rule( '^wpbs-webhook/stripe/?$', 'index.php?wpbs_webhook=stripe', 'top' );
	}

	public function add_query_vars( array $vars ): array {
		$vars[] = 'wpbs_webhook';
		return $vars;
	}

	public function handle_webhook_requests(): void {
		$webhook = get_query_var( 'wpbs_webhook' );
		if ( ! $webhook ) {
			return;
		}

		if ( 'paypal' === $webhook ) {
			$gateway = new WPBS_PayPal_Gateway();
			$gateway->handle_webhook();
		} elseif ( 'stripe' === $webhook ) {
			$gateway = new WPBS_Stripe_Gateway();
			$gateway->handle_webhook();
		}
	}

	public function on_payment_completed( array $booking, array $payment ): void {
		WPBS_Email::send_payment_confirmation( $booking, $payment );
	}

	public function on_booking_status_updated( array $booking, string $new_status ): void {
		if ( 'cancelled' === $new_status ) {
			WPBS_Email::send_cancellation( $booking );
		}
	}

	public function cleanup_expired_bookings(): void {
		global $wpdb;
		// Mark unpaid bookings older than 2 hours as expired
		$cutoff = ( new DateTime( '-2 hours' ) )->format( 'Y-m-d H:i:s' );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}bookings
				 SET booking_status = 'cancelled'
				 WHERE payment_status = 'pending'
				   AND booking_status = 'pending'
				   AND created_at < %s",
				$cutoff
			)
		);
	}

	public function check_db_version(): void {
		if ( get_option( 'wpbs_db_version' ) !== WPBS_VERSION ) {
			require_once WPBS_PLUGIN_DIR . 'includes/class-database.php';
			WPBS_Database::create_tables();
			update_option( 'wpbs_db_version', WPBS_VERSION );
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wp-booking-system',
			false,
			WPBS_PLUGIN_DIR . 'languages/'
		);
	}
}

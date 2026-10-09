<?php
defined( 'ABSPATH' ) || exit;

class WPBS_Admin {

	public function add_menu_pages(): void {
		add_menu_page(
			__( 'WP Booking System', 'wp-booking-system' ),
			__( 'Bookings', 'wp-booking-system' ),
			'manage_options',
			'wpbs-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-calendar-alt',
			30
		);

		add_submenu_page( 'wpbs-dashboard', __( 'Dashboard', 'wp-booking-system' ), __( 'Dashboard', 'wp-booking-system' ), 'manage_options', 'wpbs-dashboard', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'wpbs-dashboard', __( 'All Bookings', 'wp-booking-system' ), __( 'All Bookings', 'wp-booking-system' ), 'manage_options', 'wpbs-bookings', array( $this, 'render_bookings' ) );
		add_submenu_page( 'wpbs-dashboard', __( 'Services', 'wp-booking-system' ), __( 'Services', 'wp-booking-system' ), 'manage_options', 'wpbs-services', array( $this, 'render_services' ) );
		add_submenu_page( 'wpbs-dashboard', __( 'Calendar', 'wp-booking-system' ), __( 'Calendar', 'wp-booking-system' ), 'manage_options', 'wpbs-calendar', array( $this, 'render_calendar' ) );
		add_submenu_page( 'wpbs-dashboard', __( 'Payments', 'wp-booking-system' ), __( 'Payments', 'wp-booking-system' ), 'manage_options', 'wpbs-payments', array( $this, 'render_payments' ) );
		add_submenu_page( 'wpbs-dashboard', __( 'Reports', 'wp-booking-system' ), __( 'Reports', 'wp-booking-system' ), 'manage_options', 'wpbs-reports', array( $this, 'render_reports' ) );
		add_submenu_page( 'wpbs-dashboard', __( 'Settings', 'wp-booking-system' ), __( 'Settings', 'wp-booking-system' ), 'manage_options', 'wpbs-settings', array( $this, 'render_settings' ) );
	}

	public function enqueue_scripts( string $hook ): void {
		if ( false === strpos( $hook, 'wpbs-' ) ) {
			return;
		}

		wp_enqueue_style( 'wpbs-admin', WPBS_PLUGIN_URL . 'admin/css/admin.css', array(), WPBS_VERSION );
		wp_enqueue_script( 'wpbs-admin', WPBS_PLUGIN_URL . 'admin/js/admin.js', array( 'jquery', 'wp-api' ), WPBS_VERSION, true );

		wp_localize_script( 'wpbs-admin', 'wpbsAdmin', array(
			'restUrl'   => esc_url_raw( rest_url( 'wp-booking-system/v1/' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'adminNonce' => wp_create_nonce( 'wpbs_admin_nonce' ),
			'i18n'      => array(
				'confirm_delete'  => __( 'Are you sure you want to delete this?', 'wp-booking-system' ),
				'confirm_cancel'  => __( 'Are you sure you want to cancel this booking?', 'wp-booking-system' ),
				'confirm_refund'  => __( 'Are you sure you want to process this refund?', 'wp-booking-system' ),
				'loading'         => __( 'Loading...', 'wp-booking-system' ),
				'saved'           => __( 'Saved successfully.', 'wp-booking-system' ),
				'error'           => __( 'An error occurred. Please try again.', 'wp-booking-system' ),
			),
			'currency'  => get_option( 'wpbs_currency_symbol', '$' ),
		) );

		// Calendar page needs FullCalendar
		if ( 'bookings_page_wpbs-calendar' === $hook ) {
			wp_enqueue_script( 'fullcalendar', WPBS_PLUGIN_URL . 'admin/js/fullcalendar.min.js', array(), '6.1.10', true );
		}
	}

	public function render_dashboard(): void {
		$this->verify_admin_access();
		$stats = array(
			'total_bookings'     => WPBS_Booking::count(),
			'pending_bookings'   => WPBS_Booking::count( array( 'booking_status' => 'pending' ) ),
			'confirmed_bookings' => WPBS_Booking::count( array( 'booking_status' => 'confirmed' ) ),
			'today_bookings'     => WPBS_Booking::count( array( 'date_from' => current_time( 'Y-m-d' ), 'date_to' => current_time( 'Y-m-d' ) ) ),
			'revenue'            => WPBS_Booking::get_revenue_stats(),
			'recent_bookings'    => WPBS_Booking::get_all( array( 'limit' => 10 ) ),
		);
		include WPBS_PLUGIN_DIR . 'admin/partials/admin-dashboard.php';
	}

	public function render_bookings(): void {
		$this->verify_admin_access();

		// Handle bulk/single actions
		if ( isset( $_POST['wpbs_admin_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['wpbs_admin_nonce'] ), 'wpbs_admin_action' ) ) {
			$this->handle_booking_actions();
		}

		// Handle CSV export
		if ( isset( $_GET['wpbs_export'] ) && 'csv' === $_GET['wpbs_export'] ) {
			check_admin_referer( 'wpbs_export_csv' );
			$this->export_bookings_csv();
			exit;
		}

		$filters = array(
			'booking_status' => sanitize_text_field( $_GET['status'] ?? '' ),
			'date_from'      => sanitize_text_field( $_GET['date_from'] ?? '' ),
			'date_to'        => sanitize_text_field( $_GET['date_to'] ?? '' ),
			'search'         => sanitize_text_field( $_GET['s'] ?? '' ),
			'limit'          => 20,
			'offset'         => ( absint( $_GET['paged'] ?? 1 ) - 1 ) * 20,
		);

		$bookings = WPBS_Booking::get_all( $filters );
		$total    = WPBS_Booking::count( $filters );
		$services = WPBS_Service::get_all();
		$booking  = null;

		if ( ! empty( $_GET['action'] ) && 'view' === $_GET['action'] && ! empty( $_GET['id'] ) ) {
			$booking = WPBS_Booking::get( absint( $_GET['id'] ) );
			$payment = $booking ? WPBS_Payment::get_by_booking( (int) $booking['id'] ) : null;
		}

		include WPBS_PLUGIN_DIR . 'admin/partials/admin-bookings.php';
	}

	public function render_services(): void {
		$this->verify_admin_access();

		if ( isset( $_POST['wpbs_service_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['wpbs_service_nonce'] ), 'wpbs_service_action' ) ) {
			$this->handle_service_actions();
		}

		if ( ! empty( $_GET['wpbs_msg'] ) ) {
			$notices = array(
				'saved'        => array( 'success', __( 'Service saved successfully.', 'wp-booking-system' ) ),
				'deleted'      => array( 'success', __( 'Service deleted.', 'wp-booking-system' ) ),
				'save_failed'  => array( 'error',   __( 'Could not save service. Please try again.', 'wp-booking-system' ) ),
				'delete_failed' => array( 'error',  __( 'Cannot delete a service with active bookings.', 'wp-booking-system' ) ),
			);
			$key = sanitize_key( $_GET['wpbs_msg'] );
			if ( isset( $notices[ $key ] ) ) {
				[ $type, $text ] = $notices[ $key ];
				add_settings_error( 'wpbs_services', $key, $text, $type );
			}
		}

		settings_errors( 'wpbs_services' );

		$action   = sanitize_text_field( $_GET['action'] ?? 'list' );
		$service  = null;
		$services = array();

		if ( 'edit' === $action && ! empty( $_GET['id'] ) ) {
			$service = WPBS_Service::get( absint( $_GET['id'] ) );
		} elseif ( 'list' === $action ) {
			$services = WPBS_Service::get_all( array( 'is_active' => '' ) );
		}

		include WPBS_PLUGIN_DIR . 'admin/partials/admin-services.php';
	}

	public function render_calendar(): void {
		$this->verify_admin_access();
		$services = WPBS_Service::get_all();
		include WPBS_PLUGIN_DIR . 'admin/partials/admin-calendar.php';
	}

	public function render_payments(): void {
		$this->verify_admin_access();

		if ( isset( $_POST['wpbs_refund_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['wpbs_refund_nonce'] ), 'wpbs_refund_action' ) ) {
			$payment_id = absint( $_POST['payment_id'] ?? 0 );
			$amount     = (float) ( $_POST['refund_amount'] ?? 0 );
			if ( $payment_id && $amount > 0 ) {
				WPBS_Payment::process_refund( $payment_id, $amount );
			}
		}

		$filters  = array(
			'gateway'   => sanitize_text_field( $_GET['gateway'] ?? '' ),
			'status'    => sanitize_text_field( $_GET['status'] ?? '' ),
			'date_from' => sanitize_text_field( $_GET['date_from'] ?? '' ),
			'date_to'   => sanitize_text_field( $_GET['date_to'] ?? '' ),
			'limit'     => 20,
			'offset'    => ( absint( $_GET['paged'] ?? 1 ) - 1 ) * 20,
		);
		$payments = WPBS_Payment::get_all( $filters );
		$summary  = WPBS_Payment::get_summary( $filters['date_from'], $filters['date_to'] );
		include WPBS_PLUGIN_DIR . 'admin/partials/admin-payments.php';
	}

	public function render_reports(): void {
		$this->verify_admin_access();

		$date_from = sanitize_text_field( $_GET['date_from'] ?? date( 'Y-m-01' ) );
		$date_to   = sanitize_text_field( $_GET['date_to'] ?? date( 'Y-m-d' ) );
		$stats     = WPBS_Booking::get_revenue_stats( $date_from, $date_to );
		$by_service = $this->get_bookings_by_service( $date_from, $date_to );
		$daily_data = $this->get_daily_bookings( $date_from, $date_to );

		include WPBS_PLUGIN_DIR . 'admin/partials/admin-reports.php';
	}

	public function render_settings(): void {
		$this->verify_admin_access();

		if ( isset( $_POST['wpbs_settings_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['wpbs_settings_nonce'] ), 'wpbs_settings_save' ) ) {
			$this->save_settings();
			add_settings_error( 'wpbs_settings', 'saved', __( 'Settings saved.', 'wp-booking-system' ), 'success' );
		}

		$active_tab = sanitize_text_field( $_GET['tab'] ?? 'general' );
		include WPBS_PLUGIN_DIR . 'admin/partials/admin-settings.php';
	}

	public function handle_ajax(): void {
		check_ajax_referer( 'wpbs_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'wp-booking-system' ) ), 403 );
		}

		$action = sanitize_text_field( $_POST['sub_action'] ?? '' );

		switch ( $action ) {
			case 'get_calendar_events':
				$this->ajax_get_calendar_events();
				break;
			case 'update_booking_status':
				$this->ajax_update_booking_status();
				break;
			default:
				wp_send_json_error( array( 'message' => 'Unknown action' ), 400 );
		}
	}

	private function ajax_get_calendar_events(): void {
		$service_id = absint( $_POST['service_id'] ?? 0 );
		$start      = sanitize_text_field( $_POST['start'] ?? '' );
		$end        = sanitize_text_field( $_POST['end'] ?? '' );

		$bookings = WPBS_Booking::get_all( array(
			'service_id' => $service_id,
			'date_from'  => $start,
			'date_to'    => $end,
			'limit'      => 500,
		) );

		$events = array();
		foreach ( $bookings as $b ) {
			$color = match ( $b['booking_status'] ) {
				'confirmed' => '#28a745',
				'pending'   => '#ffc107',
				'cancelled' => '#dc3545',
				'completed' => '#6c757d',
				default     => '#007bff',
			};
			$events[] = array(
				'id'    => $b['id'],
				'title' => $b['service_name'] . ' – ' . $b['customer_name'],
				'start' => $b['booking_date'] . 'T' . $b['booking_time'],
				'end'   => $b['booking_date'] . 'T' . $b['end_time'],
				'color' => $color,
				'extendedProps' => array(
					'booking_ref' => $b['booking_ref'],
					'status'      => $b['booking_status'],
					'customer'    => $b['customer_name'],
					'amount'      => $b['amount'],
				),
			);
		}

		wp_send_json_success( $events );
	}

	private function ajax_update_booking_status(): void {
		$id     = absint( $_POST['booking_id'] ?? 0 );
		$status = sanitize_text_field( $_POST['status'] ?? '' );
		$notes  = sanitize_textarea_field( $_POST['notes'] ?? '' );

		$updated = WPBS_Booking::update_status( $id, $status, $notes );
		if ( $updated ) {
			wp_send_json_success( WPBS_Booking::get( $id ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Update failed.', 'wp-booking-system' ) ) );
		}
	}

	private function handle_booking_actions(): void {
		$action = sanitize_text_field( $_POST['action'] ?? '' );
		$ids    = array_map( 'absint', (array) ( $_POST['booking_ids'] ?? array() ) );

		if ( empty( $ids ) && ! empty( $_POST['booking_id'] ) ) {
			$ids = array( absint( $_POST['booking_id'] ) );
		}

		foreach ( $ids as $id ) {
			switch ( $action ) {
				case 'confirm':
					WPBS_Booking::update_status( $id, 'confirmed' );
					break;
				case 'cancel':
					WPBS_Booking::update_status( $id, 'cancelled' );
					$booking = WPBS_Booking::get( $id );
					if ( $booking ) {
						WPBS_Email::send_cancellation( $booking );
					}
					break;
				case 'delete':
					WPBS_Booking::delete( $id );
					break;
			}
		}
	}

	private function handle_service_actions(): void {
		$action = sanitize_text_field( $_POST['service_action'] ?? '' );

		if ( 'save' === $action ) {
			$id   = absint( $_POST['service_id'] ?? 0 );
			$data = array(
				'name'        => sanitize_text_field( $_POST['name'] ?? '' ),
				'description' => wp_kses_post( $_POST['description'] ?? '' ),
				'duration'    => absint( $_POST['duration'] ?? 60 ),
				'capacity'    => absint( $_POST['capacity'] ?? 1 ),
				'price'       => (float) ( $_POST['price'] ?? 0 ),
				'category'    => sanitize_text_field( $_POST['category'] ?? '' ),
				'is_active'   => absint( $_POST['is_active'] ?? 1 ),
				'sort_order'  => absint( $_POST['sort_order'] ?? 0 ),
			);

			if ( $id ) {
				$ok = WPBS_Service::update( $id, $data );
			} else {
				$new_id = WPBS_Service::create( $data );
				$ok     = (bool) $new_id;
				if ( $ok ) {
					$id = $new_id;
				}
			}

			$msg = $ok ? 'saved' : 'save_failed';
			wp_safe_redirect( admin_url( 'admin.php?page=wpbs-services&wpbs_msg=' . $msg ) );
			exit;

		} elseif ( 'delete' === $action ) {
			$id = absint( $_POST['service_id'] ?? 0 );
			$ok = WPBS_Service::delete( $id );
			$msg = $ok ? 'deleted' : 'delete_failed';
			wp_safe_redirect( admin_url( 'admin.php?page=wpbs-services&wpbs_msg=' . $msg ) );
			exit;
		}
	}

	private function save_settings(): void {
		$tab = sanitize_text_field( $_POST['tab'] ?? 'general' );

		switch ( $tab ) {
			case 'general':
				update_option( 'wpbs_timezone', sanitize_text_field( $_POST['timezone'] ?? 'UTC' ) );
				update_option( 'wpbs_currency', sanitize_text_field( $_POST['currency'] ?? 'USD' ) );
				update_option( 'wpbs_currency_symbol', sanitize_text_field( $_POST['currency_symbol'] ?? '$' ) );
				update_option( 'wpbs_admin_email', sanitize_email( $_POST['admin_email'] ?? '' ) );
				update_option( 'wpbs_from_name', sanitize_text_field( $_POST['from_name'] ?? '' ) );
				update_option( 'wpbs_from_email', sanitize_email( $_POST['from_email'] ?? '' ) );
				break;

			case 'hours':
				$days  = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
				$hours = array();
				foreach ( $days as $day ) {
					$hours[ $day ] = array(
						'enabled' => isset( $_POST["hours_{$day}_enabled"] ),
						'open'    => sanitize_text_field( $_POST["hours_{$day}_open"] ?? '09:00' ),
						'close'   => sanitize_text_field( $_POST["hours_{$day}_close"] ?? '17:00' ),
					);
				}
				update_option( 'wpbs_business_hours', $hours );
				break;

			case 'booking':
				update_option( 'wpbs_booking_rules', array(
					'min_advance_hours'     => absint( $_POST['min_advance_hours'] ?? 2 ),
					'max_advance_days'      => absint( $_POST['max_advance_days'] ?? 60 ),
					'cancellation_hours'    => absint( $_POST['cancellation_hours'] ?? 24 ),
					'slot_interval_minutes' => absint( $_POST['slot_interval_minutes'] ?? 30 ),
				) );
				break;

			case 'paypal':
				update_option( 'wpbs_paypal_mode', sanitize_text_field( $_POST['paypal_mode'] ?? 'sandbox' ) );
				update_option( 'wpbs_paypal_client_id', sanitize_text_field( $_POST['paypal_client_id'] ?? '' ) );
				update_option( 'wpbs_paypal_secret', sanitize_text_field( $_POST['paypal_secret'] ?? '' ) );
				update_option( 'wpbs_paypal_webhook_id', sanitize_text_field( $_POST['paypal_webhook_id'] ?? '' ) );
				// Clear cached token when credentials change
				delete_transient( 'wpbs_paypal_token_' . md5( get_option( 'wpbs_paypal_client_id', '' ) ) );
				break;

			case 'stripe':
				update_option( 'wpbs_stripe_mode', sanitize_text_field( $_POST['stripe_mode'] ?? 'test' ) );
				update_option( 'wpbs_stripe_publishable_key', sanitize_text_field( $_POST['stripe_publishable_key'] ?? '' ) );
				update_option( 'wpbs_stripe_secret_key', sanitize_text_field( $_POST['stripe_secret_key'] ?? '' ) );
				update_option( 'wpbs_stripe_webhook_secret', sanitize_text_field( $_POST['stripe_webhook_secret'] ?? '' ) );
				break;
		}
	}

	private function export_bookings_csv(): void {
		$bookings = WPBS_Booking::get_all( array( 'limit' => 9999 ) );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="bookings-' . date( 'Y-m-d' ) . '.csv"' );
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'ID', 'Ref', 'Customer', 'Service', 'Date', 'Time', 'Amount', 'Payment', 'Status', 'Created' ) );
		foreach ( $bookings as $b ) {
			fputcsv( $out, array(
				$b['id'],
				$b['booking_ref'],
				$b['customer_name'],
				$b['service_name'],
				$b['booking_date'],
				$b['booking_time'],
				$b['amount'],
				$b['payment_status'],
				$b['booking_status'],
				$b['created_at'],
			) );
		}
		fclose( $out );
	}

	private function get_bookings_by_service( string $from, string $to ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.name AS service_name, COUNT(b.id) AS count, SUM(b.amount) AS revenue
				 FROM {$wpdb->prefix}bookings b
				 LEFT JOIN {$wpdb->prefix}booking_services s ON b.service_id = s.id
				 WHERE b.booking_date BETWEEN %s AND %s
				   AND b.payment_status = 'paid'
				 GROUP BY b.service_id",
				$from,
				$to
			),
			ARRAY_A
		) ?: array();
	}

	private function get_daily_bookings( string $from, string $to ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT booking_date, COUNT(*) AS count, SUM(amount) AS revenue
				 FROM {$wpdb->prefix}bookings
				 WHERE booking_date BETWEEN %s AND %s
				   AND payment_status = 'paid'
				 GROUP BY booking_date
				 ORDER BY booking_date ASC",
				$from,
				$to
			),
			ARRAY_A
		) ?: array();
	}

	private function verify_admin_access(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-booking-system' ) );
		}
	}
}

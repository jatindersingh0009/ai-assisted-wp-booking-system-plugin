<?php
defined( 'ABSPATH' ) || exit;

class WPBS_Public {

	public function enqueue_scripts(): void {
		if ( ! $this->is_wpbs_page() ) {
			return;
		}

		wp_enqueue_style( 'wpbs-public', WPBS_PLUGIN_URL . 'public/css/public.css', array(), WPBS_VERSION );
		wp_enqueue_script( 'wpbs-public', WPBS_PLUGIN_URL . 'public/js/public.js', array( 'jquery' ), WPBS_VERSION, true );

		wp_localize_script( 'wpbs-public', 'wpbsData', array(
			'restUrl'     => esc_url_raw( rest_url( 'wp-booking-system/v1/' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'publicNonce' => wp_create_nonce( 'wpbs_public_nonce' ),
			'isLoggedIn'  => is_user_logged_in(),
			'loginUrl'    => wp_login_url( get_permalink() ),
			'currency'    => get_option( 'wpbs_currency_symbol', '$' ),
			'i18n'        => array(
				'select_service'    => __( 'Please select a service.', 'wp-booking-system' ),
				'select_date'       => __( 'Please select a date.', 'wp-booking-system' ),
				'select_time'       => __( 'Please select a time slot.', 'wp-booking-system' ),
				'booking_success'   => __( 'Booking created successfully!', 'wp-booking-system' ),
				'booking_error'     => __( 'Booking failed. Please try again.', 'wp-booking-system' ),
				'payment_success'   => __( 'Payment successful! Your booking is confirmed.', 'wp-booking-system' ),
				'payment_error'     => __( 'Payment failed. Please try again.', 'wp-booking-system' ),
				'loading'           => __( 'Loading...', 'wp-booking-system' ),
				'confirm_cancel'    => __( 'Are you sure you want to cancel this booking?', 'wp-booking-system' ),
				'no_slots'          => __( 'No available slots for this date.', 'wp-booking-system' ),
			),
		) );

		// Stripe.js
		$stripe_key = get_option( 'wpbs_stripe_publishable_key', '' );
		if ( $stripe_key ) {
			wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true );
		}
	}

	public function render_booking_form( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return '<p class="wpbs-login-notice">' .
				sprintf(
					/* translators: %s: login URL */
					wp_kses( __( 'Please <a href="%s">log in</a> to book an appointment.', 'wp-booking-system' ), array( 'a' => array( 'href' => array() ) ) ),
					esc_url( wp_login_url( get_permalink() ) )
				) .
				'</p>';
		}

		$services = WPBS_Service::get_all( array( 'is_active' => 1 ) );
		ob_start();
		include WPBS_PLUGIN_DIR . 'public/partials/booking-form.php';
		return ob_get_clean();
	}

	public function render_customer_dashboard( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return '<p class="wpbs-login-notice">' .
				sprintf(
					wp_kses( __( 'Please <a href="%s">log in</a> to view your bookings.', 'wp-booking-system' ), array( 'a' => array( 'href' => array() ) ) ),
					esc_url( wp_login_url( get_permalink() ) )
				) .
				'</p>';
		}

		$user_id  = get_current_user_id();
		$tab      = sanitize_text_field( $_GET['tab'] ?? 'upcoming' );
		$bookings = WPBS_Booking::get_all( array(
			'customer_id'    => $user_id,
			'booking_status' => 'upcoming' === $tab ? 'confirmed' : '',
			'orderby'        => 'upcoming' === $tab ? 'booking_date' : 'created_at',
			'order'          => 'upcoming' === $tab ? 'ASC' : 'DESC',
			'limit'          => 20,
		) );

		ob_start();
		include WPBS_PLUGIN_DIR . 'public/partials/customer-dashboard.php';
		return ob_get_clean();
	}

	public function render_booking_confirmation( array $atts = array() ): string {
		$booking_id  = absint( $_GET['booking_id'] ?? 0 );
		$booking_ref = sanitize_text_field( $_GET['booking_ref'] ?? '' );
		$booking     = null;

		if ( $booking_id ) {
			$booking = WPBS_Booking::get( $booking_id );
		} elseif ( $booking_ref ) {
			$booking = WPBS_Booking::get_by_ref( $booking_ref );
		}

		// Security: only owner or admin can view
		if ( $booking && is_user_logged_in() ) {
			if ( (int) $booking['customer_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
				$booking = null;
			}
		}

		ob_start();
		include WPBS_PLUGIN_DIR . 'public/partials/booking-confirmation.php';
		return ob_get_clean();
	}

	public function handle_ajax(): void {
		check_ajax_referer( 'wpbs_public_nonce', 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wp-booking-system' ) ), 401 );
		}
		$this->dispatch_ajax();
	}

	public function handle_ajax_nopriv(): void {
		$action = sanitize_text_field( $_POST['sub_action'] ?? '' );
		// Only non-auth actions allowed without login
		if ( 'get_slots' === $action ) {
			check_ajax_referer( 'wpbs_public_nonce', 'nonce' );
			$this->ajax_get_slots();
		} else {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'wp-booking-system' ) ), 401 );
		}
	}

	private function dispatch_ajax(): void {
		$action = sanitize_text_field( $_POST['sub_action'] ?? '' );
		switch ( $action ) {
			case 'get_slots':
				$this->ajax_get_slots();
				break;
			case 'create_booking':
				$this->ajax_create_booking();
				break;
			case 'cancel_booking':
				$this->ajax_cancel_booking();
				break;
			case 'get_invoice':
				$this->ajax_get_invoice();
				break;
			default:
				wp_send_json_error( array( 'message' => 'Unknown action' ), 400 );
		}
	}

	private function ajax_get_slots(): void {
		$service_id = absint( $_POST['service_id'] ?? 0 );
		$date       = sanitize_text_field( $_POST['date'] ?? '' );
		$slots      = WPBS_Availability::get_available_slots( $service_id, $date );
		wp_send_json_success( array( 'slots' => $slots ) );
	}

	private function ajax_create_booking(): void {
		$data = array(
			'customer_id'    => get_current_user_id(),
			'service_id'     => absint( $_POST['service_id'] ?? 0 ),
			'booking_date'   => sanitize_text_field( $_POST['booking_date'] ?? '' ),
			'booking_time'   => sanitize_text_field( $_POST['booking_time'] ?? '' ),
			'payment_method' => sanitize_text_field( $_POST['payment_method'] ?? '' ),
			'notes'          => sanitize_textarea_field( $_POST['notes'] ?? '' ),
		);

		$result = WPBS_Booking::create( $data );

		if ( ! $result['success'] ) {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}

		wp_send_json_success( array(
			'booking_id'  => $result['booking_id'],
			'booking_ref' => $result['booking']['booking_ref'],
			'amount'      => $result['booking']['amount'],
		) );
	}

	private function ajax_cancel_booking(): void {
		$id     = absint( $_POST['booking_id'] ?? 0 );
		$booking = WPBS_Booking::get( $id );

		if ( ! $booking ) {
			wp_send_json_error( array( 'message' => __( 'Booking not found.', 'wp-booking-system' ) ) );
		}

		if ( (int) $booking['customer_id'] !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'wp-booking-system' ) ), 403 );
		}

		if ( ! WPBS_Booking::can_cancel( $booking ) ) {
			wp_send_json_error( array( 'message' => __( 'This booking cannot be cancelled.', 'wp-booking-system' ) ) );
		}

		WPBS_Booking::update_status( $id, 'cancelled' );
		WPBS_Email::send_cancellation( $booking );
		wp_send_json_success( array( 'message' => __( 'Booking cancelled.', 'wp-booking-system' ) ) );
	}

	private function ajax_get_invoice(): void {
		$id      = absint( $_POST['booking_id'] ?? 0 );
		$booking = WPBS_Booking::get( $id );

		if ( ! $booking || (int) $booking['customer_id'] !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Not found.', 'wp-booking-system' ) ) );
		}

		$payment = WPBS_Payment::get_by_booking( $id );
		wp_send_json_success( array( 'booking' => $booking, 'payment' => $payment ) );
	}

	private function is_wpbs_page(): bool {
		$page_ids = array(
			get_option( 'wpbs_booking_page' ),
			get_option( 'wpbs_customer_dashboard' ),
			get_option( 'wpbs_booking_confirmation' ),
		);
		return is_page( array_filter( $page_ids ) ) || has_shortcode( get_the_content(), 'wpbs_booking_form' ) || has_shortcode( get_the_content(), 'wpbs_customer_dashboard' );
	}
}

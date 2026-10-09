<?php
defined( 'ABSPATH' ) || exit;

class WPBS_REST_API {

	const NAMESPACE = 'wp-booking-system/v1';

	public function register_routes(): void {
		// Services
		register_rest_route( self::NAMESPACE, '/services', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_services' ), 'permission_callback' => '__return_true' ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_service' ), 'permission_callback' => array( $this, 'admin_permission' ) ),
		) );
		register_rest_route( self::NAMESPACE, '/services/(?P<id>\d+)', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_service' ), 'permission_callback' => '__return_true' ),
			array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_service' ), 'permission_callback' => array( $this, 'admin_permission' ) ),
			array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_service' ), 'permission_callback' => array( $this, 'admin_permission' ) ),
		) );

		// Availability
		register_rest_route( self::NAMESPACE, '/availability', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_availability' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'service_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
				'date'       => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );
		register_rest_route( self::NAMESPACE, '/available-dates', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_available_dates' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'service_id' => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
				'month'      => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
				'year'       => array( 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ),
			),
		) );

		// Bookings
		register_rest_route( self::NAMESPACE, '/bookings', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_bookings' ), 'permission_callback' => array( $this, 'admin_permission' ) ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( $this, 'create_booking' ), 'permission_callback' => array( $this, 'customer_permission' ) ),
		) );
		register_rest_route( self::NAMESPACE, '/bookings/(?P<id>\d+)', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( $this, 'get_booking' ), 'permission_callback' => array( $this, 'booking_permission' ) ),
			array( 'methods' => WP_REST_Server::EDITABLE, 'callback' => array( $this, 'update_booking' ), 'permission_callback' => array( $this, 'admin_permission' ) ),
			array( 'methods' => WP_REST_Server::DELETABLE, 'callback' => array( $this, 'delete_booking' ), 'permission_callback' => array( $this, 'admin_permission' ) ),
		) );
		register_rest_route( self::NAMESPACE, '/bookings/(?P<id>\d+)/cancel', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'cancel_booking' ),
			'permission_callback' => array( $this, 'booking_permission' ),
		) );

		// Payments
		register_rest_route( self::NAMESPACE, '/payments/paypal/create-order', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'paypal_create_order' ),
			'permission_callback' => array( $this, 'customer_permission' ),
		) );
		register_rest_route( self::NAMESPACE, '/payments/paypal/capture', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'paypal_capture_order' ),
			'permission_callback' => array( $this, 'customer_permission' ),
		) );
		register_rest_route( self::NAMESPACE, '/payments/stripe/create-intent', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'stripe_create_intent' ),
			'permission_callback' => array( $this, 'customer_permission' ),
		) );
		register_rest_route( self::NAMESPACE, '/payments/stripe/confirm', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'stripe_confirm_payment' ),
			'permission_callback' => array( $this, 'customer_permission' ),
		) );
		register_rest_route( self::NAMESPACE, '/payments/(?P<id>\d+)/refund', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'process_refund' ),
			'permission_callback' => array( $this, 'admin_permission' ),
		) );

		// Customer bookings
		register_rest_route( self::NAMESPACE, '/my-bookings', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_my_bookings' ),
			'permission_callback' => array( $this, 'customer_permission' ),
		) );
	}

	// ===== Services =====

	public function get_services( WP_REST_Request $request ): WP_REST_Response {
		$services = WPBS_Service::get_all( array( 'is_active' => 1 ) );
		return rest_ensure_response( $services );
	}

	public function get_service( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$service = WPBS_Service::get( (int) $request['id'] );
		if ( ! $service ) {
			return new WP_Error( 'not_found', __( 'Service not found.', 'wp-booking-system' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $service );
	}

	public function create_service( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = WPBS_Service::create( $request->get_params() );
		if ( ! $id ) {
			return new WP_Error( 'create_failed', __( 'Failed to create service.', 'wp-booking-system' ), array( 'status' => 500 ) );
		}
		return new WP_REST_Response( WPBS_Service::get( $id ), 201 );
	}

	public function update_service( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$updated = WPBS_Service::update( (int) $request['id'], $request->get_params() );
		if ( ! $updated ) {
			return new WP_Error( 'update_failed', __( 'Update failed.', 'wp-booking-system' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( WPBS_Service::get( (int) $request['id'] ) );
	}

	public function delete_service( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$deleted = WPBS_Service::delete( (int) $request['id'] );
		if ( ! $deleted ) {
			return new WP_Error( 'delete_failed', __( 'Cannot delete service with active bookings.', 'wp-booking-system' ), array( 'status' => 409 ) );
		}
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	// ===== Availability =====

	public function get_availability( WP_REST_Request $request ): WP_REST_Response {
		$slots = WPBS_Availability::get_available_slots(
			(int) $request->get_param( 'service_id' ),
			$request->get_param( 'date' )
		);
		return rest_ensure_response( array( 'slots' => $slots ) );
	}

	public function get_available_dates( WP_REST_Request $request ): WP_REST_Response {
		$dates = WPBS_Availability::get_available_dates(
			(int) $request->get_param( 'service_id' ),
			(int) $request->get_param( 'month' ),
			(int) $request->get_param( 'year' )
		);
		return rest_ensure_response( array( 'dates' => $dates ) );
	}

	// ===== Bookings =====

	public function get_bookings( WP_REST_Request $request ): WP_REST_Response {
		$args = array(
			'booking_status' => sanitize_text_field( $request->get_param( 'status' ) ?? '' ),
			'date_from'      => sanitize_text_field( $request->get_param( 'date_from' ) ?? '' ),
			'date_to'        => sanitize_text_field( $request->get_param( 'date_to' ) ?? '' ),
			'search'         => sanitize_text_field( $request->get_param( 'search' ) ?? '' ),
			'limit'          => absint( $request->get_param( 'per_page' ) ?? 20 ),
			'offset'         => absint( $request->get_param( 'offset' ) ?? 0 ),
		);
		$bookings = WPBS_Booking::get_all( $args );
		$total    = WPBS_Booking::count( $args );

		$response = new WP_REST_Response( $bookings, 200 );
		$response->header( 'X-WP-Total', $total );
		return $response;
	}

	public function get_booking( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$booking = WPBS_Booking::get( (int) $request['id'] );
		if ( ! $booking ) {
			return new WP_Error( 'not_found', __( 'Booking not found.', 'wp-booking-system' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $booking );
	}

	public function create_booking( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_params();
		$params['customer_id'] = get_current_user_id();

		$result = WPBS_Booking::create( $params );

		if ( ! $result['success'] ) {
			return new WP_Error( 'booking_failed', $result['message'], array( 'status' => 422 ) );
		}

		return new WP_REST_Response( $result['booking'], 201 );
	}

	public function update_booking( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id     = (int) $request['id'];
		$status = sanitize_text_field( $request->get_param( 'booking_status' ) ?? '' );
		$notes  = sanitize_textarea_field( $request->get_param( 'admin_notes' ) ?? '' );

		if ( $status ) {
			WPBS_Booking::update_status( $id, $status, $notes );
		}

		$booking = WPBS_Booking::get( $id );
		return rest_ensure_response( $booking );
	}

	public function cancel_booking( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id      = (int) $request['id'];
		$booking = WPBS_Booking::get( $id );

		if ( ! $booking ) {
			return new WP_Error( 'not_found', __( 'Booking not found.', 'wp-booking-system' ), array( 'status' => 404 ) );
		}

		if ( ! current_user_can( 'manage_options' ) && ! WPBS_Booking::can_cancel( $booking ) ) {
			return new WP_Error( 'cannot_cancel', __( 'This booking cannot be cancelled.', 'wp-booking-system' ), array( 'status' => 403 ) );
		}

		$reason = sanitize_textarea_field( $request->get_param( 'reason' ) ?? '' );
		WPBS_Booking::update_status( $id, 'cancelled', $reason );
		WPBS_Email::send_cancellation( $booking, $reason );

		return rest_ensure_response( WPBS_Booking::get( $id ) );
	}

	public function delete_booking( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$deleted = WPBS_Booking::delete( (int) $request['id'] );
		if ( ! $deleted ) {
			return new WP_Error( 'delete_failed', __( 'Delete failed.', 'wp-booking-system' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	// ===== Payments =====

	public function paypal_create_order( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$booking_id = absint( $request->get_param( 'booking_id' ) );
		$booking    = WPBS_Booking::get( $booking_id );
		if ( ! $booking ) {
			return new WP_Error( 'not_found', __( 'Booking not found.', 'wp-booking-system' ), array( 'status' => 404 ) );
		}

		$gateway = new WPBS_PayPal_Gateway();
		$result  = $gateway->create_order( $booking );

		if ( ! $result['success'] ) {
			return new WP_Error( 'paypal_error', $result['message'], array( 'status' => 422 ) );
		}

		return rest_ensure_response( $result );
	}

	public function paypal_capture_order( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$order_id   = sanitize_text_field( $request->get_param( 'order_id' ) );
		$booking_id = absint( $request->get_param( 'booking_id' ) );

		$gateway = new WPBS_PayPal_Gateway();
		$result  = $gateway->capture_order( $order_id, $booking_id );

		if ( ! $result['success'] ) {
			return new WP_Error( 'paypal_error', $result['message'], array( 'status' => 422 ) );
		}

		return rest_ensure_response( $result );
	}

	public function stripe_create_intent( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$booking_id = absint( $request->get_param( 'booking_id' ) );
		$booking    = WPBS_Booking::get( $booking_id );
		if ( ! $booking ) {
			return new WP_Error( 'not_found', __( 'Booking not found.', 'wp-booking-system' ), array( 'status' => 404 ) );
		}

		$gateway = new WPBS_Stripe_Gateway();
		$result  = $gateway->create_payment_intent( $booking );

		if ( ! $result['success'] ) {
			return new WP_Error( 'stripe_error', $result['message'], array( 'status' => 422 ) );
		}

		return rest_ensure_response( $result );
	}

	public function stripe_confirm_payment( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$pi_id      = sanitize_text_field( $request->get_param( 'payment_intent_id' ) );
		$booking_id = absint( $request->get_param( 'booking_id' ) );

		$gateway = new WPBS_Stripe_Gateway();
		$result  = $gateway->confirm_payment( $pi_id, $booking_id );

		if ( ! $result['success'] ) {
			return new WP_Error( 'stripe_error', $result['message'], array( 'status' => 422 ) );
		}

		return rest_ensure_response( $result );
	}

	public function process_refund( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$amount = (float) $request->get_param( 'amount' );
		$result = WPBS_Payment::process_refund( (int) $request['id'], $amount );

		if ( ! $result['success'] ) {
			return new WP_Error( 'refund_failed', $result['message'], array( 'status' => 422 ) );
		}

		return rest_ensure_response( $result );
	}

	public function get_my_bookings( WP_REST_Request $request ): WP_REST_Response {
		$bookings = WPBS_Booking::get_all( array(
			'customer_id'    => get_current_user_id(),
			'booking_status' => sanitize_text_field( $request->get_param( 'status' ) ?? '' ),
			'limit'          => absint( $request->get_param( 'per_page' ) ?? 10 ),
			'offset'         => absint( $request->get_param( 'offset' ) ?? 0 ),
		) );
		return rest_ensure_response( $bookings );
	}

	// ===== Permissions =====

	public function admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function customer_permission(): bool {
		return is_user_logged_in();
	}

	public function booking_permission( WP_REST_Request $request ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$booking = WPBS_Booking::get( (int) $request['id'] );
		return $booking && (int) $booking['customer_id'] === get_current_user_id();
	}
}

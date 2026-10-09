<?php
defined( 'ABSPATH' ) || exit;

class WPBS_PayPal_Gateway {

	private string $client_id;
	private string $secret;
	private string $mode;
	private string $api_base;

	public function __construct() {
		$this->mode      = get_option( 'wpbs_paypal_mode', 'sandbox' );
		$this->client_id = get_option( 'wpbs_paypal_client_id', '' );
		$this->secret    = get_option( 'wpbs_paypal_secret', '' );
		$this->api_base  = 'sandbox' === $this->mode
			? 'https://api-m.sandbox.paypal.com'
			: 'https://api-m.paypal.com';
	}

	/**
	 * Create a PayPal order and return the approval URL.
	 */
	public function create_order( array $booking ): array {
		$token = $this->get_access_token();
		if ( ! $token ) {
			return array( 'success' => false, 'message' => __( 'PayPal authentication failed.', 'wp-booking-system' ) );
		}

		$currency = strtoupper( get_option( 'wpbs_currency', 'USD' ) );
		$amount   = number_format( (float) $booking['amount'], 2, '.', '' );

		$payload = array(
			'intent'         => 'CAPTURE',
			'purchase_units' => array(
				array(
					'reference_id' => $booking['booking_ref'],
					'description'  => sprintf(
						/* translators: 1: service name, 2: booking reference */
						__( 'Booking: %1$s (%2$s)', 'wp-booking-system' ),
						$booking['service_name'] ?? '',
						$booking['booking_ref']
					),
					'amount'       => array(
						'currency_code' => $currency,
						'value'         => $amount,
					),
				),
			),
			'application_context' => array(
				'return_url' => add_query_arg(
					array( 'wpbs_paypal' => 'success', 'booking_id' => $booking['id'] ),
					home_url( '/' )
				),
				'cancel_url' => add_query_arg(
					array( 'wpbs_paypal' => 'cancel', 'booking_id' => $booking['id'] ),
					home_url( '/' )
				),
				'brand_name'          => get_bloginfo( 'name' ),
				'user_action'         => 'PAY_NOW',
				'shipping_preference' => 'NO_SHIPPING',
			),
		);

		$response = wp_remote_post(
			$this->api_base . '/v2/checkout/orders',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['id'] ) ) {
			return array( 'success' => false, 'message' => $body['message'] ?? __( 'PayPal order creation failed.', 'wp-booking-system' ) );
		}

		$approval_url = '';
		foreach ( $body['links'] ?? array() as $link ) {
			if ( 'approve' === $link['rel'] ) {
				$approval_url = $link['href'];
				break;
			}
		}

		// Record pending payment
		WPBS_Payment::record( array(
			'booking_id'       => $booking['id'],
			'transaction_id'   => $body['id'],
			'gateway'          => 'paypal',
			'amount'           => (float) $booking['amount'],
			'currency'         => $currency,
			'status'           => 'pending',
			'gateway_response' => $body,
		) );

		return array(
			'success'      => true,
			'order_id'     => $body['id'],
			'approval_url' => $approval_url,
		);
	}

	/**
	 * Capture a PayPal order after customer approval.
	 */
	public function capture_order( string $order_id, int $booking_id ): array {
		$token = $this->get_access_token();
		if ( ! $token ) {
			return array( 'success' => false, 'message' => __( 'PayPal authentication failed.', 'wp-booking-system' ) );
		}

		$response = wp_remote_post(
			$this->api_base . '/v2/checkout/orders/' . $order_id . '/capture',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => '{}',
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 'COMPLETED' !== ( $body['status'] ?? '' ) ) {
			return array( 'success' => false, 'message' => $body['message'] ?? __( 'PayPal capture failed.', 'wp-booking-system' ) );
		}

		$capture = $body['purchase_units'][0]['payments']['captures'][0] ?? array();

		// Update payment record
		global $wpdb;
		$payment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}booking_payments WHERE transaction_id = %s AND gateway = 'paypal'",
				$order_id
			)
		);

		if ( $payment_id ) {
			WPBS_Payment::update( (int) $payment_id, array(
				'transaction_id'   => $capture['id'] ?? $order_id,
				'status'           => 'completed',
				'gateway_response' => $body,
			) );
		}

		WPBS_Booking::update_payment_status( $booking_id, 'paid' );
		WPBS_Booking::update_status( $booking_id, 'confirmed' );

		$booking = WPBS_Booking::get( $booking_id );
		$payment = WPBS_Payment::get_by_booking( $booking_id );

		do_action( 'wpbs_payment_completed', $booking, $payment );

		return array( 'success' => true, 'capture_id' => $capture['id'] ?? '' );
	}

	/**
	 * Refund a captured PayPal payment.
	 */
	public function refund( string $capture_id, float $amount, string $currency ): array {
		$token = $this->get_access_token();
		if ( ! $token ) {
			return array( 'success' => false, 'message' => __( 'PayPal authentication failed.', 'wp-booking-system' ) );
		}

		$payload  = array(
			'amount' => array(
				'currency_code' => strtoupper( $currency ),
				'value'         => number_format( $amount, 2, '.', '' ),
			),
		);

		$response = wp_remote_post(
			$this->api_base . '/v2/payments/captures/' . $capture_id . '/refund',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 'COMPLETED' !== ( $body['status'] ?? '' ) ) {
			return array( 'success' => false, 'message' => $body['message'] ?? __( 'Refund failed.', 'wp-booking-system' ) );
		}

		return array( 'success' => true, 'refund_id' => $body['id'] );
	}

	/**
	 * Handle PayPal webhook events.
	 */
	public function handle_webhook(): void {
		$raw_body = file_get_contents( 'php://input' );
		$event    = json_decode( $raw_body, true );

		if ( empty( $event['event_type'] ) ) {
			wp_die( 'Invalid webhook payload', 400 );
		}

		// Verify webhook signature (requires PayPal SDK or manual verification)
		if ( ! $this->verify_webhook( $raw_body ) ) {
			wp_die( 'Webhook verification failed', 401 );
		}

		$event_type = $event['event_type'];
		$resource   = $event['resource'] ?? array();

		switch ( $event_type ) {
			case 'PAYMENT.CAPTURE.COMPLETED':
				$this->handle_capture_completed( $resource );
				break;
			case 'PAYMENT.CAPTURE.REFUNDED':
				$this->handle_capture_refunded( $resource );
				break;
		}

		status_header( 200 );
		exit;
	}

	private function handle_capture_completed( array $resource ): void {
		global $wpdb;
		$capture_id = $resource['id'] ?? '';
		if ( ! $capture_id ) {
			return;
		}

		$payment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}booking_payments WHERE transaction_id = %s",
				$capture_id
			),
			ARRAY_A
		);

		if ( $payment && 'completed' !== $payment['status'] ) {
			WPBS_Payment::update( (int) $payment['id'], array( 'status' => 'completed' ) );
			WPBS_Booking::update_payment_status( (int) $payment['booking_id'], 'paid' );
			WPBS_Booking::update_status( (int) $payment['booking_id'], 'confirmed' );
		}
	}

	private function handle_capture_refunded( array $resource ): void {
		// Additional webhook-based refund reconciliation if needed
	}

	private function verify_webhook( string $raw_body ): bool {
		// Full signature verification requires PayPal SDK.
		// For production, implement using PayPal's verify-webhook-signature API.
		// Skipping signature check when webhook ID is not configured.
		$webhook_id = get_option( 'wpbs_paypal_webhook_id', '' );
		if ( empty( $webhook_id ) ) {
			return true;
		}

		$token = $this->get_access_token();
		if ( ! $token ) {
			return false;
		}

		$headers = getallheaders();
		$payload = array(
			'auth_algo'         => $headers['PAYPAL-AUTH-ALGO'] ?? '',
			'cert_url'          => $headers['PAYPAL-CERT-URL'] ?? '',
			'transmission_id'   => $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
			'transmission_sig'  => $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
			'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
			'webhook_id'        => $webhook_id,
			'webhook_event'     => json_decode( $raw_body, true ),
		);

		$response = wp_remote_post(
			$this->api_base . '/v1/notifications/verify-webhook-signature',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return 'SUCCESS' === ( $body['verification_status'] ?? '' );
	}

	private function get_access_token(): string {
		$cache_key = 'wpbs_paypal_token_' . md5( $this->client_id );
		$cached    = get_transient( $cache_key );
		if ( $cached ) {
			return $cached;
		}

		$response = wp_remote_post(
			$this->api_base . '/v1/oauth2/token',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $this->client_id . ':' . $this->secret ),
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => 'grant_type=client_credentials',
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$token = $body['access_token'] ?? '';

		if ( $token ) {
			$expires = absint( $body['expires_in'] ?? 3600 ) - 60;
			set_transient( $cache_key, $token, $expires );
		}

		return $token;
	}
}

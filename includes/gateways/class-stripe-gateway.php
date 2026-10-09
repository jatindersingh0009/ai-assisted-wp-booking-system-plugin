<?php
defined( 'ABSPATH' ) || exit;

class WPBS_Stripe_Gateway {

	private string $publishable_key;
	private string $secret_key;
	private string $webhook_secret;
	private string $api_base = 'https://api.stripe.com/v1';

	public function __construct() {
		$mode                  = get_option( 'wpbs_stripe_mode', 'test' );
		$this->publishable_key = get_option( 'wpbs_stripe_publishable_key', '' );
		$this->secret_key      = get_option( 'wpbs_stripe_secret_key', '' );
		$this->webhook_secret  = get_option( 'wpbs_stripe_webhook_secret', '' );
	}

	/**
	 * Create a Stripe PaymentIntent and return the client_secret for the frontend.
	 */
	public function create_payment_intent( array $booking ): array {
		if ( empty( $this->secret_key ) ) {
			return array( 'success' => false, 'message' => __( 'Stripe is not configured.', 'wp-booking-system' ) );
		}

		$currency      = strtolower( get_option( 'wpbs_currency', 'USD' ) );
		$amount_cents  = (int) round( (float) $booking['amount'] * 100 );
		$customer      = get_userdata( (int) $booking['customer_id'] );

		$params = array(
			'amount'               => $amount_cents,
			'currency'             => $currency,
			'automatic_payment_methods' => array( 'enabled' => 'true' ),
			'metadata'             => array(
				'booking_id'  => $booking['id'],
				'booking_ref' => $booking['booking_ref'],
				'site_url'    => get_site_url(),
			),
			'description'          => sprintf(
				/* translators: 1: service name, 2: booking reference */
				__( 'Booking: %1$s (%2$s)', 'wp-booking-system' ),
				$booking['service_name'] ?? '',
				$booking['booking_ref']
			),
		);

		if ( $customer ) {
			$params['receipt_email'] = $customer->user_email;
		}

		$response = $this->api_request( 'POST', '/payment_intents', $params );

		if ( ! $response['success'] ) {
			return $response;
		}

		$intent = $response['data'];

		// Record pending payment
		WPBS_Payment::record( array(
			'booking_id'       => $booking['id'],
			'transaction_id'   => $intent['id'],
			'gateway'          => 'stripe',
			'amount'           => (float) $booking['amount'],
			'currency'         => strtoupper( $currency ),
			'status'           => 'pending',
			'gateway_response' => $intent,
		) );

		return array(
			'success'            => true,
			'payment_intent_id'  => $intent['id'],
			'client_secret'      => $intent['client_secret'],
			'publishable_key'    => $this->publishable_key,
		);
	}

	/**
	 * Confirm that a PaymentIntent was successfully captured.
	 */
	public function confirm_payment( string $payment_intent_id, int $booking_id ): array {
		$response = $this->api_request( 'GET', '/payment_intents/' . $payment_intent_id );

		if ( ! $response['success'] ) {
			return $response;
		}

		$intent = $response['data'];

		if ( 'succeeded' !== $intent['status'] ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: intent status */
					__( 'Payment not completed. Status: %s', 'wp-booking-system' ),
					$intent['status']
				),
			);
		}

		global $wpdb;
		$payment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}booking_payments WHERE transaction_id = %s AND gateway = 'stripe'",
				$payment_intent_id
			)
		);

		if ( $payment_id ) {
			WPBS_Payment::update( (int) $payment_id, array(
				'status'           => 'completed',
				'gateway_response' => $intent,
			) );
		}

		WPBS_Booking::update_payment_status( $booking_id, 'paid' );
		WPBS_Booking::update_status( $booking_id, 'confirmed' );

		$booking = WPBS_Booking::get( $booking_id );
		$payment = WPBS_Payment::get_by_booking( $booking_id );

		do_action( 'wpbs_payment_completed', $booking, $payment );

		return array( 'success' => true );
	}

	/**
	 * Issue a refund for a Stripe charge.
	 */
	public function refund( string $payment_intent_id, float $amount, string $currency ): array {
		// First retrieve the PaymentIntent to get the charge ID
		$pi_response = $this->api_request( 'GET', '/payment_intents/' . $payment_intent_id );
		if ( ! $pi_response['success'] ) {
			return $pi_response;
		}

		$charge_id = $pi_response['data']['latest_charge'] ?? '';
		if ( ! $charge_id ) {
			return array( 'success' => false, 'message' => __( 'No charge found for this payment.', 'wp-booking-system' ) );
		}

		$params = array(
			'charge' => $charge_id,
			'amount' => (int) round( $amount * 100 ),
		);

		$response = $this->api_request( 'POST', '/refunds', $params );

		if ( ! $response['success'] ) {
			return $response;
		}

		$refund = $response['data'];
		if ( 'succeeded' !== $refund['status'] ) {
			return array( 'success' => false, 'message' => __( 'Refund did not succeed.', 'wp-booking-system' ) );
		}

		return array( 'success' => true, 'refund_id' => $refund['id'] );
	}

	/**
	 * Handle Stripe webhook events.
	 */
	public function handle_webhook(): void {
		$raw_body  = file_get_contents( 'php://input' );
		$signature = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) )
			: '';

		if ( ! $this->verify_webhook_signature( $raw_body, $signature ) ) {
			wp_die( 'Webhook signature verification failed', 401 );
		}

		$event = json_decode( $raw_body, true );
		if ( empty( $event['type'] ) ) {
			wp_die( 'Invalid event', 400 );
		}

		switch ( $event['type'] ) {
			case 'payment_intent.succeeded':
				$this->handle_payment_succeeded( $event['data']['object'] );
				break;
			case 'payment_intent.payment_failed':
				$this->handle_payment_failed( $event['data']['object'] );
				break;
			case 'charge.refunded':
				$this->handle_charge_refunded( $event['data']['object'] );
				break;
		}

		status_header( 200 );
		exit;
	}

	private function handle_payment_succeeded( array $intent ): void {
		global $wpdb;
		$booking_id = (int) ( $intent['metadata']['booking_id'] ?? 0 );
		if ( ! $booking_id ) {
			return;
		}

		$payment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}booking_payments WHERE transaction_id = %s AND gateway = 'stripe'",
				$intent['id']
			)
		);

		if ( $payment_id ) {
			WPBS_Payment::update( (int) $payment_id, array(
				'status'           => 'completed',
				'gateway_response' => $intent,
			) );
		}

		WPBS_Booking::update_payment_status( $booking_id, 'paid' );
		WPBS_Booking::update_status( $booking_id, 'confirmed' );
	}

	private function handle_payment_failed( array $intent ): void {
		global $wpdb;
		$payment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}booking_payments WHERE transaction_id = %s AND gateway = 'stripe'",
				$intent['id']
			)
		);

		if ( $payment_id ) {
			WPBS_Payment::update( (int) $payment_id, array( 'status' => 'failed' ) );
		}

		$booking_id = (int) ( $intent['metadata']['booking_id'] ?? 0 );
		if ( $booking_id ) {
			WPBS_Booking::update_payment_status( $booking_id, 'failed' );
		}
	}

	private function handle_charge_refunded( array $charge ): void {
		global $wpdb;
		$payment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}booking_payments
				 WHERE transaction_id = %s AND gateway = 'stripe'",
				$charge['payment_intent'] ?? ''
			)
		);

		if ( $payment_id ) {
			$refunded = (float) $charge['amount_refunded'] / 100;
			$total    = (float) $charge['amount'] / 100;
			$status   = $refunded >= $total ? 'refunded' : 'partially_refunded';
			WPBS_Payment::update( (int) $payment_id, array( 'status' => $status, 'refund_amount' => $refunded ) );
		}
	}

	private function verify_webhook_signature( string $payload, string $signature ): bool {
		if ( empty( $this->webhook_secret ) ) {
			return true; // Permissive if not configured
		}

		$parts = array();
		foreach ( explode( ',', $signature ) as $part ) {
			$kv = explode( '=', $part, 2 );
			$parts[ $kv[0] ] = $kv[1] ?? '';
		}

		$timestamp = $parts['t'] ?? 0;
		$sig       = $parts['v1'] ?? '';

		if ( ! $timestamp || ! $sig ) {
			return false;
		}

		// Replay attack prevention: reject webhooks older than 5 minutes
		if ( abs( time() - (int) $timestamp ) > 300 ) {
			return false;
		}

		$signed_payload = $timestamp . '.' . $payload;
		$expected       = hash_hmac( 'sha256', $signed_payload, $this->webhook_secret );

		return hash_equals( $expected, $sig );
	}

	private function api_request( string $method, string $endpoint, array $params = array() ): array {
		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->secret_key,
				'Stripe-Version' => '2024-06-20',
			),
		);

		$url = $this->api_base . $endpoint;

		if ( 'POST' === $method ) {
			$args['body']   = $params;
			$response = wp_remote_post( $url, $args );
		} else {
			if ( ! empty( $params ) ) {
				$url .= '?' . http_build_query( $params );
			}
			$response = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			return array(
				'success' => false,
				'message' => $body['error']['message'] ?? __( 'Stripe API error.', 'wp-booking-system' ),
			);
		}

		return array( 'success' => true, 'data' => $body );
	}
}

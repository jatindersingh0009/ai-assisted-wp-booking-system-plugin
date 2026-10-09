<?php
defined( 'ABSPATH' ) || exit;

class WPBS_Payment {

	public static function record( array $data ): int|false {
		global $wpdb;
		$result = $wpdb->insert(
			$wpdb->prefix . 'booking_payments',
			array(
				'booking_id'       => absint( $data['booking_id'] ),
				'transaction_id'   => sanitize_text_field( $data['transaction_id'] ?? '' ),
				'gateway'          => sanitize_text_field( $data['gateway'] ),
				'amount'           => (float) $data['amount'],
				'currency'         => sanitize_text_field( $data['currency'] ?? 'USD' ),
				'status'           => sanitize_text_field( $data['status'] ?? 'pending' ),
				'gateway_response' => wp_json_encode( $data['gateway_response'] ?? array() ),
			),
			array( '%d', '%s', '%s', '%f', '%s', '%s', '%s' )
		);
		return $result ? $wpdb->insert_id : false;
	}

	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$update  = array();
		$formats = array();

		if ( isset( $data['transaction_id'] ) ) {
			$update['transaction_id'] = sanitize_text_field( $data['transaction_id'] );
			$formats[] = '%s';
		}
		if ( isset( $data['status'] ) ) {
			$update['status'] = sanitize_text_field( $data['status'] );
			$formats[] = '%s';
		}
		if ( isset( $data['gateway_response'] ) ) {
			$update['gateway_response'] = wp_json_encode( $data['gateway_response'] );
			$formats[] = '%s';
		}
		if ( isset( $data['refund_amount'] ) ) {
			$update['refund_amount'] = (float) $data['refund_amount'];
			$formats[] = '%f';
		}
		if ( isset( $data['refund_id'] ) ) {
			$update['refund_id'] = sanitize_text_field( $data['refund_id'] );
			$formats[] = '%s';
			$update['refunded_at'] = current_time( 'mysql' );
			$formats[] = '%s';
		}

		if ( empty( $update ) ) {
			return false;
		}

		return false !== $wpdb->update(
			$wpdb->prefix . 'booking_payments',
			$update,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);
	}

	public static function get_by_booking( int $booking_id ): ?array {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}booking_payments WHERE booking_id = %d ORDER BY created_at DESC LIMIT 1",
				$booking_id
			),
			ARRAY_A
		) ?: null;
	}

	public static function get( int $id ): ?array {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}booking_payments WHERE id = %d", $id ),
			ARRAY_A
		) ?: null;
	}

	public static function get_all( array $args = array() ): array {
		global $wpdb;
		$where  = array( '1=1' );
		$values = array();
		$defaults = array(
			'gateway'    => '',
			'status'     => '',
			'date_from'  => '',
			'date_to'    => '',
			'limit'      => 20,
			'offset'     => 0,
		);
		$args = wp_parse_args( $args, $defaults );

		if ( ! empty( $args['gateway'] ) ) {
			$where[]  = 'p.gateway = %s';
			$values[] = $args['gateway'];
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'p.status = %s';
			$values[] = $args['status'];
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'p.created_at >= %s';
			$values[] = $args['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'p.created_at <= %s';
			$values[] = $args['date_to'] . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT p.*, b.booking_ref, b.booking_date, u.display_name AS customer_name
					  FROM {$wpdb->prefix}booking_payments p
					  LEFT JOIN {$wpdb->prefix}bookings b ON p.booking_id = b.id
					  LEFT JOIN {$wpdb->users} u ON b.customer_id = u.ID
					  WHERE {$where_sql}
					  ORDER BY p.created_at DESC
					  LIMIT %d OFFSET %d";

		$values[] = (int) $args['limit'];
		$values[] = (int) $args['offset'];

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, ...$values ), ARRAY_A ) ?: array();
	}

	public static function get_summary( string $date_from = '', string $date_to = '' ): array {
		global $wpdb;
		$where  = array( "status = 'completed'" );
		$values = array();

		if ( $date_from ) {
			$where[]  = 'created_at >= %s';
			$values[] = $date_from . ' 00:00:00';
		}
		if ( $date_to ) {
			$where[]  = 'created_at <= %s';
			$values[] = $date_to . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT gateway,
					         COUNT(*) AS count,
					         SUM(amount) AS total,
					         SUM(refund_amount) AS refunded
					  FROM {$wpdb->prefix}booking_payments
					  WHERE {$where_sql}
					  GROUP BY gateway";

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, ...$values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}

	/**
	 * Process a refund via the gateway and update DB records.
	 */
	public static function process_refund( int $payment_id, float $amount ): array {
		$payment = self::get( $payment_id );
		if ( ! $payment ) {
			return array( 'success' => false, 'message' => __( 'Payment not found.', 'wp-booking-system' ) );
		}

		if ( 'completed' !== $payment['status'] ) {
			return array( 'success' => false, 'message' => __( 'Payment cannot be refunded.', 'wp-booking-system' ) );
		}

		$max_refund = (float) $payment['amount'] - (float) $payment['refund_amount'];
		if ( $amount > $max_refund ) {
			return array( 'success' => false, 'message' => __( 'Refund amount exceeds available balance.', 'wp-booking-system' ) );
		}

		if ( 'paypal' === $payment['gateway'] ) {
			$gateway = new WPBS_PayPal_Gateway();
		} elseif ( 'stripe' === $payment['gateway'] ) {
			$gateway = new WPBS_Stripe_Gateway();
		} else {
			return array( 'success' => false, 'message' => __( 'Unsupported gateway.', 'wp-booking-system' ) );
		}

		$result = $gateway->refund( $payment['transaction_id'], $amount, $payment['currency'] );

		if ( $result['success'] ) {
			$new_refund = (float) $payment['refund_amount'] + $amount;
			$new_status = $new_refund >= (float) $payment['amount'] ? 'refunded' : 'partially_refunded';

			self::update( $payment_id, array(
				'refund_amount' => $new_refund,
				'refund_id'     => $result['refund_id'],
				'status'        => $new_status,
			) );

			WPBS_Booking::update_payment_status( (int) $payment['booking_id'], $new_status );
			do_action( 'wpbs_payment_refunded', $payment, $amount, $result );
		}

		return $result;
	}
}

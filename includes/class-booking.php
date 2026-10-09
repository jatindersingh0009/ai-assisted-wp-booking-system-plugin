<?php
defined( 'ABSPATH' ) || exit;

class WPBS_Booking {

	public static function get( int $id ): ?array {
		global $wpdb;
		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT b.*, s.name AS service_name, s.duration AS service_duration,
				        u.display_name AS customer_name, u.user_email AS customer_email
				 FROM {$wpdb->prefix}bookings b
				 LEFT JOIN {$wpdb->prefix}booking_services s ON b.service_id = s.id
				 LEFT JOIN {$wpdb->users} u ON b.customer_id = u.ID
				 WHERE b.id = %d",
				$id
			),
			ARRAY_A
		);
		return $booking ?: null;
	}

	public static function get_by_ref( string $ref ): ?array {
		global $wpdb;
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bookings WHERE booking_ref = %s",
				$ref
			)
		);
		return $id ? self::get( (int) $id ) : null;
	}

	public static function get_all( array $args = array() ): array {
		global $wpdb;
		$defaults = array(
			'customer_id'    => 0,
			'service_id'     => 0,
			'booking_status' => '',
			'payment_status' => '',
			'date_from'      => '',
			'date_to'        => '',
			'search'         => '',
			'orderby'        => 'created_at',
			'order'          => 'DESC',
			'limit'          => 20,
			'offset'         => 0,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['customer_id'] ) ) {
			$where[]  = 'b.customer_id = %d';
			$values[] = (int) $args['customer_id'];
		}
		if ( ! empty( $args['service_id'] ) ) {
			$where[]  = 'b.service_id = %d';
			$values[] = (int) $args['service_id'];
		}
		if ( ! empty( $args['booking_status'] ) ) {
			$where[]  = 'b.booking_status = %s';
			$values[] = $args['booking_status'];
		}
		if ( ! empty( $args['payment_status'] ) ) {
			$where[]  = 'b.payment_status = %s';
			$values[] = $args['payment_status'];
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'b.booking_date >= %s';
			$values[] = $args['date_from'];
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'b.booking_date <= %s';
			$values[] = $args['date_to'];
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(b.booking_ref LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$allowed_orderby = array( 'created_at', 'booking_date', 'booking_status', 'amount' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'b.created_at';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$where_sql = implode( ' AND ', $where );

		$sql = "SELECT b.*, s.name AS service_name, u.display_name AS customer_name, u.user_email AS customer_email
				FROM {$wpdb->prefix}bookings b
				LEFT JOIN {$wpdb->prefix}booking_services s ON b.service_id = s.id
				LEFT JOIN {$wpdb->users} u ON b.customer_id = u.ID
				WHERE {$where_sql}
				ORDER BY b.{$orderby} {$order}
				LIMIT %d OFFSET %d";

		$values[] = (int) $args['limit'];
		$values[] = (int) $args['offset'];

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, ...$values ), ARRAY_A ) ?: array();
	}

	public static function count( array $args = array() ): int {
		global $wpdb;
		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['customer_id'] ) ) {
			$where[]  = 'b.customer_id = %d';
			$values[] = (int) $args['customer_id'];
		}
		if ( ! empty( $args['booking_status'] ) ) {
			$where[]  = 'b.booking_status = %s';
			$values[] = $args['booking_status'];
		}
		if ( ! empty( $args['payment_status'] ) ) {
			$where[]  = 'b.payment_status = %s';
			$values[] = $args['payment_status'];
		}

		$where_sql = implode( ' AND ', $where );
		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}bookings b
				LEFT JOIN {$wpdb->users} u ON b.customer_id = u.ID
				WHERE {$where_sql}";

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, ...$values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql );
	}

	public static function create( array $data ): array {
		global $wpdb;

		$service = WPBS_Service::get( (int) $data['service_id'] );
		if ( ! $service ) {
			return array( 'success' => false, 'message' => __( 'Service not found.', 'wp-booking-system' ) );
		}

		// Double-booking check
		if ( self::slot_is_taken( (int) $data['service_id'], $data['booking_date'], $data['booking_time'], (int) $service['duration'], (int) $service['capacity'] ) ) {
			return array( 'success' => false, 'message' => __( 'This time slot is no longer available.', 'wp-booking-system' ) );
		}

		$start    = new DateTime( $data['booking_date'] . ' ' . $data['booking_time'] );
		$end      = clone $start;
		$end->modify( '+' . (int) $service['duration'] . ' minutes' );

		$result = $wpdb->insert(
			$wpdb->prefix . 'bookings',
			array(
				'booking_ref'    => WPBS_Database::generate_booking_ref(),
				'customer_id'    => absint( $data['customer_id'] ),
				'service_id'     => absint( $data['service_id'] ),
				'booking_date'   => sanitize_text_field( $data['booking_date'] ),
				'booking_time'   => sanitize_text_field( $data['booking_time'] ),
				'end_time'       => $end->format( 'H:i:s' ),
				'duration'       => (int) $service['duration'],
				'amount'         => (float) $service['price'],
				'payment_method' => sanitize_text_field( $data['payment_method'] ?? '' ),
				'payment_status' => 'pending',
				'booking_status' => 'pending',
				'notes'          => sanitize_textarea_field( $data['notes'] ?? '' ),
				'ip_address'     => sanitize_text_field( self::get_client_ip() ),
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return array( 'success' => false, 'message' => __( 'Failed to create booking.', 'wp-booking-system' ) );
		}

		$booking_id = $wpdb->insert_id;
		$booking    = self::get( $booking_id );

		do_action( 'wpbs_booking_created', $booking );

		return array( 'success' => true, 'booking_id' => $booking_id, 'booking' => $booking );
	}

	public static function update_status( int $id, string $status, ?string $admin_notes = null ): bool {
		global $wpdb;
		$allowed = array( 'pending', 'confirmed', 'cancelled', 'completed', 'rejected', 'rescheduled' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		$data = array( 'booking_status' => $status );
		if ( null !== $admin_notes ) {
			$data['admin_notes'] = sanitize_textarea_field( $admin_notes );
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'bookings',
			$data,
			array( 'id' => $id ),
			array_fill( 0, count( $data ), '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			$booking = self::get( $id );
			do_action( 'wpbs_booking_status_updated', $booking, $status );
		}

		return false !== $result;
	}

	public static function update_payment_status( int $id, string $status ): bool {
		global $wpdb;
		$allowed = array( 'pending', 'paid', 'failed', 'refunded', 'partially_refunded' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'bookings',
			array( 'payment_status' => $status ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	public static function can_cancel( array $booking ): bool {
		if ( ! in_array( $booking['booking_status'], array( 'pending', 'confirmed' ), true ) ) {
			return false;
		}
		$rules         = get_option( 'wpbs_booking_rules', array() );
		$cancel_hours  = absint( $rules['cancellation_hours'] ?? 24 );
		$booking_dt    = new DateTime( $booking['booking_date'] . ' ' . $booking['booking_time'] );
		$now           = new DateTime();
		$diff_hours    = ( $booking_dt->getTimestamp() - $now->getTimestamp() ) / 3600;
		return $diff_hours >= $cancel_hours;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( $wpdb->prefix . 'bookings', array( 'id' => $id ), array( '%d' ) );
	}

	private static function slot_is_taken( int $service_id, string $date, string $time, int $duration, int $capacity ): bool {
		global $wpdb;
		$start = new DateTime( $date . ' ' . $time );
		$end   = clone $start;
		$end->modify( "+{$duration} minutes" );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				 WHERE service_id = %d
				   AND booking_date = %s
				   AND booking_status NOT IN ('cancelled','rejected')
				   AND booking_time < %s
				   AND end_time > %s",
				$service_id,
				$date,
				$end->format( 'H:i:s' ),
				$start->format( 'H:i:s' )
			)
		);
		return (int) $count >= $capacity;
	}

	public static function get_revenue_stats( string $date_from = '', string $date_to = '' ): array {
		global $wpdb;
		$where  = array( "payment_status = 'paid'" );
		$values = array();

		if ( $date_from ) {
			$where[]  = 'booking_date >= %s';
			$values[] = $date_from;
		}
		if ( $date_to ) {
			$where[]  = 'booking_date <= %s';
			$values[] = $date_to;
		}

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT COUNT(*) AS total_bookings, SUM(amount) AS total_revenue,
			                 AVG(amount) AS avg_revenue
					  FROM {$wpdb->prefix}bookings
					  WHERE {$where_sql}";

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, ...$values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_row( $sql, ARRAY_A ) ?: array(
			'total_bookings' => 0,
			'total_revenue'  => 0,
			'avg_revenue'    => 0,
		);
	}

	private static function get_client_ip(): string {
		$ip = '';
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) )[0];
				break;
			}
		}
		return filter_var( trim( $ip ), FILTER_VALIDATE_IP ) ? trim( $ip ) : '';
	}
}

<?php
defined( 'ABSPATH' ) || exit;

class WPBS_Availability {

	/**
	 * Returns an array of available time slots for a service on a given date.
	 * Format: [ '09:00', '09:30', ... ]
	 */
	public static function get_available_slots( int $service_id, string $date ): array {
		$service = WPBS_Service::get( $service_id );
		if ( ! $service ) {
			return array();
		}

		$date_obj   = new DateTime( $date );
		$day_name   = strtolower( $date_obj->format( 'l' ) );
		$rules      = get_option( 'wpbs_booking_rules', array() );
		$interval   = absint( $rules['slot_interval_minutes'] ?? 30 );
		$duration   = (int) $service['duration'];

		// Get business hours for this day
		$business_hours = get_option( 'wpbs_business_hours', array() );
		$day_hours      = $business_hours[ $day_name ] ?? array( 'enabled' => false );

		if ( empty( $day_hours['enabled'] ) ) {
			return array();
		}

		// Check min/max advance booking window
		$timezone      = new DateTimeZone( get_option( 'wpbs_timezone', 'UTC' ) );
		$now           = new DateTime( 'now', $timezone );
		$booking_date  = new DateTime( $date . ' 00:00:00', $timezone );
		$min_advance   = absint( $rules['min_advance_hours'] ?? 2 );
		$max_advance   = absint( $rules['max_advance_days'] ?? 60 );

		$min_date = clone $now;
		$min_date->modify( "+{$min_advance} hours" );

		$max_date = clone $now;
		$max_date->modify( "+{$max_advance} days" );

		if ( $booking_date > $max_date || $booking_date < $now ) {
			return array();
		}

		$open_time  = new DateTime( $date . ' ' . $day_hours['open'] . ':00', $timezone );
		$close_time = new DateTime( $date . ' ' . $day_hours['close'] . ':00', $timezone );

		// Collect all blocked ranges for this date + service
		$blocked = self::get_blocked_ranges( $service_id, $date );

		// Collect already-booked slots
		$booked = self::get_booked_slots( $service_id, $date );

		$slots       = array();
		$current     = clone $open_time;
		$slot_end_interval = new DateInterval( 'PT' . $interval . 'M' );
		$duration_interval = new DateInterval( 'PT' . $duration . 'M' );

		while ( $current < $close_time ) {
			$slot_end = clone $current;
			$slot_end->add( $duration_interval );

			if ( $slot_end > $close_time ) {
				break;
			}

			// Skip slots in the minimum advance window
			if ( $current <= $min_date ) {
				$current->add( $slot_end_interval );
				continue;
			}

			$slot_start_str = $current->format( 'H:i' );
			$slot_end_str   = $slot_end->format( 'H:i' );

			if (
				! self::is_slot_blocked( $slot_start_str, $slot_end_str, $blocked ) &&
				! self::is_slot_booked( $slot_start_str, $slot_end_str, $booked, (int) $service['capacity'] )
			) {
				$slots[] = $slot_start_str;
			}

			$current->add( $slot_end_interval );
		}

		return $slots;
	}

	private static function get_blocked_ranges( int $service_id, string $date ): array {
		global $wpdb;
		$day_of_week = (int) ( new DateTime( $date ) )->format( 'w' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT start_time, end_time FROM {$wpdb->prefix}booking_availability
				 WHERE rule_type = 'block'
				   AND ( service_id = %d OR service_id IS NULL )
				   AND (
				       ( is_recurring = 0 AND start_date <= %s AND end_date >= %s )
				    OR ( is_recurring = 1 AND start_date <= %s AND ( end_date >= %s OR end_date = '0000-00-00' ) )
				   )",
				$service_id,
				$date,
				$date,
				$date,
				$date
			),
			ARRAY_A
		) ?: array();
	}

	private static function get_booked_slots( int $service_id, string $date ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT booking_time, end_time FROM {$wpdb->prefix}bookings
				 WHERE service_id = %d
				   AND booking_date = %s
				   AND booking_status NOT IN ('cancelled', 'rejected')",
				$service_id,
				$date
			),
			ARRAY_A
		) ?: array();
	}

	private static function is_slot_blocked( string $start, string $end, array $blocked ): bool {
		foreach ( $blocked as $block ) {
			if ( empty( $block['start_time'] ) ) {
				return true; // Full day block
			}
			if ( $start < $block['end_time'] && $end > $block['start_time'] ) {
				return true;
			}
		}
		return false;
	}

	private static function is_slot_booked( string $start, string $end, array $booked, int $capacity ): bool {
		$count = 0;
		foreach ( $booked as $booking ) {
			if ( $start < $booking['end_time'] && $end > $booking['booking_time'] ) {
				$count++;
			}
		}
		return $count >= $capacity;
	}

	public static function is_date_available( int $service_id, string $date ): bool {
		return ! empty( self::get_available_slots( $service_id, $date ) );
	}

	public static function get_available_dates( int $service_id, int $month, int $year ): array {
		$days_in_month = cal_days_in_month( CAL_GREGORIAN, $month, $year );
		$available     = array();
		for ( $day = 1; $day <= $days_in_month; $day++ ) {
			$date = sprintf( '%04d-%02d-%02d', $year, $month, $day );
			if ( self::is_date_available( $service_id, $date ) ) {
				$available[] = $date;
			}
		}
		return $available;
	}

	public static function block_slot( array $data ): int|false {
		global $wpdb;
		$result = $wpdb->insert(
			$wpdb->prefix . 'booking_availability',
			array(
				'service_id'   => $data['service_id'] ? absint( $data['service_id'] ) : null,
				'rule_type'    => 'block',
				'start_date'   => sanitize_text_field( $data['start_date'] ),
				'end_date'     => sanitize_text_field( $data['end_date'] ),
				'start_time'   => ! empty( $data['start_time'] ) ? sanitize_text_field( $data['start_time'] ) : null,
				'end_time'     => ! empty( $data['end_time'] ) ? sanitize_text_field( $data['end_time'] ) : null,
				'is_recurring' => absint( $data['is_recurring'] ?? 0 ),
				'reason'       => sanitize_text_field( $data['reason'] ?? '' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
		return $result ? $wpdb->insert_id : false;
	}
}

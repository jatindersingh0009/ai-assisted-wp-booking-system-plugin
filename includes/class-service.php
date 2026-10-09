<?php
defined( 'ABSPATH' ) || exit;

class WPBS_Service {

	public static function get_all( array $args = array() ): array {
		global $wpdb;
		$table    = $wpdb->prefix . 'booking_services';
		$defaults = array(
			'is_active'  => 1,
			'orderby'    => 'sort_order',
			'order'      => 'ASC',
			'limit'      => 0,
			'offset'     => 0,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = '';
		$values = array();

		if ( isset( $args['is_active'] ) && $args['is_active'] !== '' ) {
			$where    .= ' WHERE is_active = %d';
			$values[] = (int) $args['is_active'];
		}

		$orderby = in_array( $args['orderby'], array( 'id', 'name', 'sort_order', 'price', 'created_at' ), true )
			? $args['orderby'] : 'sort_order';
		$order = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';

		$limit_sql = '';
		if ( $args['limit'] > 0 ) {
			$limit_sql = $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $args['limit'], (int) $args['offset'] );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT * FROM {$table}{$where} ORDER BY {$orderby} {$order}{$limit_sql}";

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, ...$values );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}

	public static function get( int $id ): ?array {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}booking_services WHERE id = %d", $id ),
			ARRAY_A
		) ?: null;
	}

	public static function create( array $data ): int|false {
		global $wpdb;
		$result = $wpdb->insert(
			$wpdb->prefix . 'booking_services',
			array(
				'name'        => sanitize_text_field( $data['name'] ),
				'description' => wp_kses_post( $data['description'] ?? '' ),
				'duration'    => absint( $data['duration'] ),
				'capacity'    => absint( $data['capacity'] ?? 1 ),
				'price'       => (float) $data['price'],
				'category'    => sanitize_text_field( $data['category'] ?? '' ),
				'image_id'    => absint( $data['image_id'] ?? 0 ) ?: null,
				'is_active'   => absint( $data['is_active'] ?? 1 ),
				'sort_order'  => absint( $data['sort_order'] ?? 0 ),
			),
			array( '%s', '%s', '%d', '%d', '%f', '%s', '%d', '%d', '%d' )
		);
		return $result ? $wpdb->insert_id : false;
	}

	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$update = array();
		$formats = array();

		if ( isset( $data['name'] ) ) {
			$update['name'] = sanitize_text_field( $data['name'] );
			$formats[] = '%s';
		}
		if ( isset( $data['description'] ) ) {
			$update['description'] = wp_kses_post( $data['description'] );
			$formats[] = '%s';
		}
		if ( isset( $data['duration'] ) ) {
			$update['duration'] = absint( $data['duration'] );
			$formats[] = '%d';
		}
		if ( isset( $data['capacity'] ) ) {
			$update['capacity'] = absint( $data['capacity'] );
			$formats[] = '%d';
		}
		if ( isset( $data['price'] ) ) {
			$update['price'] = (float) $data['price'];
			$formats[] = '%f';
		}
		if ( isset( $data['is_active'] ) ) {
			$update['is_active'] = absint( $data['is_active'] );
			$formats[] = '%d';
		}
		if ( isset( $data['sort_order'] ) ) {
			$update['sort_order'] = absint( $data['sort_order'] );
			$formats[] = '%d';
		}
		if ( isset( $data['category'] ) ) {
			$update['category'] = sanitize_text_field( $data['category'] );
			$formats[] = '%s';
		}

		if ( empty( $update ) ) {
			return false;
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'booking_services',
			$update,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		return $result !== false;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		// Check for active bookings first
		$active = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bookings
				 WHERE service_id = %d AND booking_status NOT IN ('cancelled','completed')",
				$id
			)
		);
		if ( $active > 0 ) {
			return false;
		}
		return (bool) $wpdb->delete(
			$wpdb->prefix . 'booking_services',
			array( 'id' => $id ),
			array( '%d' )
		);
	}

	public static function count( array $args = array() ): int {
		global $wpdb;
		$where  = '';
		$values = array();

		if ( isset( $args['is_active'] ) && $args['is_active'] !== '' ) {
			$where    .= ' WHERE is_active = %d';
			$values[] = (int) $args['is_active'];
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}booking_services{$where}";
		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$sql = $wpdb->prepare( $sql, ...$values );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql );
	}
}

<?php
defined( 'ABSPATH' ) || exit;

class WPBS_Database {

	public static function create_tables(): void {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Services table
		$sql_services = "CREATE TABLE {$wpdb->prefix}booking_services (
			id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name           VARCHAR(200)        NOT NULL,
			description    TEXT                         DEFAULT NULL,
			duration       INT(11)             NOT NULL DEFAULT 60 COMMENT 'minutes',
			capacity       INT(11)             NOT NULL DEFAULT 1,
			price          DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
			category       VARCHAR(100)                 DEFAULT NULL,
			image_id       BIGINT(20) UNSIGNED          DEFAULT NULL,
			is_active      TINYINT(1)          NOT NULL DEFAULT 1,
			sort_order     INT(11)             NOT NULL DEFAULT 0,
			created_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_is_active (is_active),
			KEY idx_sort_order (sort_order)
		) $charset_collate;";

		// Bookings table
		$sql_bookings = "CREATE TABLE {$wpdb->prefix}bookings (
			id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_ref      VARCHAR(20)         NOT NULL,
			customer_id      BIGINT(20) UNSIGNED NOT NULL,
			service_id       BIGINT(20) UNSIGNED NOT NULL,
			booking_date     DATE                NOT NULL,
			booking_time     TIME                NOT NULL,
			end_time         TIME                NOT NULL,
			duration         INT(11)             NOT NULL DEFAULT 60,
			amount           DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
			payment_method   VARCHAR(50)                  DEFAULT NULL,
			payment_status   VARCHAR(20)         NOT NULL DEFAULT 'pending',
			booking_status   VARCHAR(20)         NOT NULL DEFAULT 'pending',
			notes            TEXT                         DEFAULT NULL,
			admin_notes      TEXT                         DEFAULT NULL,
			ip_address       VARCHAR(45)                  DEFAULT NULL,
			reminder_sent    TINYINT(1)          NOT NULL DEFAULT 0,
			created_at       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uk_booking_ref (booking_ref),
			KEY idx_customer_id (customer_id),
			KEY idx_service_id (service_id),
			KEY idx_booking_date (booking_date),
			KEY idx_booking_status (booking_status),
			KEY idx_payment_status (payment_status),
			KEY idx_date_time (booking_date, booking_time)
		) $charset_collate;";

		// Payments table
		$sql_payments = "CREATE TABLE {$wpdb->prefix}booking_payments (
			id                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id          BIGINT(20) UNSIGNED NOT NULL,
			transaction_id      VARCHAR(200)                 DEFAULT NULL,
			gateway             VARCHAR(50)         NOT NULL,
			amount              DECIMAL(10,2)       NOT NULL,
			currency            VARCHAR(10)         NOT NULL DEFAULT 'USD',
			status              VARCHAR(20)         NOT NULL DEFAULT 'pending',
			gateway_response    LONGTEXT                     DEFAULT NULL,
			refund_amount       DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
			refund_id           VARCHAR(200)                 DEFAULT NULL,
			refunded_at         DATETIME                     DEFAULT NULL,
			created_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_booking_id (booking_id),
			KEY idx_transaction_id (transaction_id),
			KEY idx_status (status),
			KEY idx_gateway (gateway)
		) $charset_collate;";

		// Availability table (overrides to business hours – blocked slots or special hours)
		$sql_availability = "CREATE TABLE {$wpdb->prefix}booking_availability (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			service_id   BIGINT(20) UNSIGNED          DEFAULT NULL COMMENT 'NULL = applies to all services',
			rule_type    VARCHAR(20)         NOT NULL DEFAULT 'block' COMMENT 'block|special_hours',
			start_date   DATE                NOT NULL,
			end_date     DATE                NOT NULL,
			start_time   TIME                         DEFAULT NULL,
			end_time     TIME                         DEFAULT NULL,
			is_recurring TINYINT(1)          NOT NULL DEFAULT 0,
			recur_days   VARCHAR(20)                  DEFAULT NULL COMMENT 'JSON array of day numbers 0-6',
			reason       VARCHAR(200)                 DEFAULT NULL,
			created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_service_id (service_id),
			KEY idx_start_date (start_date),
			KEY idx_rule_type (rule_type)
		) $charset_collate;";

		dbDelta( $sql_services );
		dbDelta( $sql_bookings );
		dbDelta( $sql_payments );
		dbDelta( $sql_availability );
	}

	public static function drop_tables(): void {
		global $wpdb;
		$tables = array(
			$wpdb->prefix . 'booking_payments',
			$wpdb->prefix . 'bookings',
			$wpdb->prefix . 'booking_services',
			$wpdb->prefix . 'booking_availability',
		);
		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
		}
	}

	/**
	 * Generate a unique booking reference like WPBS-A3F2X9.
	 */
	public static function generate_booking_ref(): string {
		global $wpdb;
		do {
			$ref = 'WPBS-' . strtoupper( wp_generate_password( 6, false, false ) );
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}bookings WHERE booking_ref = %s",
					$ref
				)
			);
		} while ( $exists );

		return $ref;
	}
}

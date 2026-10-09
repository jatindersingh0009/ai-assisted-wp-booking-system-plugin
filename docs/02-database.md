# Database Schema

[← Architecture](01-architecture.md) · [API Reference →](03-api-reference.md)

---

## Overview

The plugin creates four custom tables on activation using WordPress's `dbDelta()`. All table names respect the configured `$wpdb->prefix`.

| Table | Purpose | Rows (typical) |
|---|---|---|
| `wp_booking_services` | Service catalogue | Tens |
| `wp_bookings` | Every booking attempt | Thousands |
| `wp_booking_payments` | Payment records, one per booking attempt | Thousands |
| `wp_booking_availability` | Blocked dates / special hours overrides | Dozens |

Tables are created in activation order: services → bookings → payments → availability. The `wp_bookings` table references `wp_booking_services` (service_id) and `wp_users` (customer_id), but these are enforced at the application layer, not as foreign key constraints, for compatibility with shared hosting environments.

---

## `wp_booking_services`

The service catalogue. Each row defines one bookable service.

```sql
CREATE TABLE wp_booking_services (
    id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(200)        NOT NULL,
    description TEXT                         DEFAULT NULL,
    duration    INT(11)             NOT NULL DEFAULT 60,   -- minutes
    capacity    INT(11)             NOT NULL DEFAULT 1,    -- concurrent bookings per slot
    price       DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
    category    VARCHAR(100)                 DEFAULT NULL,
    image_id    BIGINT(20) UNSIGNED          DEFAULT NULL, -- wp_posts attachment ID
    is_active   TINYINT(1)          NOT NULL DEFAULT 1,
    sort_order  INT(11)             NOT NULL DEFAULT 0,
    created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_is_active  (is_active),
    KEY idx_sort_order (sort_order)
);
```

### Column notes

| Column | Notes |
|---|---|
| `duration` | Stored in minutes. Controls the length of each booked slot and determines the end_time stored in `wp_bookings`. |
| `capacity` | Maximum concurrent bookings for a single time slot. A capacity of 3 allows three customers to book the same 09:00 slot for the same service. |
| `price` | Copied to `wp_bookings.amount` at booking creation. Changing the price here does not retroactively alter existing bookings. |
| `image_id` | References `wp_posts.ID` (attachment). Not a hard FK — attachment deletion does not cascade. |
| `is_active` | `0` hides the service from the booking form without deleting its history. |

---

## `wp_bookings`

The central table. One row per booking attempt (including failed/cancelled bookings).

```sql
CREATE TABLE wp_bookings (
    id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_ref     VARCHAR(20)         NOT NULL,           -- e.g. WPBS-A3F2X9
    customer_id     BIGINT(20) UNSIGNED NOT NULL,           -- wp_users.ID
    service_id      BIGINT(20) UNSIGNED NOT NULL,           -- wp_booking_services.id
    booking_date    DATE                NOT NULL,
    booking_time    TIME                NOT NULL,
    end_time        TIME                NOT NULL,           -- booking_time + service.duration
    duration        INT(11)             NOT NULL DEFAULT 60,
    amount          DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
    payment_method  VARCHAR(50)                  DEFAULT NULL,
    payment_status  VARCHAR(20)         NOT NULL DEFAULT 'pending',
    booking_status  VARCHAR(20)         NOT NULL DEFAULT 'pending',
    notes           TEXT                         DEFAULT NULL,
    admin_notes     TEXT                         DEFAULT NULL,
    ip_address      VARCHAR(45)                  DEFAULT NULL,
    reminder_sent   TINYINT(1)          NOT NULL DEFAULT 0,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_booking_ref (booking_ref),
    KEY idx_customer_id   (customer_id),
    KEY idx_service_id    (service_id),
    KEY idx_booking_date  (booking_date),
    KEY idx_booking_status (booking_status),
    KEY idx_payment_status (payment_status),
    KEY idx_date_time     (booking_date, booking_time)
);
```

### Status values

**`booking_status`**

| Value | Meaning |
|---|---|
| `pending` | Booking created, payment not yet confirmed |
| `confirmed` | Payment completed or admin manually confirmed |
| `completed` | Appointment has taken place |
| `cancelled` | Cancelled by customer or admin |
| `rejected` | Rejected by admin (never goes through payment) |
| `rescheduled` | Placeholder status; rescheduling creates a new booking row |

**`payment_status`**

| Value | Meaning |
|---|---|
| `pending` | No payment attempt yet, or payment initiated |
| `paid` | Full payment received |
| `failed` | Payment attempt failed at the gateway |
| `refunded` | Full refund processed |
| `partially_refunded` | Partial refund processed |

### Column notes

| Column | Notes |
|---|---|
| `booking_ref` | Human-readable identifier. Generated with a loop-until-unique approach in `WPBS_Database::generate_booking_ref()`. Format: `WPBS-` + 6 random alphanumeric characters. |
| `end_time` | Stored at creation from `booking_time + service.duration`. Enables the overlap query in the double-booking guard without recalculating duration at read time. |
| `amount` | Snapshot of `service.price` at creation time. Price changes do not affect existing bookings. |
| `reminder_sent` | Set to `1` by the cron job after the reminder email is dispatched. Prevents duplicate reminders. |
| `ip_address` | Stored for fraud/chargeback investigation. Supports IPv6 (VARCHAR 45). |

### The double-booking guard query

```sql
SELECT COUNT(*)
FROM   wp_bookings
WHERE  service_id      = %d
  AND  booking_date    = %s
  AND  booking_status  NOT IN ('cancelled', 'rejected')
  AND  booking_time    < %s   -- new slot's end_time
  AND  end_time        > %s   -- new slot's booking_time
```

This detects any overlap with an existing booking. If `COUNT(*) >= service.capacity`, the slot is taken.

---

## `wp_booking_payments`

One row per payment attempt. A booking may have multiple rows here if a customer retries after a failed payment.

```sql
CREATE TABLE wp_booking_payments (
    id                BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id        BIGINT(20) UNSIGNED NOT NULL,
    transaction_id    VARCHAR(200)                 DEFAULT NULL,
    gateway           VARCHAR(50)         NOT NULL,
    amount            DECIMAL(10,2)       NOT NULL,
    currency          VARCHAR(10)         NOT NULL DEFAULT 'USD',
    status            VARCHAR(20)         NOT NULL DEFAULT 'pending',
    gateway_response  LONGTEXT                     DEFAULT NULL,  -- raw JSON
    refund_amount     DECIMAL(10,2)       NOT NULL DEFAULT 0.00,
    refund_id         VARCHAR(200)                 DEFAULT NULL,
    refunded_at       DATETIME                     DEFAULT NULL,
    created_at        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_booking_id    (booking_id),
    KEY idx_transaction_id (transaction_id),
    KEY idx_status        (status),
    KEY idx_gateway       (gateway)
);
```

### Column notes

| Column | Notes |
|---|---|
| `transaction_id` | PayPal: the Capture ID (not the Order ID). Stripe: the PaymentIntent ID. Used as the reference for refunds. |
| `gateway_response` | Full JSON response from the gateway, stored as `LONGTEXT`. Invaluable for debugging and dispute evidence. Never displayed in the UI. |
| `refund_amount` | Running total of refunds issued. `amount - refund_amount` = remaining refundable balance. |
| `status` | `pending` → `completed` on capture. `completed` → `refunded` or `partially_refunded` on refund. |

---

## `wp_booking_availability`

Overrides to business hours. Rows here either **block** a date/time range or define **special hours** outside the standard schedule.

```sql
CREATE TABLE wp_booking_availability (
    id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    service_id   BIGINT(20) UNSIGNED          DEFAULT NULL,  -- NULL = all services
    rule_type    VARCHAR(20)         NOT NULL DEFAULT 'block',
    start_date   DATE                NOT NULL,
    end_date     DATE                NOT NULL,
    start_time   TIME                         DEFAULT NULL,  -- NULL = full day
    end_time     TIME                         DEFAULT NULL,
    is_recurring TINYINT(1)          NOT NULL DEFAULT 0,
    recur_days   VARCHAR(20)                  DEFAULT NULL,  -- JSON [0,6] = Sun,Sat
    reason       VARCHAR(200)                 DEFAULT NULL,
    created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_service_id (service_id),
    KEY idx_start_date (start_date),
    KEY idx_rule_type  (rule_type)
);
```

### Rule examples

**Block a full day (holiday):**
```sql
INSERT INTO wp_booking_availability
    (service_id, rule_type, start_date, end_date, reason)
VALUES
    (NULL, 'block', '2025-12-25', '2025-12-25', 'Christmas Day');
```

**Block a time range for one service:**
```sql
INSERT INTO wp_booking_availability
    (service_id, rule_type, start_date, end_date, start_time, end_time)
VALUES
    (3, 'block', '2025-08-01', '2025-08-01', '12:00:00', '14:00:00');
```

**Recurring weekly block (every Sunday):**
```sql
INSERT INTO wp_booking_availability
    (service_id, rule_type, start_date, end_date, is_recurring, recur_days, reason)
VALUES
    (NULL, 'block', '2025-01-01', '2025-12-31', 1, '[0]', 'Closed Sundays');
```

---

## Query Performance Notes

- The `idx_date_time` composite index on `(booking_date, booking_time)` is used by the double-booking guard and the calendar event query. Both filter on `booking_date` first, making this index highly selective.
- `gateway_response` (`LONGTEXT`) is never fetched in list queries — only in single-row lookups. All list queries select explicit columns.
- The reports query `GROUP BY service_id` uses `idx_service_id` on the bookings table. On large installations, adding a covering index on `(service_id, booking_date, amount, payment_status)` will eliminate the file-sort.

# Architecture

[← README](../README.md) · [Database →](02-database.md)

---

## Design Philosophy

The plugin follows three guiding constraints:

1. **No Composer dependency.** Everything ships in the plugin folder. No autoloader, no SDK installation step. This keeps the plugin self-contained and WordPress.org-compatible.
2. **Static model classes, not a DI container.** At this scale (4 tables, bounded domain) a service container adds complexity without benefit. Each model class owns its own DB access methods.
3. **Custom tables, not post meta.** Bookings, payments, and availability are relational data with complex query patterns (joins, aggregates, date ranges). WordPress posts/meta cannot express these efficiently.

---

## File Structure

```
includes/
├── class-wp-booking-system.php   ← Main plugin class (singleton, wires all hooks)
├── class-activator.php           ← DB creation, page setup, option defaults
├── class-deactivator.php         ← Cron cleanup
├── class-database.php            ← Table DDL via dbDelta, ref generation
├── class-service.php             ← Service CRUD
├── class-availability.php        ← Slot calculation, blocking logic
├── class-booking.php             ← Booking CRUD, double-booking guard, stats
├── class-payment.php             ← Payment recording, refund routing
├── class-email.php               ← All transactional email sending
├── class-rest-api.php            ← 16 REST route registrations + handlers
└── gateways/
    ├── class-paypal-gateway.php  ← PayPal REST API v2 (orders, capture, refund)
    └── class-stripe-gateway.php  ← Stripe API 2024 (PaymentIntents, refunds)

admin/
├── class-admin.php               ← Menu registration, enqueue, AJAX dispatch
└── partials/
    ├── admin-dashboard.php       ← Stats overview, recent bookings
    ├── admin-bookings.php        ← List + single booking detail/action
    ├── admin-services.php        ← Service list + add/edit form
    ├── admin-calendar.php        ← FullCalendar integration
    ├── admin-payments.php        ← Payment list + refund form
    ├── admin-reports.php         ← Revenue stats + daily bar chart
    └── admin-settings.php        ← Tabbed settings (general/hours/booking/paypal/stripe)

public/
├── class-public.php              ← Shortcode registration, public AJAX dispatch
└── partials/
    ├── booking-form.php          ← 5-step booking form HTML
    ├── customer-dashboard.php    ← Upcoming/history tabs, cancel/invoice buttons
    └── booking-confirmation.php  ← Post-payment confirmation screen

templates/emails/
├── booking-confirmation.php
├── payment-confirmation.php
├── booking-reminder.php
└── booking-cancellation.php
```

---

## Bootstrap Sequence

```
wp-booking-system.php
  └─ defines constants (WPBS_VERSION, WPBS_PLUGIN_DIR, …)
  └─ registers activation/deactivation hooks
  └─ calls wpbs_run()
       └─ WPBS_Plugin::get_instance()->run()
            ├─ load_dependencies()   — requires all class files
            ├─ define_admin_hooks()  — admin menu, scripts, AJAX
            ├─ define_public_hooks() — shortcodes, public AJAX
            └─ define_common_hooks()
                 ├─ rest_api_init    — registers 16 REST routes
                 ├─ init             — rewrite rules for webhooks
                 ├─ WP-Cron          — reminder + cleanup schedules
                 └─ email action hooks
```

---

## Class Responsibilities

### `WPBS_Plugin` (Singleton)

The only class with side effects at load time. Responsible for wiring WordPress hooks. Does not contain business logic.

```php
WPBS_Plugin::get_instance()->run();
```

### `WPBS_Database`

Static methods only. Owns the `CREATE TABLE` SQL and calls `dbDelta()` on activation and on version mismatch. Also owns `generate_booking_ref()` — a loop-until-unique generator for human-readable references like `WPBS-A3F2X9`.

### `WPBS_Service`

CRUD for `wp_booking_services`. Validates that a service has no active bookings before allowing deletion. Returns `false` (not an exception) on constraint violations so callers can surface the right message.

### `WPBS_Availability`

Pure function: given a `service_id` and `date`, returns an array of available `HH:MM` strings. The algorithm:

```
1. Look up business hours for the day-of-week
2. Check min/max advance booking window against current time
3. Generate candidate slots at the configured interval
4. For each candidate: reject if blocked by wp_booking_availability rows
5. For each remaining: reject if concurrent booking count ≥ service capacity
6. Return the survivors
```

No state. Safe to call multiple times.

### `WPBS_Booking`

Owns the double-booking guard. `create()` runs a `SELECT COUNT(*)` overlap check before `INSERT`. Because WordPress sites typically run on a single DB server without application-level transactions, the check-then-insert window is small. For high-concurrency deployments, wrapping the pair in a DB transaction is the recommended extension (see [Extension Points](#extension-points)).

### `WPBS_Payment`

Records every payment attempt in `wp_booking_payments` and routes refund requests to the correct gateway class. Does not talk to any gateway directly — it delegates to `WPBS_PayPal_Gateway` or `WPBS_Stripe_Gateway`.

### `WPBS_Email`

Uses `ob_start()` / `include` / `ob_get_clean()` to render PHP email templates. Sends via `wp_mail()` so site-level SMTP configuration is respected automatically.

### `WPBS_REST_API`

Registers all routes under `wp-booking-system/v1`. Permission callbacks: `admin_permission` requires `manage_options`; `customer_permission` requires `is_user_logged_in()`; `booking_permission` checks ownership (customer can only read/cancel their own bookings). No logic lives in this class — it delegates to model classes.

---

## Data Flow: Booking Creation

```
Browser (JS)
  │  POST /wp-admin/admin-ajax.php  action=wpbs_public_action
  │  sub_action=create_booking
  │
WPBS_Public::ajax_create_booking()
  ├─ Nonce verify (wpbs_public_nonce)
  ├─ is_user_logged_in() check
  ├─ WPBS_Booking::create($data)
  │    ├─ WPBS_Service::get($service_id)     validate service exists
  │    ├─ slot_is_taken() overlap check
  │    └─ $wpdb->insert()                    write to wp_bookings
  └─ do_action('wpbs_booking_created', $booking)
       └─ WPBS_Email::send_booking_confirmation()
```

## Data Flow: Stripe Payment

```
Browser
  │  POST REST /payments/stripe/create-intent  {booking_id}
  │
WPBS_REST_API::stripe_create_intent()
  └─ WPBS_Stripe_Gateway::create_payment_intent()
       ├─ POST https://api.stripe.com/v1/payment_intents
       ├─ WPBS_Payment::record()  ← status: pending
       └─ returns {client_secret, publishable_key}

Browser (Stripe.js)
  └─ stripe.confirmCardPayment(client_secret)
       └─ on success: POST REST /payments/stripe/confirm  {payment_intent_id, booking_id}

WPBS_REST_API::stripe_confirm_payment()
  └─ WPBS_Stripe_Gateway::confirm_payment()
       ├─ GET https://api.stripe.com/v1/payment_intents/{id}
       ├─ verify status === 'succeeded'
       ├─ WPBS_Payment::update()          ← status: completed
       ├─ WPBS_Booking::update_payment_status() ← paid
       ├─ WPBS_Booking::update_status()   ← confirmed
       └─ do_action('wpbs_payment_completed', $booking, $payment)
            └─ WPBS_Email::send_payment_confirmation()
```

---

## WordPress Hooks Reference

### Actions fired by this plugin

| Action | When | Arguments |
|---|---|---|
| `wpbs_booking_created` | New booking inserted | `$booking` (array) |
| `wpbs_booking_status_updated` | Status changed | `$booking`, `$new_status` |
| `wpbs_payment_completed` | Payment confirmed | `$booking`, `$payment` |
| `wpbs_payment_refunded` | Refund processed | `$payment`, `$amount`, `$result` |

### Filters (planned extension points)

| Filter | Purpose |
|---|---|
| `wpbs_available_slots` | Modify the final slot list before returning to client |
| `wpbs_booking_data` | Modify booking data array before INSERT |
| `wpbs_email_template` | Override an email template path |
| `wpbs_rest_booking_response` | Modify REST API booking response shape |

> These filters are the intended extension surface. Third-party code should use these rather than modifying plugin files directly.

---

## Extension Points

### Adding a payment gateway

1. Create `includes/gateways/class-mypay-gateway.php` implementing `create_order()`, `capture_order()`, and `refund()` with the same return shape as the existing gateways (`['success' => bool, 'message' => string, ...]`).
2. Add REST routes in `WPBS_REST_API` for your gateway's flow.
3. Add the payment option to `public/partials/booking-form.php`.
4. Add credentials fields to the **Settings → Your Gateway** tab.

### Custom email templates

Copy any template from `templates/emails/` to your theme:

```
your-theme/
└── wp-booking-system/
    └── emails/
        └── booking-confirmation.php
```

The plugin resolves templates with `locate_template()` first (not yet wired — planned for 1.1.0). Until then, override via the `wpbs_email_template` filter.

### Blocking availability programmatically

```php
add_action( 'init', function() {
    // Block all services on a specific date
    WPBS_Availability::block_slot([
        'service_id' => null,   // null = all services
        'start_date' => '2025-12-25',
        'end_date'   => '2025-12-25',
        'reason'     => 'Holiday',
    ]);
});
```

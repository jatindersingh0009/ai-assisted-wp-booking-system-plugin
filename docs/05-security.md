# Security

[← Payment Gateways](04-payment-gateways.md) · [Deployment →](06-deployment.md)

---

## Security Model

Every request — AJAX, REST, or form submission — passes through three independent gates:

```
Request → [1] Authentication → [2] Authorization → [3] Input Validation → Handler
```

A failure at any gate halts the request with an appropriate HTTP error before any business logic executes.

---

## Authentication

### WordPress cookies (default)

All AJAX and REST endpoints are protected by WordPress's cookie-based session system. `check_ajax_referer()` validates the nonce and confirms a logged-in session. REST endpoints validate the nonce via the `X-WP-Nonce` header.

### Nonce strategy

Two nonces are in use:

| Nonce | Scope | Used by |
|---|---|---|
| `wpbs_admin_nonce` | Admin AJAX actions | `class-admin.php` AJAX handler |
| `wpbs_public_nonce` | Customer AJAX actions | `class-public.php` AJAX handler |
| `wp_rest` | REST API requests | `X-WP-Nonce` header on all REST calls |
| `wpbs_admin_action` | Admin form submissions | Bulk booking actions |
| `wpbs_service_action` | Service CRUD forms | Service add/edit/delete |
| `wpbs_settings_save` | Settings form | All settings tabs |
| `wpbs_refund_action` | Refund form | Payment refund submission |
| `wpbs_export_csv` | CSV export link | Booking export |

Each nonce is single-purpose. A nonce issued for the settings form cannot be replayed against the booking bulk-action handler.

---

## Authorization

### Capability checks

| Action | Check |
|---|---|
| View/manage all bookings | `manage_options` |
| Create/edit/delete services | `manage_options` |
| View/manage payments | `manage_options` |
| Change settings | `manage_options` |
| View booking reports | `manage_options` |
| Create a booking | `is_user_logged_in()` |
| View own booking | `is_user_logged_in()` + owner check |
| Cancel own booking | `is_user_logged_in()` + owner check + cancellation window |

### Ownership verification

Customer-facing endpoints verify that the requested booking belongs to the authenticated user:

```php
if ( (int) $booking['customer_id'] !== get_current_user_id() ) {
    wp_send_json_error( ['message' => 'Unauthorized.'], 403 );
}
```

This check runs in addition to nonce verification — not as a replacement.

---

## Input Validation and Sanitization

Every piece of data entering the system is processed through WordPress sanitization functions before being used in queries or stored.

| Data type | Function used |
|---|---|
| Text fields | `sanitize_text_field()` |
| Email addresses | `sanitize_email()` |
| Integers / IDs | `absint()` |
| Float amounts | `(float)` cast with explicit range checks |
| HTML content | `wp_kses_post()` |
| Textarea | `sanitize_textarea_field()` |
| URL parameters | `sanitize_text_field()` + explicit allowlist for status values |

Status values (booking_status, payment_status) are validated against an explicit allowlist before any DB write:

```php
$allowed = ['pending', 'confirmed', 'cancelled', 'completed', 'rejected', 'rescheduled'];
if ( ! in_array( $status, $allowed, true ) ) {
    return false;
}
```

---

## Output Escaping

All output uses the appropriate contextual escaping function:

| Output context | Function |
|---|---|
| HTML content | `esc_html()` |
| HTML attributes | `esc_attr()` |
| URLs | `esc_url()` |
| JavaScript inline values | `esc_js()` / `wp_json_encode()` |
| SQL (via `$wpdb`) | `$wpdb->prepare()` |
| Textarea content | `esc_textarea()` |

---

## SQL Injection Prevention

All database queries use `$wpdb->prepare()` with type specifiers. There are no string-concatenated SQL queries. Example from the overlap check:

```php
$count = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}bookings
         WHERE service_id = %d
           AND booking_date = %s
           AND booking_status NOT IN ('cancelled', 'rejected')
           AND booking_time < %s
           AND end_time > %s",
        $service_id, $date, $end, $start
    )
);
```

Table names (prefix + table name) are interpolated directly — not through `prepare()`, because `prepare()` only handles value placeholders. These interpolations are constructed exclusively from the controlled prefix constant, never from user input.

---

## Webhook Verification

### Stripe

Stripe signs each webhook with HMAC-SHA256 using the webhook signing secret. The plugin verifies:

1. The `Stripe-Signature` header is present
2. The `t=` timestamp is within 5 minutes of `time()` (replay attack prevention)
3. The `v1=` HMAC matches `HMAC-SHA256(timestamp + "." + raw_body, signing_secret)`

```php
$signed_payload = $timestamp . '.' . $payload;
$expected       = hash_hmac( 'sha256', $signed_payload, $this->webhook_secret );
return hash_equals( $expected, $sig );  // timing-safe comparison
```

`hash_equals()` is used (not `===`) to prevent timing attacks.

### PayPal

PayPal webhook verification uses PayPal's own API endpoint (`/v1/notifications/verify-webhook-signature`). This sends the raw body and all PayPal-generated headers back to PayPal for server-side signature verification. The expected response is `{ "verification_status": "SUCCESS" }`.

If a Webhook ID is not configured in Settings, signature verification is skipped. **Configure the Webhook ID in production.**

---

## Secure Payment Data Handling

- No card numbers, CVCs, or full PANs ever touch the plugin's server
- Stripe card capture happens entirely within Stripe.js; the plugin server receives only a PaymentIntent ID
- PayPal handles card data on its own hosted checkout page
- Gateway API keys are stored in WordPress options; never exposed to the browser or logged
- The `gateway_response` column (raw JSON from the gateway) is stored for dispute evidence but never rendered in the UI

---

## HTTPS Requirement

Both PayPal and Stripe require HTTPS on the receiving end. The plugin does not enforce HTTPS itself, but the payment flows will fail on HTTP:

- Stripe.js refuses to load on non-HTTPS origins
- PayPal's approval redirect requires a secure return URL

Ensure your site has a valid SSL certificate before processing real payments.

---

## Multisite Considerations

The plugin is network-activatable. On multisite:
- Each sub-site has its own DB tables and settings (no cross-site data leakage)
- Activation creates tables per-blog using `switch_to_blog()`
- Admin capabilities (`manage_options`) are per-blog — a sub-site admin cannot access another site's data

---

## Recommendations for Production

| Action | Priority |
|---|---|
| Configure PayPal Webhook ID | High |
| Configure Stripe Webhook Secret | High |
| Use a dedicated `FROM` email with SPF/DKIM | High |
| Enable WordPress debug log only on staging | High |
| Restrict wp-admin access by IP if possible | Medium |
| Rotate API keys if they appear in logs or version control | High |
| Review `wpbs_delete_data_on_uninstall` before deleting the plugin | Low |

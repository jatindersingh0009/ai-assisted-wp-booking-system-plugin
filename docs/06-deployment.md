# Deployment Guide

[← Security](05-security.md) · [README →](../README.md)

---

## Server Requirements

| Requirement | Minimum | Recommended |
|---|---|---|
| PHP | 8.0 | 8.2+ |
| WordPress | 6.0 | 6.5+ |
| MySQL | 5.7 | 8.0 |
| MariaDB | 10.3 | 10.6 |
| HTTPS | Required | Required |
| `allow_url_fopen` | Enabled | Enabled |
| cURL extension | Enabled | Enabled |
| WordPress cron | WP-Cron or server cron | Server cron |
| Memory limit | 128 MB | 256 MB |

---

## Installation Checklist

### Step 1 — Upload

```bash
# Via SSH / SFTP
cp -r wp-booking-system/ /var/www/html/wp-content/plugins/

# Or via WP-CLI
wp plugin install wp-booking-system.zip --activate
```

### Step 2 — Activate

Go to **Plugins → Installed Plugins** and activate **WP Booking System**.

Activation creates:
- Tables: `wp_bookings`, `wp_booking_services`, `wp_booking_payments`, `wp_booking_availability`
- Pages: **Book Appointment**, **My Bookings**, **Booking Confirmed**
- Options: all default settings
- WP-Cron schedules: `wpbs_send_booking_reminders` (daily), `wpbs_cleanup_expired_bookings` (daily)

### Step 3 — General Settings

**Bookings → Settings → General**

| Setting | Value |
|---|---|
| Timezone | Set to your business timezone |
| Currency Code | Three-letter ISO code (USD, GBP, EUR, …) |
| Currency Symbol | Matching symbol ($, £, €, …) |
| Admin Email | Where admin notifications go |
| From Name | Sender name for customer emails |
| From Email | Authenticated sending address (ideally SPF/DKIM-signed) |

### Step 4 — Business Hours

**Bookings → Settings → Business Hours**

Enable each day your business operates. Set open and close times. Times are interpreted in the timezone set in Step 3.

### Step 5 — Booking Rules

**Bookings → Settings → Booking Rules**

| Rule | Default | Guidance |
|---|---|---|
| Minimum advance (hours) | 2 | Prevents same-hour bookings. Set higher for services requiring preparation. |
| Maximum advance (days) | 60 | Controls how far into the future customers can book. |
| Cancellation window (hours) | 24 | Minimum hours before appointment time that a customer can cancel. |
| Slot interval (minutes) | 30 | Gap between time slot start times. Usually matches service duration. |

### Step 6 — Add Services

**Bookings → Services → Add New**

Add at least one service before testing the booking form. Required fields: name, duration, price.

### Step 7 — Payment Gateways

Follow the [Payment Gateways guide](04-payment-gateways.md) to configure credentials and webhooks.

Start with sandbox/test credentials and complete an end-to-end test booking before going live.

### Step 8 — Test End-to-End

1. Log in as a non-admin user
2. Visit **Book Appointment**
3. Select service → date → time → notes
4. Pay with a test card / sandbox PayPal account
5. Verify the booking appears in **All Bookings** with status `confirmed`
6. Verify you received a booking confirmation email and a payment confirmation email
7. Go to **All Bookings → [View]** and process a test refund

### Step 9 — Go Live

1. Switch PayPal mode to **Live**, update credentials
2. Switch Stripe mode to **Live**, update credentials
3. Register live webhook endpoints with both gateways
4. Test with a real low-value transaction

---

## WordPress Cron

The plugin relies on WP-Cron for two scheduled tasks:

| Hook | Schedule | Job |
|---|---|---|
| `wpbs_send_booking_reminders` | Daily at ~08:00 | Sends reminder emails for tomorrow's confirmed bookings |
| `wpbs_cleanup_expired_bookings` | Daily | Cancels `pending` bookings older than 2 hours with no payment |

WP-Cron fires when a page is loaded. On low-traffic sites, cron jobs may not fire on time.

### Recommended: System cron

Add to the server's crontab to trigger WP-Cron reliably, bypassing the page-load requirement:

```bash
# Fire WordPress cron every 5 minutes
*/5 * * * * wget -q -O - https://yourdomain.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
```

Or using WP-CLI:

```bash
*/5 * * * * cd /var/www/html && wp cron event run --due-now --quiet
```

Then disable the browser-triggered cron by adding to `wp-config.php`:

```php
define( 'DISABLE_WP_CRON', true );
```

---

## Database Maintenance

### Check tables exist

```bash
wp db query "SHOW TABLES LIKE '%booking%';"
```

Expected output:
```
wp_booking_availability
wp_booking_payments
wp_booking_services
wp_bookings
```

### Manual table recreation

If tables are missing (e.g., after a database restore), trigger recreation by bumping the DB version:

```bash
wp option delete wpbs_db_version
wp eval "do_action('plugins_loaded');"
```

Or simply deactivate and reactivate the plugin.

### Backup before updates

Always back up the four booking tables before plugin updates:

```bash
wp db export --tables=wp_bookings,wp_booking_services,wp_booking_payments,wp_booking_availability backup-$(date +%Y%m%d).sql
```

---

## Performance Considerations

### Caching availability

`WPBS_Availability::get_available_slots()` runs several queries per call. On high-traffic booking pages:

1. Cache the result in a transient keyed by `service_id + date`
2. Invalidate the transient when a booking is created or cancelled for that service+date

```php
add_action( 'wpbs_booking_created', function( $booking ) {
    delete_transient( 'wpbs_slots_' . $booking['service_id'] . '_' . $booking['booking_date'] );
} );
```

### Index review (large installations)

For 50,000+ bookings, add a covering index on the reporting query:

```sql
ALTER TABLE wp_bookings
    ADD INDEX idx_report (service_id, booking_date, amount, payment_status);
```

### Object caching

If your hosting provides a persistent object cache (Redis, Memcached), `get_option()` calls for settings are automatically cached. No plugin changes required.

---

## Uninstall and Data Removal

The plugin only deletes data if you explicitly opt in:

```bash
wp option update wpbs_delete_data_on_uninstall 1
```

Then delete the plugin from **Plugins → Installed Plugins → Delete**.

Without this option set, deactivating or deleting the plugin leaves all booking data intact. This is the safe default — data is your client's business records.

---

## Multisite Deployment

### Per-site activation (recommended)

Activate from each sub-site's **Plugins** screen. Each site gets its own tables.

### Network activation

Activate from the Network Admin **Plugins** screen. Tables are created for every existing site at activation time. New sites added to the network will not automatically get tables — run activation per-site or hook `wpmu_new_blog` to call `WPBS_Activator::activate()`.

---

## Environment Variable Reference

All settings are stored as WordPress options. There are no `.env` file dependencies. For automated deployments, set options via WP-CLI after activation:

```bash
wp option update wpbs_paypal_mode 'live'
wp option update wpbs_paypal_client_id 'AYour...'
wp option update wpbs_paypal_secret 'EYour...'
wp option update wpbs_stripe_mode 'live'
wp option update wpbs_stripe_publishable_key 'pk_live_...'
wp option update wpbs_stripe_secret_key 'sk_live_...'
wp option update wpbs_stripe_webhook_secret 'whsec_...'
wp option update wpbs_timezone 'America/New_York'
wp option update wpbs_currency 'USD'
wp option update wpbs_currency_symbol '$'
```

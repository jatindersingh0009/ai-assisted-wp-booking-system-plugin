# WP Booking System

**Version**: 1.0.0  
**Requires WordPress**: 6.0+  
**Requires PHP**: 8.0+  
**License**: GPL v2 or later  
**Text Domain**: `wp-booking-system`

A production-ready WordPress booking and appointment plugin. Customers select a service, choose an available slot, and pay through PayPal or Stripe — all without leaving your site. Administrators manage every booking, payment, and schedule from the WordPress dashboard.

---

## Features

### Customer-Facing
- **Multi-step booking form** (service → date → time → payment → confirmation)
- **Live availability calendar** — only shows genuinely bookable dates
- **PayPal Checkout** and **Stripe Card** payment options
- **Customer dashboard** — upcoming bookings, history, cancellation, invoice download
- **Email notifications** — booking confirmation, payment receipt, appointment reminder, cancellation

### Administrator
- **Booking management** — list view with search, date filters, status filters, bulk actions
- **Interactive calendar** — FullCalendar month/week/day views, color-coded by status
- **Service management** — name, duration, capacity (concurrent bookings per slot), price, category
- **Payment reports** — per-gateway totals, refund processing, CSV export
- **Revenue reports** — daily chart, by-service breakdown, date range filtering
- **Settings** — business hours per day, timezone, booking rules, PayPal/Stripe credentials
- **Refund processing** — trigger full or partial refunds from the booking detail page

### Technical
- 4 custom database tables with proper indexes and foreign key semantics
- REST API — 16 versioned endpoints under `/wp-json/wp-booking-system/v1/`
- Multisite compatible (per-site activation)
- WP-Cron jobs for appointment reminders and expired-booking cleanup
- PayPal and Stripe webhook endpoints at clean URLs (no `admin-ajax.php`)

---

## Quick Start

### 1 — Install

Upload the `wp-booking-system/` folder to `wp-content/plugins/` and activate from **Plugins → Installed Plugins**.

On activation the plugin:
- Creates 4 database tables (`wp_bookings`, `wp_booking_services`, `wp_booking_payments`, `wp_booking_availability`)
- Publishes three pages: **Book Appointment**, **My Bookings**, **Booking Confirmed**
- Registers two WP-Cron schedules (daily reminder, daily cleanup)
- Seeds default business hours, booking rules, and option defaults

### 2 — Add a service

Go to **Bookings → Services → Add New**. At minimum set a name, duration, and price. Save.

### 3 — Configure payment

Go to **Bookings → Settings**:
- **PayPal tab** — paste your sandbox Client ID and Secret, set mode to Sandbox
- **Stripe tab** — paste your test Publishable Key and Secret Key, set mode to Test

### 4 — Test a booking

Visit the **Book Appointment** page as a logged-in non-admin user. Complete the 5-step flow. Verify the booking appears in **Bookings → All Bookings** and that confirmation emails arrive.

### 5 — Go live

Flip both gateways to Live mode, paste live credentials, and register the webhook URLs (see [Payment Gateway Integration](docs/04-payment-gateways.md)).

---

## Shortcodes

| Shortcode | Description |
|---|---|
| `[wpbs_booking_form]` | 5-step booking form — place on any page |
| `[wpbs_customer_dashboard]` | Customer's booking history and management |
| `[wpbs_booking_confirmation]` | Post-payment confirmation screen |

All three shortcodes require the visitor to be logged in; unauthenticated users see a login prompt.

---

## Documentation Index

| Document | Contents |
|---|---|
| [Architecture](docs/01-architecture.md) | Plugin structure, class map, design patterns, extension points |
| [Database Schema](docs/02-database.md) | All 4 tables — columns, types, indexes, relationships |
| [REST API Reference](docs/03-api-reference.md) | All 16 endpoints with request/response examples |
| [Payment Gateways](docs/04-payment-gateways.md) | PayPal & Stripe integration, webhook setup, refunds, testing |
| [Security](docs/05-security.md) | Input validation, authentication, SQL safety, webhook verification |
| [Deployment Guide](docs/06-deployment.md) | Server requirements, configuration checklist, cron, performance |

---

## Minimum Server Requirements

| Requirement | Minimum |
|---|---|
| PHP | 8.0 |
| WordPress | 6.0 |
| MySQL | 5.7 or MariaDB 10.3 |
| HTTPS | Required (payment gateways mandate it) |
| `allow_url_fopen` or cURL | Required for PayPal/Stripe API calls |

---

## Plugin File Map (top level)

```
wp-booking-system/
├── wp-booking-system.php     ← Entry point, constants, activation hooks
├── uninstall.php             ← Runs only on plugin deletion
├── includes/                 ← Business logic (no WordPress UI)
├── admin/                    ← Admin dashboard (menus, screens, AJAX)
├── public/                   ← Shortcodes, customer-facing AJAX
├── templates/emails/         ← HTML email templates
└── languages/                ← POT file for i18n
```

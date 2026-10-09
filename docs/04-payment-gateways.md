# Payment Gateways

[← API Reference](03-api-reference.md) · [Security →](05-security.md)

---

## Overview

Both gateways use the plugin's own HTTP client (`wp_remote_post` / `wp_remote_get`) — no external SDK required. All credentials are stored in WordPress options and never exposed to the frontend.

| | PayPal | Stripe |
|---|---|---|
| API version | REST API v2 | 2024-06-20 |
| Flow type | Redirect (hosted checkout) | Card element (on-page) |
| Webhook path | `/wpbs-webhook/paypal/` | `/wpbs-webhook/stripe/` |
| Sandbox/Test | Supported | Supported |
| Refunds | Full and partial | Full and partial |

---

## PayPal Integration

### Credentials

1. Log in to [developer.paypal.com](https://developer.paypal.com)
2. Go to **Apps & Credentials**
3. Create a new app (or use the default sandbox app)
4. Copy the **Client ID** and **Secret** for the sandbox environment
5. In WordPress: **Bookings → Settings → PayPal** — paste credentials, set Mode to Sandbox

### Payment Flow

```
Customer clicks "Pay Now" (PayPal selected)
  │
  ├─ Frontend: POST /payments/paypal/create-order {booking_id}
  │
  ├─ Plugin: POST https://api-m.sandbox.paypal.com/v2/checkout/orders
  │    {intent: CAPTURE, purchase_units: [{amount, description, reference_id}]}
  │
  ├─ PayPal returns: {id: "ORDER_ID", links: [{rel: "approve", href: "..."}]}
  │
  ├─ Plugin: records PENDING payment row, returns approval_url to frontend
  │
  ├─ Frontend: window.location.href = approval_url
  │         (customer on PayPal's hosted page)
  │
  ├─ Customer approves → PayPal redirects to return_url with ?token=ORDER_ID
  │
  ├─ Frontend: POST /payments/paypal/capture {order_id, booking_id}
  │
  ├─ Plugin: POST https://api-m.sandbox.paypal.com/v2/checkout/orders/{id}/capture
  │
  └─ Plugin: updates payment → completed, booking → confirmed, sends emails
```

### Webhook Setup (strongly recommended)

Webhooks protect against cases where the customer's browser closes before the capture step completes.

1. In the PayPal Developer Dashboard, go to your app → **Webhooks → Add Webhook**
2. Set the URL to: `https://yourdomain.com/wpbs-webhook/paypal/`
3. Subscribe to: `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.REFUNDED`
4. Copy the **Webhook ID** into **Bookings → Settings → PayPal → Webhook ID**

The plugin verifies webhook signatures using PayPal's `/v1/notifications/verify-webhook-signature` endpoint. If the Webhook ID is not configured, signature verification is skipped (permissive fallback for development).

### Going Live

1. Change mode from **Sandbox** to **Live** in Settings → PayPal
2. Replace credentials with live app credentials from developer.paypal.com
3. Register the live webhook at the same URL

---

## Stripe Integration

### Credentials

1. Log in to [dashboard.stripe.com](https://dashboard.stripe.com)
2. Toggle **Test mode** on
3. Go to **Developers → API keys**
4. Copy the **Publishable key** (starts `pk_test_`) and **Secret key** (starts `sk_test_`)
5. In WordPress: **Bookings → Settings → Stripe** — paste both keys, set Mode to Test

The publishable key is printed to the frontend (it's safe to expose); the secret key never leaves the server.

### Payment Flow

```
Customer clicks "Pay Now" (Stripe selected)
  │
  ├─ Frontend: POST REST /payments/stripe/create-intent {booking_id}
  │
  ├─ Plugin: POST https://api.stripe.com/v1/payment_intents
  │    {amount (cents), currency, metadata: {booking_id, booking_ref}}
  │
  ├─ Plugin: records PENDING payment row, returns {client_secret, publishable_key}
  │
  ├─ Frontend (Stripe.js):
  │    stripe.confirmCardPayment(client_secret, {payment_method: {card: cardElement}})
  │
  ├─ Stripe processes the card charge
  │
  ├─ Frontend: on success, POST REST /payments/stripe/confirm
  │    {payment_intent_id, booking_id}
  │
  └─ Plugin: verifies PaymentIntent status = 'succeeded', updates records, sends emails
```

### Webhook Setup

Webhooks fire independently of the browser — essential for confirming payments if the tab closes after `stripe.confirmCardPayment` succeeds but before the confirm API call.

1. Stripe Dashboard → **Developers → Webhooks → Add endpoint**
2. URL: `https://yourdomain.com/wpbs-webhook/stripe/`
3. Select events: `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`
4. After saving, reveal the **Signing secret** (starts `whsec_`)
5. Paste it into **Bookings → Settings → Stripe → Webhook Secret**

The plugin verifies Stripe webhook signatures using HMAC-SHA256 and rejects events with a timestamp older than 5 minutes (replay attack prevention). If the Webhook Secret is not configured, signature verification is skipped.

### Going Live

1. Change mode to **Live** in Settings → Stripe
2. Replace test keys with live keys from the Stripe dashboard (without Test mode toggled)
3. Create a separate live webhook endpoint and paste the live signing secret

---

## Refunds

Refunds are initiated from the **Bookings → All Bookings → [View Booking]** page or via the REST API.

```php
// Via REST API (admin only)
POST /wp-json/wp-booking-system/v1/payments/{payment_id}/refund
{ "amount": 37.50 }
```

The refund flow:

1. Validate: payment must be `completed`, amount ≤ remaining balance
2. Call gateway's `refund()` method with the transaction ID and amount
3. On success: update `refund_amount` and `refund_id` in `wp_booking_payments`
4. Update `payment_status` on the booking to `refunded` or `partially_refunded`
5. Fire `do_action('wpbs_payment_refunded', $payment, $amount, $result)`

**PayPal refunds** use `/v2/payments/captures/{capture_id}/refund`.  
**Stripe refunds** retrieve the charge ID from the PaymentIntent, then call `/v1/refunds`.

---

## Testing Credentials

### PayPal Sandbox

Use the buyer and seller sandbox accounts from developer.paypal.com. The sandbox buyer email is typically `sb-XXXXX@personal.example.com`.

Test card (PayPal hosted page): use any Visa number starting with `4` and a future expiry.

### Stripe Test Cards

| Card | Result |
|---|---|
| `4242 4242 4242 4242` | Succeeds |
| `4000 0000 0000 0002` | Declined |
| `4000 0025 0000 3155` | Requires 3D Secure authentication |
| `4000 0000 0000 9995` | Insufficient funds |

Use any future expiry date, any 3-digit CVC, and any 5-digit zip code.

---

## Currency Configuration

Set your currency code and symbol in **Bookings → Settings → General**.

```
Currency Code:   USD
Currency Symbol: $
```

> **Important:** PayPal amounts are submitted as decimal strings (`"75.00"`). Stripe amounts are submitted as integers in the smallest currency unit — the plugin multiplies by 100 automatically. If you switch to a zero-decimal currency (JPY, KRW), you must modify `create_payment_intent()` to not multiply by 100.

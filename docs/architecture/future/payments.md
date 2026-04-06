# Payments (Planned)

Status: Planned (not implemented)
Source: Archived package prophoto-payments
Last reviewed: 2026-04
Purpose:
Stripe-based payment processing — checkout sessions, webhook handling, payment-to-invoice reconciliation, and refund processing.

Notes:
- This is NOT an active package
- Do not implement without revisiting this spec
- Depends on prophoto-invoicing being further developed first

---

# ProPhoto Payments

Payment processing for ProPhoto using Stripe.

## Purpose

Handles payment lifecycle:
- Create Stripe checkout sessions
- Process webhooks (payment.succeeded, etc.)
- Reconcile payments to invoices
- Refund processing
- Payment intent retries

## Key Features

### Stripe Integration
- Checkout session creation
- Webhook signature verification
- Payment status sync
- Customer portal access
- Saved payment methods

### Payment Reconciliation
- Match Stripe payment → Invoice
- Update invoice status on payment
- Handle partial payments
- Track payment attempts

### Refund Processing
- Full or partial refunds
- Refund reasons
- Update invoice status
- Notify client

## Configuration

```php
return [
    'stripe' => [
        'key' => env('STRIPE_SECRET_KEY'),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
    'currency' => 'usd',
    'payment_methods' => ['card', 'bank_transfer'],
];
```

## Dependencies

- `prophoto/contracts` - Payment contracts
- `prophoto/invoicing` - Update invoice status
- `stripe/stripe-php` - Stripe SDK


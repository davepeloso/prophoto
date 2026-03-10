# ProPhoto Invoicing

Invoice generation and management for ProPhoto studios.

## Purpose

Creates and manages invoices for:
- Session fees
- Print orders
- Digital downloads
- Custom line items
- Tax calculation
- PDF generation

## Key Features

### Invoice Builder
- Auto-populate from sessions
- Manual line items
- Tax rules (per state/region)
- Discount codes
- Deposit tracking

### PDF Generation
- Branded templates
- Studio logo and colors
- Itemized breakdown
- Payment instructions
- QR code for Stripe payment

### Status Tracking
- Draft → Sent → Viewed → Paid → Overdue
- Automatic reminders
- Payment reconciliation

## Configuration

```php
return [
    'numbering' => [
        'prefix' => 'INV-',
        'format' => '{year}-{sequential}', // INV-2024-00123
    ],
    'tax' => [
        'rate' => 0.08, // 8%
        'label' => 'Sales Tax',
    ],
    'payment_terms' => 'Net 30',
];
```

## Dependencies

- `prophoto/contracts` - Invoice contracts
- `prophoto/payments` - Payment processing
- `dompdf` - PDF generation


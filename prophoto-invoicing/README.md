# ProPhoto Invoicing

## Purpose

Invoice generation and management for photography studios. Owns the invoice lifecycle from creation through PDF generation. Invoices are scoped to studios and organizations via prophoto-access models. This package does not handle payment processing — that is a separate concern (see `docs/architecture/future/payments.md`).

## Responsibilities

- Invoice model (invoices scoped to studio + organization)
- InvoiceItem model (line items within invoices)
- CustomFee model (reusable fee templates for studios)
- InvoicePolicy (authorization using prophoto-access permissions and roles)
- Invoice numbering, tax calculation, and PDF generation (via dompdf)

## Non-Responsibilities

- Does NOT process payments — payment processing is a planned future package (see `docs/architecture/future/payments.md`)
- Does NOT own studio or organization models — depends on prophoto-access for Studio and Organization
- Does NOT own session or booking data — session fees reference bookings but do not query booking tables directly
- Does NOT participate in the ingest → assets → intelligence event loop
- Does NOT mutate ingest, asset, or booking state

## Integration Points

- **Events listened to:** None currently
- **Events emitted:** None currently (future: InvoiceSent, InvoicePaid)
- **Contracts depended on:** `prophoto/contracts` (shared DTOs/enums)
- **Model relationships:** Invoice→Studio (belongs to), Invoice→Organization (belongs to) — both models from prophoto-access

## Data Ownership

| Table | Model | Purpose |
|---|---|---|
| `invoices` | Invoice | Invoice records scoped to studio + organization |
| `invoice_items` | InvoiceItem | Line items within invoices |
| `custom_fees` | CustomFee | Reusable fee templates |

## Notes

- ServiceProvider is declared in composer.json (`ProPhoto\Invoicing\InvoicingServiceProvider`) but the file does not yet exist — needs implementation
- Uses `barryvdh/laravel-dompdf` for PDF generation

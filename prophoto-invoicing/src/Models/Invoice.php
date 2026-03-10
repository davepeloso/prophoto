<?php

namespace ProPhoto\Invoicing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ProPhoto\Access\Models\Studio;
use ProPhoto\Access\Models\Organization;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'studio_id',
        'organization_id',
        'invoice_number',
        'quote_number',
        'status',
        'stripe_invoice_id',
        'issued_at',
        'due_at',
        'paid_at',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'payment_method',
        'payment_reference',
        'payment_notes',
        'po_number',
        'notes',
        'client_notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'due_at' => 'date',
        'paid_at' => 'date',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Invoice status constants.
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_QUOTE = 'quote';
    public const STATUS_SENT = 'sent';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Payment method constants.
     */
    public const PAYMENT_STRIPE = 'stripe';
    public const PAYMENT_BANK_TRANSFER = 'bank_transfer';
    public const PAYMENT_CHECK = 'check';
    public const PAYMENT_WIRE = 'wire';
    public const PAYMENT_CASH = 'cash';

    /**
     * Get the studio that owns this invoice.
     */
    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    /**
     * Get the organization that owns this invoice.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user who created this invoice.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by_user_id');
    }

    /**
     * Get the line items for this invoice.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Check if the invoice is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Check if the invoice is overdue.
     */
    public function isOverdue(): bool
    {
        if ($this->status === self::STATUS_OVERDUE) {
            return true;
        }

        return $this->status === self::STATUS_SENT
            && $this->due_at
            && $this->due_at->isPast();
    }

    /**
     * Calculate totals from line items.
     */
    public function calculateTotals(): void
    {
        $subtotal = $this->items()->sum('total');
        $taxAmount = $this->tax_rate > 0 ? $subtotal * ($this->tax_rate / 100) : 0;

        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
        ]);
    }

    /**
     * Mark invoice as paid.
     */
    public function markAsPaid(string $paymentMethod, ?string $reference = null, ?string $notes = null): void
    {
        $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
            'payment_method' => $paymentMethod,
            'payment_reference' => $reference,
            'payment_notes' => $notes,
        ]);
    }
}

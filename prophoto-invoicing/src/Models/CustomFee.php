<?php

namespace ProPhoto\Invoicing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CustomFee extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'type',
        'description',
        'quantity',
        'unit_price',
        'calculation_data',
        'created_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'calculation_data' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Fee type constants.
     */
    public const TYPE_MILEAGE = 'mileage';
    public const TYPE_POST_PROCESSING = 'post_processing';
    public const TYPE_TRAVEL = 'travel';
    public const TYPE_SECOND_SHOOTER = 'second_shooter';
    public const TYPE_ASSISTANT = 'assistant';
    public const TYPE_EQUIPMENT = 'equipment';
    public const TYPE_INSURANCE = 'insurance';

    /**
     * Get the invoice items for this custom fee.
     */
    public function invoiceItems(): MorphMany
    {
        return $this->morphMany(InvoiceItem::class, 'itemable');
    }

    /**
     * Calculate the total for this fee.
     */
    public function getTotalAttribute(): float
    {
        return $this->quantity * $this->unit_price;
    }

    /**
     * Create a mileage fee.
     */
    public static function createMileage(float $miles, float $rate = 0.66, string $from = null, string $to = null): self
    {
        return static::create([
            'type' => self::TYPE_MILEAGE,
            'description' => $from && $to
                ? "Mileage: {$from} to {$to} ({$miles} miles @ \${$rate}/mile)"
                : "Mileage: {$miles} miles @ \${$rate}/mile",
            'quantity' => $miles,
            'unit_price' => $rate,
            'calculation_data' => [
                'miles' => $miles,
                'rate' => $rate,
                'from' => $from,
                'to' => $to,
            ],
            'created_at' => now(),
        ]);
    }
}

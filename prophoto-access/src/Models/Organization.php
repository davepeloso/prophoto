<?php

namespace ProPhoto\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'studio_id',
        'name',
        'type',
        'billing_email',
        'billing_phone',
        'billing_address',
        'billing_city',
        'billing_state',
        'billing_zip',
        'vendor_number',
        'insurance_code',
        'payment_terms',
        'tax_exempt',
        'settings',
        'permissions',
    ];

    protected $casts = [
        'tax_exempt' => 'boolean',
        'settings' => 'array',
        'permissions' => 'array',
    ];

    /**
     * Get the studio that owns this organization.
     */
    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    /**
     * Get the users belonging to this organization.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(config('auth.providers.users.model'), 'organization_user')
            ->withPivot(['role', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * Get the documents belonging to this organization.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(OrganizationDocument::class);
    }

    /**
     * Get the sessions belonging to this organization.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    /**
     * Get the galleries belonging to this organization.
     */
    public function galleries(): HasMany
    {
        return $this->hasMany(Gallery::class);
    }

    /**
     * Get the booking requests belonging to this organization.
     */
    public function bookingRequests(): HasMany
    {
        return $this->hasMany(BookingRequest::class);
    }

    /**
     * Get the invoices belonging to this organization.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Check if the organization has all required documents.
     */
    public function hasRequiredDocuments(): bool
    {
        $requiredTypes = ['insurance', 'w9'];

        foreach ($requiredTypes as $type) {
            $hasValid = $this->documents()
                ->where('type', $type)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->exists();

            if (!$hasValid) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the missing required documents.
     */
    public function getMissingRequiredDocuments(): array
    {
        $requiredTypes = ['insurance', 'w9'];
        $missing = [];

        foreach ($requiredTypes as $type) {
            $hasValid = $this->documents()
                ->where('type', $type)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->exists();

            if (!$hasValid) {
                $missing[] = config("prophoto-access.document_types.{$type}.label", ucfirst($type));
            }
        }

        return $missing;
    }

    /**
     * Get a setting value.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Get a permission value.
     */
    public function getPermission(string $key, bool $default = false): bool
    {
        return data_get($this->permissions, $key, $default);
    }
}

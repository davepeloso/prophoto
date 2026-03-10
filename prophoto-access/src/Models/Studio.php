<?php

namespace ProPhoto\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Studio extends Model
{
    protected $fillable = [
        'name',
        'subdomain',
        'business_name',
        'business_address',
        'business_city',
        'business_state',
        'business_zip',
        'business_phone',
        'business_email',
        'logo_url',
        'website_url',
        'timezone',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Get all users belonging to this studio.
     */
    public function users(): HasMany
    {
        return $this->hasMany(config('auth.providers.users.model'));
    }

    /**
     * Get all organizations belonging to this studio.
     */
    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }

    /**
     * Get all sessions belonging to this studio.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    /**
     * Get all galleries belonging to this studio.
     */
    public function galleries(): HasMany
    {
        return $this->hasMany(Gallery::class);
    }

    /**
     * Get all invoices belonging to this studio.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get all staging images belonging to this studio.
     */
    public function stagingImages(): HasMany
    {
        return $this->hasMany(StagingImage::class);
    }

    /**
     * Get a setting value from the settings JSON.
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Set a setting value in the settings JSON.
     */
    public function setSetting(string $key, mixed $value): self
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        return $this;
    }
}

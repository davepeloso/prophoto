<?php

namespace ProPhoto\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

class PermissionContext extends Model
{
    protected $fillable = [
        'user_id',
        'permission_id',
        'contextable_type',
        'contextable_id',
        'granted_at',
        'expires_at',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }

    public function contextable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }
}
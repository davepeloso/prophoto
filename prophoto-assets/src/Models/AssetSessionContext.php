<?php

namespace ProPhoto\Assets\Models;

use Illuminate\Database\Eloquent\Model;

class AssetSessionContext extends Model
{
    protected $table = 'asset_session_contexts';

    protected $fillable = [
        'asset_id',
        'session_id',
        'source_decision_id',
        'decision_type',
        'subject_type',
        'subject_id',
        'ingest_item_id',
        'confidence_tier',
        'confidence_score',
        'algorithm_version',
        'occurred_at',
    ];

    protected $casts = [
        'asset_id' => 'integer',
        'session_id' => 'integer',
        'confidence_score' => 'float',
        'occurred_at' => 'datetime',
    ];
}

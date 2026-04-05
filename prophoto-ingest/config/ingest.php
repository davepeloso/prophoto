<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Session Association
    |--------------------------------------------------------------------------
    |
    | Controls for ingest-owned asset/session association behavior.
    | Matching logic will be implemented later; keep these conservative.
    |
    */

    'session_association' => [
        'enabled' => true,
        'auto_assign_threshold' => 0.85,
        'proposal_threshold' => 0.55,
    ],

    /*
    |--------------------------------------------------------------------------
    | Matching
    |--------------------------------------------------------------------------
    |
    | Strategy/version values should be persisted into decision history so
    | matching behavior is auditable and replayable.
    |
    */

    'matching' => [
        'algorithm_version' => 'v1',
    ],

];

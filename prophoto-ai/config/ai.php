<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | The default provider key used when resolving from the registry.
    | Must match a key in the 'providers' array below.
    |
    */

    'default_provider' => env('AI_PROVIDER', 'astria'),

    /*
    |--------------------------------------------------------------------------
    | AI Generation Providers
    |--------------------------------------------------------------------------
    |
    | Each provider implements AiProviderContract and handles creative compute
    | (training models, generating images). Provider URLs are transient —
    | results must always be persisted to the storage layer before use.
    |
    */

    'providers' => [

        'astria' => [
            'enabled' => env('ASTRIA_ENABLED', true),
            'api_key' => env('ASTRIA_API_KEY'),
            'api_base_url' => env('ASTRIA_API_URL', 'https://api.astria.ai'),
            'max_generations_per_model' => 5,
            'default_images_per_prompt' => 8,
            'model_expiry_days' => 30,
            'training_cost_cents' => 150,
            'generation_cost_cents' => 23,
            'preset' => 'flux-lora-portrait',
            'model_type' => 'lora',
            'face_crop' => true,
            'default_negative_prompt' => 'double torso, totem pole, old, wrinkles, mole, blemish, (oversmoothed, 3d render), scar, sad, severe, 2d, sketch, painting, digital art, drawing, disfigured, elongated body, text, cropped, out of frame',
        ],

        // Future providers — uncomment and configure when ready:
        // 'fal' => [
        //     'enabled' => env('FAL_ENABLED', false),
        //     'api_key' => env('FAL_API_KEY'),
        // ],
        // 'magnific' => [
        //     'enabled' => env('MAGNIFIC_ENABLED', false),
        //     'api_key' => env('MAGNIFIC_API_KEY'),
        // ],
        // 'claid' => [
        //     'enabled' => env('CLAID_ENABLED', false),
        //     'api_key' => env('CLAID_API_KEY'),
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Storage / Delivery Layer
    |--------------------------------------------------------------------------
    |
    | The storage driver persists generated images permanently and serves them
    | via CDN with URL-based transforms (resize, bg removal, retouch, etc.).
    |
    */

    'storage' => [
        'driver' => env('AI_STORAGE_DRIVER', 'imagekit'),

        'imagekit' => [
            'public_key' => env('IMAGEKIT_PUBLIC_KEY'),
            'private_key' => env('IMAGEKIT_PRIVATE_KEY'),
            'url_endpoint' => env('IMAGEKIT_URL_ENDPOINT'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | AI jobs run on a dedicated queue to avoid blocking gallery operations.
    | Training can take 15-60 minutes; generation typically 30-90 seconds.
    |
    */

    'queue' => [
        'name' => env('AI_QUEUE', 'ai'),
        'max_training_poll_hours' => 24,
        'max_generation_poll_hours' => 2,
    ],

];

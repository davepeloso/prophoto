# ProPhoto AI

AI orchestration for ProPhoto providing model training, portrait generation, quotas, and cost tracking.

## Purpose

Manages AI portrait generation lifecycle:
- Train custom models per subject
- Generate AI portraits on demand
- Rate limiting and quota management
- Cost tracking per generation
- Provider abstraction (Stability AI, Midjourney, etc.)

## Key Features

### Model Training
- Upload 10-20 training images
- Validation (quality, variety, face detection)
- Train job submission to AI provider
- Webhook/polling for completion
- Model storage and versioning

### Generation Requests
- Subject selects style/prompt
- Queue job to AI provider
- Progress tracking
- Result storage in gallery
- Cost attribution

### Quota Management
- Per-subject generation limits
- Per-gallery limits
- Studio-wide limits
- Quota reset schedules
- Overage handling

### Cost Tracking
- Track cost per training ($5-$20)
- Track cost per generation ($0.10-$1.00)
- Aggregate by org/gallery/studio
- Export for billing

## Configuration

```php
return [
    'provider' => 'stability', // stability, midjourney
    'quotas' => [
        'generations_per_subject' => 100,
        'generations_per_gallery' => 1000,
    ],
    'costs' => [
        'training' => 15.00, // USD
        'generation' => 0.50, // USD per image
    ],
];
```

## Dependencies

- `prophoto/contracts` - AI contracts
- `prophoto/galleries` - Store generated images
- `prophoto/settings` - Feature flags


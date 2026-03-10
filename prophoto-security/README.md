# ProPhoto Security

Security layer for ProPhoto handling magic links, rate limiting, token management, and abuse prevention.

## Purpose

Provides secure, passwordless access and abuse prevention where:
- Magic links allow subjects to access galleries without accounts
- Tokens are cryptographically secure, one-time-use, expiring
- Rate limiting prevents brute force and abuse
- Download throttling prevents mass data exfiltration
- Audit trail of all security events

## Key Responsibilities

### Magic Link Generation & Verification
- Generate secure, high-entropy tokens
- Store tokens hashed (never plaintext)
- One-time use or reusable (configurable per link)
- Expiry after configured time (default: 7 days)
- Scope-based: gallery access, download permission, approval access

### Token Management
- Token lifecycle: generated → active → used/expired/revoked
- Automatic cleanup of expired tokens
- Revocation support (invalidate link immediately)
- Usage tracking (how many times accessed, last access)

### Rate Limiting
- Per-IP rate limiting on sensitive endpoints
- Per-token rate limiting (prevent link sharing abuse)
- Exponential backoff on repeated failures
- CAPTCHA trigger after threshold

### Download Authorization
- Every asset download requires authorization check
- Verify: token valid, not expired, has permission for THIS image
- Track download count per token (prevent unlimited sharing)
- Watermarking hooks (apply watermark based on token permissions)

### Abuse Prevention
- Detect suspicious patterns (rapid downloads, mass access)
- Automatic token revocation on abuse detection
- IP blocking for severe abuse
- Alert studio admins on abuse events

## Contracts Implemented

- `MagicLinkGeneratorContract` - Generate secure magic links
- `TokenVerifierContract` - Verify and validate tokens
- `RateLimiterContract` - Apply rate limits to requests

## Database Tables

- `magic_link_tokens` - Secure tokens for passwordless access
  - `id`
  - `token_hash` (hashed, never plaintext)
  - `scope` (gallery_access, download, approval)
  - `resource_type` (Gallery, Booking, etc.)
  - `resource_id`
  - `studio_id`
  - `created_by` (user who generated link)
  - `expires_at`
  - `revoked_at` (nullable)
  - `usage_limit` (null = unlimited, or specific number)
  - `usage_count` (current)
  - `last_used_at`
  - `last_ip_address`
  - `metadata` (JSON: custom permissions, restrictions)

- `rate_limits` - Track rate limit attempts
  - `id`
  - `key` (IP, token, user ID)
  - `endpoint`
  - `attempts`
  - `window_start`
  - `blocked_until` (nullable)

## Configuration

**config/security.php**
```php
return [
    'magic_links' => [
        'token_length' => 64, // High entropy
        'hash_algo' => 'sha256',
        'default_expiry_days' => 7,
        'one_time_use' => false, // Can reuse by default
        'max_usage_per_token' => null, // Unlimited
    ],

    'rate_limiting' => [
        'enabled' => true,
        'per_ip' => [
            'attempts' => 60,
            'window_seconds' => 60, // 60 requests per minute
        ],
        'per_token' => [
            'downloads' => 100,
            'window_seconds' => 3600, // 100 downloads per hour
        ],
        'lockout_minutes' => 15,
    ],

    'abuse_detection' => [
        'rapid_download_threshold' => 50, // 50 downloads in window
        'rapid_download_window_seconds' => 300, // 5 minutes
        'auto_revoke_on_abuse' => true,
        'notify_admin_on_abuse' => true,
    ],

    'token_cleanup' => [
        'delete_expired_after_days' => 30,
        'delete_revoked_after_days' => 90,
    ],
];
```

## Usage Examples

### Generate Magic Link
```php
use ProPhoto\Contracts\Security\MagicLinkGeneratorContract;

$linkGenerator = app(MagicLinkGeneratorContract::class);

$magicLink = $linkGenerator->generate([
    'scope' => 'gallery_access',
    'resource' => $gallery,
    'expires_in_days' => 7,
    'one_time_use' => false,
    'usage_limit' => 100, // Can be accessed 100 times
    'permissions' => [
        'can_download' => true,
        'can_approve' => true,
        'can_comment' => false,
    ],
]);

// Returns: MagicLinkToken DTO with URL
// https://prophoto.example.com/gallery/{gallery}/access/{token}
```

### Verify Token
```php
use ProPhoto\Contracts\Security\TokenVerifierContract;

$verifier = app(TokenVerifierContract::class);

$result = $verifier->verify($tokenFromUrl);

if ($result->valid) {
    // Token is valid
    $gallery = $result->resource;
    $permissions = $result->permissions;

    // Log the access (automatic via event)
    // Increment usage_count (automatic)
} else {
    // Token is expired, revoked, or invalid
    abort(403, $result->reason); // "Token expired", "Token revoked", etc.
}
```

### Check Rate Limit
```php
use ProPhoto\Contracts\Security\RateLimiterContract;

$limiter = app(RateLimiterContract::class);

if ($limiter->tooManyAttempts($key = "download:{$token}")) {
    $seconds = $limiter->availableIn($key);
    abort(429, "Too many requests. Try again in {$seconds} seconds.");
}

// Perform action
$limiter->hit($key);
```

### Middleware Usage
```php
// In routes/web.php
Route::get('/gallery/{gallery}/access/{token}', [GalleryAccessController::class, 'show'])
    ->middleware(['verify.magic.link', 'throttle:gallery_access']);

Route::get('/gallery/{gallery}/image/{image}/download', [DownloadController::class, 'download'])
    ->middleware(['verify.magic.link', 'throttle:downloads']);
```

### Revoke Token
```php
$linkGenerator->revoke($token);

// Token immediately invalid
// Audit log created
// Admin notified if configured
```

### Check Download Permission
```php
// In DownloadController:
public function download(Gallery $gallery, Image $image, Request $request)
{
    $token = $request->token;

    // Verify token grants access to THIS specific image
    if (!$verifier->authorizeDownload($token, $image)) {
        abort(403, 'You do not have permission to download this image.');
    }

    // Check rate limit
    if ($limiter->tooManyAttempts("download:{$token}")) {
        abort(429, 'Download limit exceeded. Please try again later.');
    }

    // Log download (automatic)
    // Increment usage count (automatic)

    return response()->download($image->path);
}
```

## Magic Link Scopes

### gallery_access
- View gallery and all images
- Optionally: download, approve, comment (based on permissions)
- Used for: Subject access links

### download
- Download specific images or entire gallery
- Used for: "Download all" links sent to clients

### approval
- View images and submit marketing approvals
- Used for: Marketing approval requests

### booking_confirm
- Confirm/decline booking request
- Used for: Booking confirmation links

## Abuse Detection

Automatic detection of:

- **Rapid downloads**: 50+ downloads in 5 minutes
- **Mass access**: Single token accessing 100+ different IPs
- **Brute force**: Rapid token guessing attempts
- **Link sharing**: Token used from many different IPs simultaneously

Actions on abuse:
1. Log to audit trail
2. Revoke token (if configured)
3. Block IP temporarily (if severe)
4. Notify studio admin

## Security Best Practices

### Token Generation
- 64+ character length (high entropy)
- Cryptographically secure random (random_bytes)
- Never expose raw token after initial generation
- Store only hashed version in database

### Token Verification
- Constant-time comparison (prevent timing attacks)
- Check expiry before any other logic
- Increment usage count atomically
- Log every verification attempt

### Rate Limiting
- Apply to all sensitive endpoints
- Use distributed cache (Redis) for multi-server setups
- Exponential backoff on repeated failures
- CAPTCHA integration for human verification

## Middleware

- `VerifyMagicLink` - Verifies token and sets context
- `ThrottleByToken` - Rate limit by token
- `ThrottleByIp` - Rate limit by IP
- `PreventAbuse` - Check for abuse patterns

## Events

- `MagicLinkGenerated` - Link created
- `MagicLinkUsed` - Link accessed
- `MagicLinkExpired` - Link expired
- `MagicLinkRevoked` - Link revoked
- `RateLimitExceeded` - Too many attempts
- `AbuseDetected` - Suspicious pattern detected

## Filament Integration

Admin panel for viewing/managing tokens:

- List all active tokens
- Filter by scope, status, resource
- Revoke tokens manually
- View usage stats (access count, last used, IPs)
- Export security report

## Future Enhancements

- [ ] CAPTCHA integration (hCaptcha/reCAPTCHA)
- [ ] Geofencing (restrict access by location)
- [ ] Device fingerprinting
- [ ] Watermarking integration
- [ ] Suspicious IP database (known VPNs, proxies)
- [ ] 2FA for sensitive magic links

## Dependencies

- `prophoto/contracts` - Security contracts and DTOs
- `prophoto/tenancy` - Studio context
- `prophoto/audit` - Log all security events
- `prophoto/notifications` - Alert admins on abuse

## Testing

```bash
cd prophoto-security
vendor/bin/pest
```

## Notes

- Tokens MUST be transmitted over HTTPS only
- Token cleanup runs daily (delete old expired/revoked tokens)
- Rate limits reset per window (sliding window algorithm)
- All security events logged to audit trail
- Failed verification attempts logged for security monitoring

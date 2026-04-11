<?php

namespace ProPhoto\Ingest\Services\Calendar;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CalendarOAuthService
 *
 * Handles Google Calendar OAuth2 token management.
 * Responsible for:
 *   - Generating the OAuth authorization URL
 *   - Exchanging authorization code for access + refresh tokens
 *   - Refreshing expired access tokens
 *   - Revoking tokens (disconnect calendar)
 *
 * Tokens are encrypted at rest using Laravel's Crypt facade.
 *
 * Story 1a.2 — Sprint 1
 */
class CalendarOAuthService
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUri;

    // Google OAuth endpoints
    protected const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    protected const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    protected const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    // Read-only calendar scope — we never write to the user's calendar
    protected const SCOPE = 'https://www.googleapis.com/auth/calendar.readonly';

    public function __construct()
    {
        $this->clientId     = config('prophoto-ingest.calendar.google_client_id', '');
        $this->clientSecret = config('prophoto-ingest.calendar.google_client_secret', '');
        $this->redirectUri  = config('prophoto-ingest.calendar.google_redirect_uri', '');
    }

    /**
     * Generate the Google OAuth authorization URL.
     * Redirect the user to this URL to begin the OAuth flow.
     *
     * @param  string  $stateToken  CSRF state token to verify on callback
     */
    public function getAuthorizationUrl(string $stateToken): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'             => $this->clientId,
            'redirect_uri'          => $this->redirectUri,
            'response_type'         => 'code',
            'scope'                 => self::SCOPE,
            'access_type'           => 'offline',  // Request refresh token
            'prompt'                => 'consent',   // Force consent screen (ensures refresh token)
            'state'                 => $stateToken,
            'include_granted_scopes' => 'true',
        ]);
    }

    /**
     * Exchange an authorization code for access + refresh tokens.
     * Returns the token payload to be stored on the user record.
     *
     * @param  string  $code  Authorization code from OAuth callback
     * @return array{access_token: string, refresh_token: string, expires_at: int, scope: string}
     *
     * @throws \RuntimeException if exchange fails
     */
    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code',
        ]);

        if (! $response->successful()) {
            Log::error('Calendar OAuth: Code exchange failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Google Calendar authorization failed. Please try again.');
        }

        $data = $response->json();

        if (empty($data['access_token'])) {
            throw new \RuntimeException('Google Calendar returned an invalid token response.');
        }

        return [
            'access_token'  => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_at'    => now()->addSeconds($data['expires_in'] ?? 3600)->timestamp,
            'scope'         => $data['scope'] ?? self::SCOPE,
        ];
    }

    /**
     * Refresh an expired access token using the stored refresh token.
     *
     * @param  string  $encryptedRefreshToken  Encrypted refresh token from user record
     * @return array{access_token: string, expires_at: int}
     *
     * @throws \RuntimeException if refresh fails
     */
    public function refreshAccessToken(string $encryptedRefreshToken): array
    {
        $refreshToken = Crypt::decryptString($encryptedRefreshToken);

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token',
        ]);

        if (! $response->successful()) {
            Log::warning('Calendar OAuth: Token refresh failed', [
                'status' => $response->status(),
            ]);
            throw new \RuntimeException('Calendar token refresh failed. Please reconnect your calendar.');
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'expires_at'   => now()->addSeconds($data['expires_in'] ?? 3600)->timestamp,
        ];
    }

    /**
     * Revoke all calendar tokens for a user.
     * Called when user disconnects their calendar.
     *
     * @param  string  $encryptedAccessToken  Encrypted access token from user record
     */
    public function revokeTokens(string $encryptedAccessToken): void
    {
        try {
            $accessToken = Crypt::decryptString($encryptedAccessToken);

            Http::post(self::REVOKE_URL, ['token' => $accessToken]);
        } catch (\Throwable $e) {
            // Log but don't throw — we should still clear local tokens
            // even if Google revocation fails (token may already be expired)
            Log::warning('Calendar OAuth: Token revocation failed (non-critical)', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check whether the given token payload is expired.
     *
     * @param  array{expires_at: int}  $tokenData
     */
    public function isExpired(array $tokenData): bool
    {
        // Consider expired if within 60 seconds of expiry (buffer)
        return ($tokenData['expires_at'] ?? 0) <= now()->addSeconds(60)->timestamp;
    }

    /**
     * Validate the OAuth state token to prevent CSRF attacks.
     * Compare the state stored in the session with the one returned from Google.
     */
    public function validateStateToken(string $expected, string $received): bool
    {
        return hash_equals($expected, $received);
    }
}

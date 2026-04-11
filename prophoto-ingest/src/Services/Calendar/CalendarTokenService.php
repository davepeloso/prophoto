<?php

namespace ProPhoto\Ingest\Services\Calendar;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * CalendarTokenService
 *
 * Manages storage and retrieval of encrypted Calendar OAuth tokens
 * on the user model. Handles token refresh transparently — callers
 * just call getValidAccessToken() and always receive a usable token.
 *
 * Story 1a.2 — Sprint 1
 */
class CalendarTokenService
{
    public function __construct(
        protected CalendarOAuthService $oauthService
    ) {}

    /**
     * Store encrypted tokens on the user after successful OAuth exchange.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  array{access_token: string, refresh_token: ?string, expires_at: int, scope: string}  $tokenData
     */
    public function storeTokens(mixed $user, array $tokenData): void
    {
        $user->forceFill([
            'calendar_access_token'  => Crypt::encryptString($tokenData['access_token']),
            'calendar_refresh_token' => $tokenData['refresh_token']
                ? Crypt::encryptString($tokenData['refresh_token'])
                : null,
            'calendar_token_expires_at' => $tokenData['expires_at'],
            'calendar_connected_at'     => now(),
            'calendar_scope'            => $tokenData['scope'],
            'calendar_provider'         => 'google',
        ])->save();
    }

    /**
     * Clear all calendar tokens from the user record.
     * Called on calendar disconnect or OAuth revocation.
     */
    public function clearTokens(mixed $user): void
    {
        $user->forceFill([
            'calendar_access_token'     => null,
            'calendar_refresh_token'    => null,
            'calendar_token_expires_at' => null,
            'calendar_connected_at'     => null,
            'calendar_scope'            => null,
            'calendar_provider'         => null,
        ])->save();
    }

    /**
     * Get a valid (non-expired) access token for the user.
     * Auto-refreshes if the token is expired.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @return string  Plain-text access token ready for API use
     *
     * @throws \RuntimeException if user has no calendar connected
     */
    public function getValidAccessToken(mixed $user): string
    {
        if (! $this->isConnected($user)) {
            throw new \RuntimeException('No calendar connected for this user.');
        }

        $tokenData = [
            'expires_at' => $user->calendar_token_expires_at,
        ];

        // If token is still valid, decrypt and return it
        if (! $this->oauthService->isExpired($tokenData)) {
            return Crypt::decryptString($user->calendar_access_token);
        }

        // Token expired — attempt refresh
        if (empty($user->calendar_refresh_token)) {
            throw new \RuntimeException('Calendar token expired and no refresh token available. Please reconnect.');
        }

        Log::info('CalendarTokenService: Refreshing expired access token', [
            'user_id' => $user->id,
        ]);

        $refreshed = $this->oauthService->refreshAccessToken($user->calendar_refresh_token);

        // Persist refreshed token
        $user->forceFill([
            'calendar_access_token'     => Crypt::encryptString($refreshed['access_token']),
            'calendar_token_expires_at' => $refreshed['expires_at'],
        ])->save();

        return $refreshed['access_token'];
    }

    /**
     * Check whether the user has a calendar connected.
     */
    public function isConnected(mixed $user): bool
    {
        return ! empty($user->calendar_access_token)
            && ! empty($user->calendar_provider);
    }

    /**
     * Get the connected calendar provider name (e.g. 'google').
     */
    public function getProvider(mixed $user): ?string
    {
        return $user->calendar_provider ?? null;
    }
}

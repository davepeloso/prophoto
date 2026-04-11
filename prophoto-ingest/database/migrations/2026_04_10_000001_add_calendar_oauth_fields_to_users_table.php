<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Google Calendar OAuth token storage to the users table.
 * Tokens are stored encrypted (via Laravel Crypt). We never store
 * plain-text credentials in the database.
 *
 * Story 1a.2 — Sprint 1
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Provider identifier ('google', future: 'apple', 'outlook')
            $table->string('calendar_provider', 32)->nullable()->after('remember_token');

            // Encrypted OAuth tokens
            $table->text('calendar_access_token')->nullable()->after('calendar_provider');
            $table->text('calendar_refresh_token')->nullable()->after('calendar_access_token');

            // Token expiry as Unix timestamp for fast comparison
            $table->unsignedBigInteger('calendar_token_expires_at')->nullable()->after('calendar_refresh_token');

            // When the calendar was first connected
            $table->timestamp('calendar_connected_at')->nullable()->after('calendar_token_expires_at');

            // OAuth scope granted (read-only for us, but stored for auditing)
            $table->string('calendar_scope', 512)->nullable()->after('calendar_connected_at');

            $table->index('calendar_provider', 'idx_users_calendar_provider');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('idx_users_calendar_provider');
            $table->dropColumn([
                'calendar_provider',
                'calendar_access_token',
                'calendar_refresh_token',
                'calendar_token_expires_at',
                'calendar_connected_at',
                'calendar_scope',
            ]);
        });
    }
};

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
        // Guard: the users table is owned by the host application.
        // In package test environments (Orchestra Testbench) it doesn't exist,
        // so we skip gracefully. In the real app it will always be present.
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'calendar_provider')) {
                $table->string('calendar_provider', 32)->nullable()->after('remember_token');
            }
            if (! Schema::hasColumn('users', 'calendar_access_token')) {
                $table->text('calendar_access_token')->nullable()->after('calendar_provider');
            }
            if (! Schema::hasColumn('users', 'calendar_refresh_token')) {
                $table->text('calendar_refresh_token')->nullable()->after('calendar_access_token');
            }
            if (! Schema::hasColumn('users', 'calendar_token_expires_at')) {
                $table->unsignedBigInteger('calendar_token_expires_at')->nullable()->after('calendar_refresh_token');
            }
            if (! Schema::hasColumn('users', 'calendar_connected_at')) {
                $table->timestamp('calendar_connected_at')->nullable()->after('calendar_token_expires_at');
            }
            if (! Schema::hasColumn('users', 'calendar_scope')) {
                $table->string('calendar_scope', 512)->nullable()->after('calendar_connected_at');
            }

            try {
                $table->index('calendar_provider', 'idx_users_calendar_provider');
            } catch (\Throwable) {
                // Index may already exist
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            try { $table->dropIndex('idx_users_calendar_provider'); } catch (\Throwable) {}
            $existing = array_filter([
                'calendar_provider', 'calendar_access_token', 'calendar_refresh_token',
                'calendar_token_expires_at', 'calendar_connected_at', 'calendar_scope',
            ], fn ($col) => Schema::hasColumn('users', $col));

            if (! empty($existing)) {
                $table->dropColumn(array_values($existing));
            }
        });
    }
};

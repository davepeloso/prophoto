<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds ProPhoto Access columns to existing Laravel users table.
     * Standard Laravel users table already has: id, name, email, email_verified_at,
     * password, remember_token, created_at, updated_at
     *
     * Note: For multi-tenant setups where the same email can exist across different
     * studios, you may want to drop Laravel's default unique constraint on email
     * and add a composite unique constraint instead:
     *   $table->dropUnique(['email']);
     *   $table->unique(['studio_id', 'email', 'deleted_at'], 'unique_email_per_studio');
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add ProPhoto Access specific columns
            $table->foreignId('studio_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('phone', 50)->nullable()->after('password');
            $table->string('avatar_url', 500)->nullable()->after('phone');
            $table->string('timezone', 100)->nullable()->after('avatar_url');
            $table->string('role', 50)->nullable()->after('timezone'); // Cached from Spatie for queries
            $table->softDeletes();

            // Add indexes
            $table->index('role');
            $table->index('studio_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop foreign key and indexes first
            $table->dropForeign(['studio_id']);
            $table->dropIndex(['role']);
            $table->dropIndex(['studio_id']);

            // Drop columns
            $table->dropColumn([
                'studio_id',
                'phone',
                'avatar_url',
                'timezone',
                'role',
                'deleted_at',
            ]);
        });
    }
};

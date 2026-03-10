<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Extends the existing image_interactions table with session tracking.
     */
    public function up(): void
    {
        // This table comes from prophoto-interactions and may not exist in
        // minimal sandbox installs.
        if (!Schema::hasTable('image_interactions')) {
            return;
        }

        Schema::table('image_interactions', function (Blueprint $table) {
            // Session tracking for analytics
            if (!Schema::hasColumn('image_interactions', 'session_id')) {
                $table->string('session_id')->nullable()->after('ip_address');
            }

            if (!Schema::hasColumn('image_interactions', 'user_agent')) {
                $table->text('user_agent')->nullable()->after('session_id');
            }

            // Indexes for better query performance
            $table->index('user_id', 'idx_interactions_user');
            $table->index('created_at', 'idx_interactions_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('image_interactions')) {
            return;
        }

        Schema::table('image_interactions', function (Blueprint $table) {
            try {
                $table->dropIndex('idx_interactions_user');
            } catch (\Throwable) {
            }

            try {
                $table->dropIndex('idx_interactions_created');
            } catch (\Throwable) {
            }

            $columnsToDrop = [];
            if (Schema::hasColumn('image_interactions', 'session_id')) {
                $columnsToDrop[] = 'session_id';
            }
            if (Schema::hasColumn('image_interactions', 'user_agent')) {
                $columnsToDrop[] = 'user_agent';
            }

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};

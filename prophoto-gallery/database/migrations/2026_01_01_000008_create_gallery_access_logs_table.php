<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the gallery_access_logs table for audit trail and analytics.
     */
    public function up(): void
    {
        Schema::create('gallery_access_logs', function (Blueprint $table) {
            $table->id();

            // What was accessed
            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();

            // Who accessed it (nullable for anonymous/unauthenticated access)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // What action was performed
            $table->string('action', 50); // viewed, downloaded, shared, approved, rated, commented, exported

            // Optional: specific resource within gallery
            $table->string('resource_type', 50)->nullable(); // gallery, image, collection, comment
            $table->unsignedBigInteger('resource_id')->nullable();

            // Request metadata
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('referer', 500)->nullable();
            $table->string('session_id')->nullable();

            // Additional context (JSON for flexibility)
            // Examples: {image_count: 5, download_format: 'original', share_token: 'abc123'}
            $table->json('metadata')->nullable();

            // Performance tracking
            $table->unsignedInteger('response_time_ms')->nullable();

            // Geographic data (optional, can be enriched later)
            $table->string('country_code', 2)->nullable();
            $table->string('city')->nullable();

            // Timestamp
            $table->timestamp('created_at')->useCurrent();

            // Indexes for analytics queries
            $table->index('gallery_id', 'idx_access_logs_gallery');
            $table->index('user_id', 'idx_access_logs_user');
            $table->index('created_at', 'idx_access_logs_created');
            $table->index(['gallery_id', 'action', 'created_at'], 'idx_access_logs_analytics');
            $table->index(['user_id', 'action', 'created_at'], 'idx_access_logs_user_activity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gallery_access_logs');
    }
};

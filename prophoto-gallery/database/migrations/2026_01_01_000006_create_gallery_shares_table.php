<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the gallery_shares table for sharing galleries with external users
     * beyond the magic link functionality.
     */
    public function up(): void
    {
        Schema::create('gallery_shares', function (Blueprint $table) {
            $table->id();

            // What is being shared
            $table->foreignId('gallery_id')->constrained()->cascadeOnDelete();

            // Who shared it
            $table->foreignId('shared_by_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Who is it shared with
            $table->string('shared_with_email');
            $table->foreignId('shared_with_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('cascade');

            // Unique share token
            $table->string('share_token')->unique();

            // Granular permissions (what can they do?)
            $table->boolean('can_view')->default(true);
            $table->boolean('can_download')->default(false);
            $table->boolean('can_approve')->default(false);
            $table->boolean('can_comment')->default(false);
            $table->boolean('can_share')->default(false);

            // Temporal controls
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accessed_at')->nullable(); // First access
            $table->timestamp('last_accessed_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);

            // Optional security
            $table->string('password_hash')->nullable();
            $table->json('ip_whitelist')->nullable(); // ['192.168.1.0/24']

            // Download limits
            $table->unsignedInteger('max_downloads')->nullable();
            $table->unsignedInteger('download_count')->default(0);

            // Revocation
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // Custom message to recipient
            $table->text('message')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('gallery_id', 'idx_shares_gallery');
            $table->index('share_token', 'idx_shares_token');
            $table->index('shared_with_email', 'idx_shares_email');
            $table->index(['gallery_id', 'shared_with_email'], 'idx_shares_gallery_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gallery_shares');
    }
};

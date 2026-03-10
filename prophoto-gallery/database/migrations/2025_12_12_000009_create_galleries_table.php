<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $hasPhotoSessionsTable = Schema::hasTable('photo_sessions');

        Schema::create('galleries', function (Blueprint $table) use ($hasPhotoSessionsTable) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            if ($hasPhotoSessionsTable) {
                $table->foreignId('session_id')->nullable()->constrained('photo_sessions')->onDelete('set null');
            } else {
                $table->unsignedBigInteger('session_id')->nullable();
            }
            $table->string('subject_name');
            $table->string('access_code', 100)->unique()->nullable();
            $table->string('magic_link_token')->unique()->nullable();
            $table->timestamp('magic_link_expires_at')->nullable();
            $table->string('status', 50)->default('active'); // active, completed, archived
            $table->boolean('ai_enabled')->default(false);
            $table->string('ai_training_status', 50)->nullable(); // null, ready, training, trained
            $table->unsignedInteger('image_count')->default(0);
            $table->unsignedInteger('approved_count')->default(0);
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'idx_galleries_organization');
            $table->index('access_code', 'idx_galleries_access_code');
            $table->index('status', 'idx_galleries_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('galleries');
    }
};

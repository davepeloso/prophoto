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
        Schema::create('photo_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('subject_name');
            $table->string('session_type', 50); // headshot, half_day, full_day, event
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('location', 500)->nullable();
            $table->string('status', 50)->default('tentative'); // tentative, scheduled, completed, processing, delivered, cancelled
            $table->string('google_event_id')->nullable();
            $table->decimal('rate', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index('scheduled_at', 'idx_photo_sessions_scheduled_at');
            $table->index('status', 'idx_photo_sessions_status');
            $table->index('organization_id', 'idx_photo_sessions_organization');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photo_sessions');
    }
};

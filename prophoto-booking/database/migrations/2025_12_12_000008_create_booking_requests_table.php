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
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_name');
            $table->string('session_type', 50);
            $table->timestamp('requested_datetime');
            $table->unsignedInteger('duration_minutes')->default(30);
            $table->string('location', 500)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 50)->default('pending'); // pending, confirmed, denied, cancelled
            $table->foreignId('session_id')->nullable()->constrained('photo_sessions')->onDelete('set null');
            $table->string('google_event_id')->nullable();
            $table->text('denial_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('status', 'idx_booking_requests_status');
            $table->index('requested_datetime', 'idx_booking_requests_requested_datetime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_requests');
    }
};

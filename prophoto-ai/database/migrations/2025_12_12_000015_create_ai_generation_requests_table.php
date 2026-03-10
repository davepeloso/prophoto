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
        Schema::create('ai_generation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_generation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('request_number'); // 1-5
            $table->text('custom_prompt')->nullable();
            $table->boolean('used_default_prompt')->default(true);
            $table->unsignedInteger('generated_portrait_count')->default(8);
            $table->decimal('generation_cost', 8, 2)->nullable();
            $table->boolean('background_removal')->default(false);
            $table->boolean('super_resolution')->default(false);
            $table->string('status', 50)->default('pending'); // pending, processing, completed, failed
            $table->text('error_message')->nullable();
            $table->timestamp('liability_accepted_at')->nullable();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('ai_generation_id', 'idx_ai_gen_requests_generation');
            $table->index('status', 'idx_ai_gen_requests_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_generation_requests');
    }
};

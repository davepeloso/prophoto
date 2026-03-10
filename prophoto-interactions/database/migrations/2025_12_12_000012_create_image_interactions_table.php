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
        Schema::create('image_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('image_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('interaction_type', 50); // rating, note, approval, download, edit_request
            $table->unsignedTinyInteger('rating')->nullable(); // 1-5 stars
            $table->text('note')->nullable();
            $table->boolean('approved_for_marketing')->nullable();
            $table->boolean('edit_requested')->nullable();
            $table->text('edit_notes')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('image_id', 'idx_image_interactions_image');
            $table->index('interaction_type', 'idx_image_interactions_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_interactions');
    }
};

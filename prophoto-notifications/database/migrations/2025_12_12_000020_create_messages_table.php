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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('gallery_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('image_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('recipient_user_id', 'idx_messages_recipient');
            $table->index('gallery_id', 'idx_messages_gallery');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

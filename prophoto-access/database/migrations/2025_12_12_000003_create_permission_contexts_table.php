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
        Schema::create('permission_contexts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->string('contextable_type');
            $table->unsignedBigInteger('contextable_id');
            $table->timestamp('granted_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['contextable_type', 'contextable_id'], 'idx_contextable');
            $table->index(['user_id', 'permission_id'], 'idx_user_permission');
            $table->unique(['user_id', 'permission_id', 'contextable_type', 'contextable_id'], 'unique_context');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_contexts');
    }
};

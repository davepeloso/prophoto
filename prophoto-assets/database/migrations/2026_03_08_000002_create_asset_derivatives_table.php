<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_derivatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('type', 32)->index();
            $table->string('storage_key', 1024);
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'type'], 'idx_asset_derivatives_asset_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_derivatives');
    }
};

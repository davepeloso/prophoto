<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('studio_id')->index();
            $table->string('organization_id')->nullable()->index();
            $table->string('type', 32)->index();
            $table->string('original_filename');
            $table->string('mime_type', 191)->index();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->string('checksum_sha256', 64)->index();
            $table->string('storage_driver', 64)->default('local');
            $table->string('storage_key_original', 1024);
            $table->string('logical_path', 512)->default('')->index();
            $table->timestamp('captured_at')->nullable()->index();
            $table->timestamp('ingested_at')->nullable()->index();
            $table->string('status', 32)->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['studio_id', 'checksum_sha256'], 'idx_assets_studio_checksum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};

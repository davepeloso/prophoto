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
        Schema::create('studios', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subdomain', 100)->unique()->nullable();
            $table->string('business_name');
            $table->string('business_address')->nullable();
            $table->string('business_city', 100)->nullable();
            $table->string('business_state', 50)->nullable();
            $table->string('business_zip', 20)->nullable();
            $table->string('business_phone', 50)->nullable();
            $table->string('business_email')->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->string('website_url', 500)->nullable();
            $table->string('timezone', 100)->default('UTC');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index('subdomain');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('studios');
    }
};

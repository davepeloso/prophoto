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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 50)->nullable(); // corporate, individual, agency
            $table->string('billing_email')->nullable();
            $table->string('billing_phone', 50)->nullable();
            $table->string('billing_address')->nullable();
            $table->string('billing_city', 100)->nullable();
            $table->string('billing_state', 50)->nullable();
            $table->string('billing_zip', 20)->nullable();
            $table->string('vendor_number', 100)->nullable(); // Client's vendor # for photographer
            $table->string('insurance_code', 100)->nullable(); // Client's insurance code
            $table->string('payment_terms', 50)->nullable(); // Net 30, Net 60, etc.
            $table->boolean('tax_exempt')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('studio_id', 'idx_organizations_studio');
            $table->index('name', 'idx_organizations_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};

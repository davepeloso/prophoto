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
        Schema::create('custom_fees', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50); // mileage, post_processing, travel, second_shooter, assistant, equipment, insurance
            $table->text('description');
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_price', 10, 2);
            $table->json('calculation_data')->nullable();
            $table->timestamps(); // Changed from `created_at` to `timestamps` to include `updated_at` as per common Laravel convention.

            $table->index('type', 'idx_custom_fees_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_fees');
    }
};

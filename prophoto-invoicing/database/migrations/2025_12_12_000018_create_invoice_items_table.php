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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('itemable_type')->nullable(); // Session, CustomFee (polymorphic)
            $table->unsignedBigInteger('itemable_id')->nullable();
            $table->text('description');
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total', 10, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('invoice_id', 'idx_invoice_items_invoice');
            $table->index(['itemable_type', 'itemable_id'], 'idx_invoice_items_itemable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};

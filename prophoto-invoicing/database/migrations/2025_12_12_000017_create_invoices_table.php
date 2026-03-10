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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number', 100)->unique();
            $table->string('quote_number', 100)->nullable();
            $table->string('status', 50)->default('draft'); // draft, quote, sent, paid, overdue, cancelled
            $table->string('stripe_invoice_id')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->date('paid_at')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0.00);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2)->default(0.00);
            $table->string('payment_method', 50)->nullable(); // stripe, bank_transfer, check, wire, cash
            $table->string('payment_reference')->nullable();
            $table->text('payment_notes')->nullable();
            $table->string('po_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->text('client_notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'idx_invoices_organization');
            $table->index('status', 'idx_invoices_status');
            $table->index('invoice_number', 'idx_invoices_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

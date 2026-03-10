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
        Schema::create('organization_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users');
            $table->string('title');
            $table->string('type', 50); // insurance, w9, contract, terms, branding, other
            $table->text('description')->nullable();
            $table->string('file_name');
            $table->string('file_path', 500);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('requires_renewal')->default(false);
            $table->date('reminded_at')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('client_visible')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id', 'idx_org_docs_organization');
            $table->index('type', 'idx_org_docs_type');
            $table->index('expires_at', 'idx_org_docs_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_documents');
    }
};

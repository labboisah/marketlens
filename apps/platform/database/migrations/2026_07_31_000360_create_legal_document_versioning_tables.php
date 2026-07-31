<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('legal_documents')) {
            Schema::create('legal_documents', function (Blueprint $table): void {
                $table->id();
                $table->string('slug')->unique();
                $table->string('title');
                $table->text('description')->nullable();
                $table->boolean('is_public')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('legal_document_versions')) {
            Schema::create('legal_document_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('legal_document_id')->constrained('legal_documents')->cascadeOnDelete();
                $table->string('version');
                $table->text('summary')->nullable();
                $table->longText('body');
                $table->timestamp('published_at')->nullable();
                $table->timestamp('effective_at')->nullable();
                $table->boolean('is_published')->default(false);
                $table->boolean('is_material')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['legal_document_id', 'version'], 'legal_document_version_unique');
                $table->index(['is_published', 'effective_at'], 'legal_version_published_effective_idx');
            });
        }

        if (! Schema::hasTable('legal_acceptances')) {
            Schema::create('legal_acceptances', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('legal_document_version_id')->constrained('legal_document_versions')->cascadeOnDelete();
                $table->timestamp('accepted_at');
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'legal_document_version_id'], 'legal_acceptance_user_version_unique');
                $table->index(['user_id', 'accepted_at'], 'legal_acceptance_user_accepted_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_acceptances');
        Schema::dropIfExists('legal_document_versions');
        Schema::dropIfExists('legal_documents');
    }
};

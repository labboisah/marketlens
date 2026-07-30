<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('report_key');
            $table->string('report_group');
            $table->string('format');
            $table->string('status')->default('queued');
            $table->json('filters')->nullable();
            $table->unsignedInteger('rows_count')->default(0);
            $table->longText('content')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'report_key', 'created_at'], 'report_exports_business_report_created_idx');
            $table->index(['business_id', 'status', 'created_at'], 'report_exports_business_status_created_idx');
        });

        Schema::create('scheduled_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('report_key');
            $table->string('format')->default('csv');
            $table->string('frequency')->default('weekly');
            $table->json('filters')->nullable();
            $table->json('recipients')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'is_active', 'next_run_at'], 'scheduled_reports_business_active_next_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('report_exports');
    }
};

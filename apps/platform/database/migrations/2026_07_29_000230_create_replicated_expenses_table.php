<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replicated_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sync_batch_id')->nullable()->constrained('sync_batches')->nullOnDelete();
            $table->uuid('uuid');
            $table->string('expense_number');
            $table->uuid('category_uuid')->nullable();
            $table->string('category_name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->string('payment_method')->nullable();
            $table->uuid('creator_uuid')->nullable();
            $table->string('creator_name')->nullable();
            $table->uuid('approver_uuid')->nullable();
            $table->string('approver_name')->nullable();
            $table->string('status')->default('approved');
            $table->dateTime('expense_date');
            $table->string('receipt_attachment_path')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_period')->nullable();
            $table->unsignedInteger('source_version')->default(1);
            $table->string('source_idempotency_key');
            $table->timestamp('source_occurred_at');
            $table->timestamps();

            $table->unique(['business_id', 'uuid'], 'rep_expenses_business_uuid_unique');
            $table->unique(['business_id', 'source_idempotency_key'], 'rep_expenses_idempotency_unique');
            $table->index(['business_id', 'branch_id', 'expense_date'], 'rep_expenses_scope_date_idx');
            $table->index(['business_id', 'category_uuid', 'expense_date'], 'rep_expenses_category_date_idx');
            $table->index(['business_id', 'status', 'expense_date'], 'rep_expenses_status_date_idx');
            $table->index(['business_id', 'creator_uuid', 'expense_date'], 'rep_expenses_creator_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replicated_expenses');
    }
};

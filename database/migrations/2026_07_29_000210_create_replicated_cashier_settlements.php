<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replicated_cashier_settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sync_batch_id')->nullable()->constrained('sync_batches')->nullOnDelete();
            $table->uuid('uuid');
            $table->uuid('shift_uuid')->nullable();
            $table->uuid('cashier_uuid')->nullable();
            $table->string('cashier_name')->nullable();
            $table->unsignedBigInteger('expected_cash_minor')->default(0);
            $table->unsignedBigInteger('actual_cash_minor')->default(0);
            $table->bigInteger('shortage_minor')->default(0);
            $table->bigInteger('surplus_minor')->default(0);
            $table->unsignedBigInteger('cash_expenses_minor')->default(0);
            $table->unsignedBigInteger('refunds_minor')->default(0);
            $table->unsignedBigInteger('unverified_transfer_minor')->default(0);
            $table->unsignedInteger('payment_mismatch_count')->default(0);
            $table->unsignedInteger('reversal_count')->default(0);
            $table->string('status')->default('pending');
            $table->timestamp('settled_at')->nullable();
            $table->unsignedInteger('source_version')->default(1);
            $table->string('source_idempotency_key');
            $table->timestamp('source_occurred_at');
            $table->timestamps();

            $table->unique(['business_id', 'uuid'], 'replicated_settlements_business_uuid_unique');
            $table->unique(['business_id', 'source_idempotency_key'], 'replicated_settlements_business_idempotency_unique');
            $table->index(['business_id', 'branch_id', 'settled_at'], 'replicated_settlements_scope_date_idx');
            $table->index(['business_id', 'cashier_uuid', 'settled_at'], 'replicated_settlements_cashier_date_idx');
            $table->index(['business_id', 'status', 'settled_at'], 'replicated_settlements_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replicated_cashier_settlements');
    }
};

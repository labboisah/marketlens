<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replicated_customer_debt_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sync_batch_id')->nullable()->constrained('sync_batches')->nullOnDelete();
            $table->uuid('uuid');
            $table->uuid('customer_uuid');
            $table->string('customer_name');
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('credit_limit_minor')->default(0);
            $table->unsignedBigInteger('current_balance_minor')->default(0);
            $table->unsignedBigInteger('credit_sales_minor')->default(0);
            $table->unsignedBigInteger('overdue_amount_minor')->default(0);
            $table->string('debt_status')->default('current');
            $table->dateTime('last_payment_at')->nullable();
            $table->unsignedBigInteger('last_payment_minor')->default(0);
            $table->string('last_payment_method')->nullable();
            $table->dateTime('oldest_unpaid_sale_at')->nullable();
            $table->string('oldest_unpaid_sale_number')->nullable();
            $table->unsignedBigInteger('age_current_minor')->default(0);
            $table->unsignedBigInteger('age_1_30_minor')->default(0);
            $table->unsignedBigInteger('age_31_60_minor')->default(0);
            $table->unsignedBigInteger('age_61_90_minor')->default(0);
            $table->unsignedBigInteger('age_over_90_minor')->default(0);
            $table->dateTime('last_synchronized_at')->nullable();
            $table->unsignedInteger('source_version')->default(1);
            $table->string('source_idempotency_key');
            $table->timestamp('source_occurred_at');
            $table->timestamps();

            $table->unique(['business_id', 'customer_uuid'], 'rep_debt_business_customer_unique');
            $table->unique(['business_id', 'uuid'], 'rep_debt_business_uuid_unique');
            $table->unique(['business_id', 'source_idempotency_key'], 'rep_debt_idempotency_unique');
            $table->index(['business_id', 'branch_id', 'debt_status'], 'rep_debt_scope_status_idx');
            $table->index(['business_id', 'current_balance_minor'], 'rep_debt_balance_idx');
            $table->index(['business_id', 'oldest_unpaid_sale_at'], 'rep_debt_oldest_sale_idx');
        });

        Schema::create('replicated_customer_debt_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_debt_summary_id');
            $table->uuid('uuid');
            $table->uuid('customer_uuid');
            $table->string('customer_name');
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->string('payment_method')->nullable();
            $table->string('reference')->nullable();
            $table->dateTime('paid_at');
            $table->timestamps();

            $table->unique(['business_id', 'uuid'], 'rep_debt_payments_business_uuid_unique');
            $table->index(['business_id', 'branch_id', 'paid_at'], 'rep_debt_payments_scope_date_idx');
            $table->index(['business_id', 'customer_uuid', 'paid_at'], 'rep_debt_payments_customer_date_idx');
            $table->foreign('customer_debt_summary_id', 'rep_debt_payments_summary_fk')
                ->references('id')
                ->on('replicated_customer_debt_summaries')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replicated_customer_debt_payments');
        Schema::dropIfExists('replicated_customer_debt_summaries');
    }
};

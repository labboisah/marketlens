<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replicated_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sync_batch_id')->nullable()->constrained('sync_batches')->nullOnDelete();
            $table->uuid('uuid');
            $table->string('purchase_number');
            $table->uuid('supplier_uuid')->nullable();
            $table->string('supplier_name');
            $table->dateTime('purchased_at');
            $table->string('status')->default('received');
            $table->string('payment_status')->default('unpaid');
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('transport_cost_minor')->default(0);
            $table->unsignedBigInteger('additional_cost_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('amount_paid_minor')->default(0);
            $table->unsignedBigInteger('balance_minor')->default(0);
            $table->unsignedInteger('source_version')->default(1);
            $table->string('source_idempotency_key');
            $table->timestamp('source_occurred_at');
            $table->timestamps();

            $table->unique(['business_id', 'uuid'], 'rep_purchases_business_uuid_unique');
            $table->unique(['business_id', 'source_idempotency_key'], 'rep_purchases_idempotency_unique');
            $table->index(['business_id', 'branch_id', 'purchased_at'], 'rep_purchases_scope_date_idx');
            $table->index(['business_id', 'supplier_uuid', 'purchased_at'], 'rep_purchases_supplier_date_idx');
            $table->index(['business_id', 'payment_status', 'purchased_at'], 'rep_purchases_payment_status_idx');
        });

        Schema::create('replicated_purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('replicated_purchase_id')->constrained('replicated_purchases')->cascadeOnDelete();
            $table->uuid('uuid');
            $table->uuid('purchase_uuid');
            $table->uuid('product_uuid')->nullable();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->unsignedBigInteger('unit_cost_minor')->default(0);
            $table->unsignedBigInteger('line_total_minor')->default(0);
            $table->unsignedInteger('source_version')->default(1);
            $table->string('source_idempotency_key');
            $table->timestamp('source_occurred_at');
            $table->timestamps();

            $table->unique(['business_id', 'uuid'], 'rep_purchase_items_business_uuid_unique');
            $table->unique(['business_id', 'source_idempotency_key'], 'rep_purchase_items_idempotency_unique');
            $table->index(['business_id', 'product_uuid', 'created_at'], 'rep_purchase_items_product_idx');
        });

        Schema::create('replicated_purchase_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('replicated_purchase_id')->nullable()->constrained('replicated_purchases')->nullOnDelete();
            $table->uuid('uuid');
            $table->uuid('purchase_uuid');
            $table->uuid('supplier_uuid')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('method')->nullable();
            $table->string('status')->default('completed');
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->string('reference')->nullable();
            $table->dateTime('paid_at');
            $table->unsignedInteger('source_version')->default(1);
            $table->string('source_idempotency_key');
            $table->timestamp('source_occurred_at');
            $table->timestamps();

            $table->unique(['business_id', 'uuid'], 'rep_purchase_payments_business_uuid_unique');
            $table->unique(['business_id', 'source_idempotency_key'], 'rep_purchase_payments_idempotency_unique');
            $table->index(['business_id', 'branch_id', 'paid_at'], 'rep_purchase_payments_scope_date_idx');
            $table->index(['business_id', 'supplier_uuid', 'paid_at'], 'rep_purchase_payments_supplier_date_idx');
        });

        Schema::create('replicated_supplier_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sync_batch_id')->nullable()->constrained('sync_batches')->nullOnDelete();
            $table->uuid('uuid');
            $table->uuid('supplier_uuid');
            $table->string('supplier_name');
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('current_balance_minor')->default(0);
            $table->unsignedBigInteger('overdue_balance_minor')->default(0);
            $table->dateTime('last_purchase_at')->nullable();
            $table->dateTime('last_payment_at')->nullable();
            $table->string('status')->default('active');
            $table->dateTime('last_synchronized_at')->nullable();
            $table->unsignedInteger('source_version')->default(1);
            $table->string('source_idempotency_key');
            $table->timestamp('source_occurred_at');
            $table->timestamps();

            $table->unique(['business_id', 'supplier_uuid'], 'rep_supplier_balances_supplier_unique');
            $table->unique(['business_id', 'uuid'], 'rep_supplier_balances_uuid_unique');
            $table->unique(['business_id', 'source_idempotency_key'], 'rep_supplier_balances_idempotency_unique');
            $table->index(['business_id', 'branch_id', 'current_balance_minor'], 'rep_supplier_balances_scope_balance_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replicated_supplier_balances');
        Schema::dropIfExists('replicated_purchase_payments');
        Schema::dropIfExists('replicated_purchase_items');
        Schema::dropIfExists('replicated_purchases');
    }
};

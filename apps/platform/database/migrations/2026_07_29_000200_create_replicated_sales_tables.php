<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replicated_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sync_batch_id')->nullable()->constrained('sync_batches')->nullOnDelete();
            $table->uuid('uuid');
            $table->string('sale_number');
            $table->string('transaction_number')->nullable();
            $table->uuid('cashier_uuid')->nullable();
            $table->string('cashier_name')->nullable();
            $table->uuid('customer_uuid')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('sale_type')->default('retail');
            $table->string('status')->default('completed');
            $table->string('payment_status')->default('paid');
            $table->timestamp('sold_at');
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('cost_minor')->default(0);
            $table->bigInteger('gross_profit_minor')->default(0);
            $table->json('cancellation')->nullable();
            $table->json('return_summary')->nullable();
            $table->unsignedInteger('source_version')->default(1);
            $table->string('source_idempotency_key');
            $table->timestamp('source_occurred_at');
            $table->timestamps();

            $table->unique(['business_id', 'uuid'], 'replicated_sales_business_uuid_unique');
            $table->unique(['business_id', 'source_idempotency_key'], 'replicated_sales_business_idempotency_unique');
            $table->index(['business_id', 'branch_id', 'sold_at'], 'replicated_sales_scope_date_idx');
            $table->index(['business_id', 'branch_id', 'status', 'sold_at'], 'replicated_sales_scope_status_date_idx');
            $table->index(['business_id', 'cashier_uuid', 'sold_at'], 'replicated_sales_cashier_date_idx');
            $table->index(['business_id', 'customer_uuid', 'sold_at'], 'replicated_sales_customer_date_idx');
            $table->index(['business_id', 'payment_status', 'sold_at'], 'replicated_sales_payment_status_idx');
            $table->index(['business_id', 'transaction_number'], 'replicated_sales_transaction_idx');
        });

        Schema::create('replicated_sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('replicated_sale_id')->nullable()->constrained('replicated_sales')->cascadeOnDelete();
            $table->foreignId('sync_batch_id')->nullable()->constrained('sync_batches')->nullOnDelete();
            $table->uuid('uuid');
            $table->uuid('sale_uuid');
            $table->uuid('product_uuid')->nullable();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->uuid('category_uuid')->nullable();
            $table->string('category_name')->nullable();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->string('unit')->nullable();
            $table->unsignedBigInteger('unit_price_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('line_total_minor')->default(0);
            $table->unsignedBigInteger('cost_minor')->default(0);
            $table->bigInteger('gross_profit_minor')->default(0);
            $table->unsignedInteger('source_version')->default(1);
            $table->string('source_idempotency_key');
            $table->timestamp('source_occurred_at');
            $table->timestamps();

            $table->unique(['business_id', 'uuid'], 'replicated_sale_items_business_uuid_unique');
            $table->unique(['business_id', 'source_idempotency_key'], 'replicated_sale_items_business_idempotency_unique');
            $table->index(['business_id', 'branch_id', 'sale_uuid'], 'replicated_sale_items_scope_sale_idx');
            $table->index(['business_id', 'product_uuid'], 'replicated_sale_items_product_idx');
            $table->index(['business_id', 'category_uuid'], 'replicated_sale_items_category_idx');
        });

        Schema::create('replicated_sale_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('replicated_sale_id')->nullable()->constrained('replicated_sales')->cascadeOnDelete();
            $table->foreignId('sync_batch_id')->nullable()->constrained('sync_batches')->nullOnDelete();
            $table->uuid('uuid');
            $table->uuid('sale_uuid');
            $table->string('method');
            $table->string('status')->default('completed');
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedInteger('source_version')->default(1);
            $table->string('source_idempotency_key');
            $table->timestamp('source_occurred_at');
            $table->timestamps();

            $table->unique(['business_id', 'uuid'], 'replicated_sale_payments_business_uuid_unique');
            $table->unique(['business_id', 'source_idempotency_key'], 'replicated_sale_payments_business_idempotency_unique');
            $table->index(['business_id', 'branch_id', 'sale_uuid'], 'replicated_sale_payments_scope_sale_idx');
            $table->index(['business_id', 'method', 'paid_at'], 'replicated_sale_payments_method_date_idx');
            $table->index(['business_id', 'status', 'paid_at'], 'replicated_sale_payments_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replicated_sale_payments');
        Schema::dropIfExists('replicated_sale_items');
        Schema::dropIfExists('replicated_sales');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replicated_inventory_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sync_batch_id')->nullable()->constrained('sync_batches')->nullOnDelete();
            $table->uuid('uuid');
            $table->uuid('product_uuid');
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('base_unit')->nullable();
            $table->uuid('warehouse_uuid')->nullable();
            $table->string('warehouse_name')->nullable();
            $table->uuid('category_uuid')->nullable();
            $table->string('category_name')->nullable();
            $table->uuid('brand_uuid')->nullable();
            $table->string('brand_name')->nullable();
            $table->decimal('available_quantity', 18, 4)->default(0);
            $table->decimal('reserved_quantity', 18, 4)->default(0);
            $table->unsignedBigInteger('stock_value_minor')->default(0);
            $table->decimal('reorder_level', 18, 4)->default(0);
            $table->decimal('overstock_level', 18, 4)->nullable();
            $table->string('stock_status')->default('normal');
            $table->date('expires_on')->nullable();
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamp('last_synchronized_at')->nullable();
            $table->unsignedInteger('source_version')->default(1);
            $table->string('source_idempotency_key');
            $table->timestamp('source_occurred_at');
            $table->timestamps();

            $table->unique(['business_id', 'branch_id', 'warehouse_uuid', 'product_uuid'], 'rep_inv_stock_scope_product_unique');
            $table->unique(['business_id', 'source_idempotency_key'], 'rep_inv_stock_idempotency_unique');
            $table->index(['business_id', 'branch_id', 'stock_status'], 'rep_inv_stock_scope_status_idx');
            $table->index(['business_id', 'product_uuid'], 'rep_inv_stock_product_idx');
            $table->index(['business_id', 'category_uuid'], 'rep_inv_stock_category_idx');
            $table->index(['business_id', 'brand_uuid'], 'rep_inv_stock_brand_idx');
            $table->index(['business_id', 'expires_on'], 'rep_inv_stock_expiry_idx');
        });

        Schema::create('replicated_inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sync_batch_id')->nullable()->constrained('sync_batches')->nullOnDelete();
            $table->uuid('uuid');
            $table->uuid('product_uuid');
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->uuid('warehouse_uuid')->nullable();
            $table->string('warehouse_name')->nullable();
            $table->string('movement_type');
            $table->decimal('quantity_delta', 18, 4)->default(0);
            $table->unsignedBigInteger('value_minor')->default(0);
            $table->string('reference')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->unsignedInteger('source_version')->default(1);
            $table->string('source_idempotency_key');
            $table->timestamp('source_occurred_at');
            $table->timestamps();

            $table->unique(['business_id', 'uuid'], 'rep_inv_movements_business_uuid_unique');
            $table->unique(['business_id', 'source_idempotency_key'], 'rep_inv_movements_idempotency_unique');
            $table->index(['business_id', 'branch_id', 'movement_type', 'occurred_at'], 'rep_inv_movements_scope_type_date_idx');
            $table->index(['business_id', 'product_uuid', 'occurred_at'], 'rep_inv_movements_product_date_idx');
        });

        Schema::create('replicated_stock_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sync_batch_id')->nullable()->constrained('sync_batches')->nullOnDelete();
            $table->uuid('uuid');
            $table->uuid('product_uuid')->nullable();
            $table->string('product_name');
            $table->string('alert_type');
            $table->string('severity')->default('warning');
            $table->string('status')->default('open');
            $table->text('message')->nullable();
            $table->timestamp('occurred_at');
            $table->unsignedInteger('source_version')->default(1);
            $table->string('source_idempotency_key');
            $table->timestamp('source_occurred_at');
            $table->timestamps();

            $table->unique(['business_id', 'uuid'], 'rep_stock_alerts_business_uuid_unique');
            $table->unique(['business_id', 'source_idempotency_key'], 'rep_stock_alerts_idempotency_unique');
            $table->index(['business_id', 'branch_id', 'alert_type', 'occurred_at'], 'rep_stock_alerts_scope_type_date_idx');
            $table->index(['business_id', 'status', 'severity'], 'rep_stock_alerts_status_severity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replicated_stock_alerts');
        Schema::dropIfExists('replicated_inventory_movements');
        Schema::dropIfExists('replicated_inventory_stocks');
    }
};

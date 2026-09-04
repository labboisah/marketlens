<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replicated_cashier_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sync_batch_id')->nullable()->constrained('sync_batches')->nullOnDelete();
            $table->uuid('uuid');
            $table->uuid('cashier_uuid')->nullable();
            $table->string('cashier_name')->nullable();
            $table->string('status')->default('open');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->unsignedInteger('source_version')->default(1);
            $table->string('source_idempotency_key');
            $table->timestamp('source_occurred_at');
            $table->timestamps();

            $table->unique(['business_id', 'uuid'], 'replicated_cashier_shifts_business_uuid_unique');
            $table->unique(['business_id', 'source_idempotency_key'], 'replicated_cashier_shifts_business_idempotency_unique');
            $table->index(['business_id', 'branch_id', 'opened_at'], 'replicated_cashier_shifts_scope_opened_idx');
            $table->index(['business_id', 'cashier_uuid', 'opened_at'], 'replicated_cashier_shifts_cashier_opened_idx');
            $table->index(['business_id', 'status', 'last_activity_at'], 'replicated_cashier_shifts_status_activity_idx');
        });

        Schema::table('staff_activity_summaries', function (Blueprint $table): void {
            $table->unsignedInteger('purchases_recorded_count')->default(0)->after('cashier_variance_minor');
            $table->unsignedInteger('stock_counts_count')->default(0)->after('purchases_recorded_count');
            $table->unsignedInteger('stock_adjustments_count')->default(0)->after('stock_counts_count');
            $table->unsignedInteger('damaged_stock_records_count')->default(0)->after('stock_adjustments_count');
            $table->unsignedInteger('transfers_processed_count')->default(0)->after('damaged_stock_records_count');
            $table->unsignedInteger('discounts_approved_count')->default(0)->after('transfers_processed_count');
            $table->unsignedInteger('expenses_approved_count')->default(0)->after('discounts_approved_count');
            $table->unsignedInteger('returns_approved_count')->default(0)->after('expenses_approved_count');
            $table->unsignedInteger('stock_adjustments_approved_count')->default(0)->after('returns_approved_count');
            $table->unsignedInteger('cashier_settlements_reviewed_count')->default(0)->after('stock_adjustments_approved_count');
            $table->unsignedInteger('outside_normal_hours_count')->default(0)->after('cashier_settlements_reviewed_count');
            $table->unsignedInteger('excessive_discount_count')->default(0)->after('outside_normal_hours_count');
            $table->unsignedInteger('high_cancellation_count')->default(0)->after('excessive_discount_count');
            $table->unsignedInteger('high_return_count')->default(0)->after('high_cancellation_count');
            $table->unsignedInteger('repeated_stock_adjustment_count')->default(0)->after('high_return_count');
        });
    }

    public function down(): void
    {
        Schema::table('staff_activity_summaries', function (Blueprint $table): void {
            $table->dropColumn([
                'purchases_recorded_count',
                'stock_counts_count',
                'stock_adjustments_count',
                'damaged_stock_records_count',
                'transfers_processed_count',
                'discounts_approved_count',
                'expenses_approved_count',
                'returns_approved_count',
                'stock_adjustments_approved_count',
                'cashier_settlements_reviewed_count',
                'outside_normal_hours_count',
                'excessive_discount_count',
                'high_cancellation_count',
                'high_return_count',
                'repeated_stock_adjustment_count',
            ]);
        });

        Schema::dropIfExists('replicated_cashier_shifts');
    }
};

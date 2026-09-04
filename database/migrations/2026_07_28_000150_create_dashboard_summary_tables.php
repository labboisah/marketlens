<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_dashboard_daily_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('summary_date');
            $table->unsignedBigInteger('gross_sales_minor')->default(0);
            $table->unsignedBigInteger('net_sales_minor')->default(0);
            $table->unsignedInteger('completed_sales_count')->default(0);
            $table->unsignedBigInteger('cash_payments_minor')->default(0);
            $table->unsignedBigInteger('bank_transfer_payments_minor')->default(0);
            $table->unsignedBigInteger('pos_terminal_payments_minor')->default(0);
            $table->unsignedBigInteger('credit_sales_minor')->default(0);
            $table->unsignedBigInteger('expenses_minor')->default(0);
            $table->unsignedBigInteger('gross_profit_minor')->default(0);
            $table->bigInteger('estimated_net_profit_minor')->default(0);
            $table->unsignedBigInteger('customer_debt_minor')->default(0);
            $table->unsignedBigInteger('supplier_balance_minor')->default(0);
            $table->unsignedBigInteger('current_stock_value_minor')->default(0);
            $table->unsignedInteger('low_stock_products_count')->default(0);
            $table->bigInteger('cashier_variance_minor')->default(0);
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->timestamp('summarized_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'branch_id', 'summary_date'], 'dashboard_daily_business_branch_date_unique');
            $table->index(['business_id', 'summary_date'], 'dash_daily_business_date_idx');
            $table->index(['business_id', 'branch_id', 'summary_date'], 'dashboard_daily_business_branch_date_index');
        });

        Schema::create('dashboard_product_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('summary_date');
            $table->uuid('product_uuid');
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->decimal('quantity_sold', 18, 4)->default(0);
            $table->unsignedBigInteger('sales_minor')->default(0);
            $table->unsignedInteger('days_without_sale')->default(0);
            $table->boolean('is_low_stock')->default(false);
            $table->decimal('stock_quantity', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'branch_id', 'summary_date', 'product_uuid'], 'dashboard_product_summary_unique');
            $table->index(['business_id', 'branch_id', 'is_low_stock'], 'dashboard_product_low_stock_index');
            $table->index(['business_id', 'summary_date', 'quantity_sold'], 'dash_product_date_qty_idx');
        });

        Schema::create('dashboard_branch_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('summary_date');
            $table->unsignedBigInteger('gross_sales_minor')->default(0);
            $table->unsignedBigInteger('net_sales_minor')->default(0);
            $table->unsignedInteger('completed_sales_count')->default(0);
            $table->bigInteger('cashier_variance_minor')->default(0);
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'branch_id', 'summary_date'], 'dash_branch_business_branch_date_unique');
            $table->index(['business_id', 'summary_date'], 'dash_branch_business_date_idx');
        });

        Schema::create('dashboard_cashier_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('summary_date');
            $table->uuid('cashier_uuid');
            $table->string('cashier_name');
            $table->bigInteger('variance_minor')->default(0);
            $table->string('status')->default('open');
            $table->timestamps();

            $table->index(['business_id', 'branch_id', 'summary_date'], 'dashboard_cashier_business_branch_date_index');
            $table->index(['business_id', 'status'], 'dash_cashier_business_status_idx');
        });

        Schema::create('dashboard_business_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['business_id', 'branch_id', 'occurred_at'], 'dashboard_activity_business_branch_time_index');
            $table->index(['business_id', 'type'], 'dash_activity_business_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_business_activities');
        Schema::dropIfExists('dashboard_cashier_exceptions');
        Schema::dropIfExists('dashboard_branch_summaries');
        Schema::dropIfExists('dashboard_product_summaries');
        Schema::dropIfExists('business_dashboard_daily_summaries');
    }
};

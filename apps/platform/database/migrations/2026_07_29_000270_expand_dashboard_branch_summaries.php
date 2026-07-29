<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_branch_summaries', function (Blueprint $table): void {
            $table->unsignedBigInteger('gross_profit_minor')->default(0)->after('completed_sales_count');
            $table->unsignedBigInteger('expenses_minor')->default(0)->after('gross_profit_minor');
            $table->bigInteger('estimated_net_profit_minor')->default(0)->after('expenses_minor');
            $table->unsignedBigInteger('stock_value_minor')->default(0)->after('estimated_net_profit_minor');
            $table->unsignedBigInteger('customer_debt_minor')->default(0)->after('stock_value_minor');
            $table->unsignedBigInteger('supplier_balance_minor')->default(0)->after('customer_debt_minor');
            $table->unsignedInteger('low_stock_count')->default(0)->after('supplier_balance_minor');
            $table->unsignedInteger('active_client_apps_count')->default(0)->after('low_stock_count');
            $table->timestamp('summarized_at')->nullable()->after('last_successful_sync_at');
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_branch_summaries', function (Blueprint $table): void {
            $table->dropColumn([
                'gross_profit_minor',
                'expenses_minor',
                'estimated_net_profit_minor',
                'stock_value_minor',
                'customer_debt_minor',
                'supplier_balance_minor',
                'low_stock_count',
                'active_client_apps_count',
                'summarized_at',
            ]);
        });
    }
};

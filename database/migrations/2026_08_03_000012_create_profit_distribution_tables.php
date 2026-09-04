<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('profit_periods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('business_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('gross_revenue', 14, 4)->default(0);
            $table->decimal('cogs', 14, 4)->default(0);
            $table->decimal('direct_service_expenses', 14, 4)->default(0);
            $table->decimal('operating_expenses', 14, 4)->default(0);
            $table->decimal('tax_provision', 14, 4)->default(0);
            $table->decimal('net_profit', 14, 4)->default(0);
            $table->decimal('retained_amount', 14, 4)->default(0);
            $table->decimal('distributable_amount', 14, 4)->default(0);
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('profit_distribution_resolutions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('profit_period_id');
            $table->uuid('resolved_by')->nullable();
            $table->text('notes')->nullable();
            $table->date('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shareholder_distributions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('profit_period_id');
            $table->unsignedBigInteger('shareholder_id');
            $table->decimal('entitlement', 14, 4)->default(0);
            $table->decimal('paid', 14, 4)->default(0);
            $table->decimal('outstanding', 14, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('shareholder_distribution_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('distribution_id');
            $table->decimal('amount', 14, 4);
            $table->uuid('recorded_by')->nullable();
            $table->date('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shareholder_distribution_payments');
        Schema::dropIfExists('shareholder_distributions');
        Schema::dropIfExists('profit_distribution_resolutions');
        Schema::dropIfExists('profit_periods');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('replicated_service_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('source_uuid')->unique();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('branch_id');
            $table->uuid('device_uuid')->nullable();
            $table->string('source_version')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('total_amount', 14, 4)->default(0);
            $table->decimal('expenses_total', 14, 4)->default(0);
            $table->decimal('payments_total', 14, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('replicated_service_order_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('replicated_service_order_id');
            $table->uuid('source_uuid')->unique();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->decimal('price', 14, 4)->default(0);
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('replicated_service_staff_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('replicated_service_order_item_id');
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->timestamps();
        });

        Schema::create('replicated_service_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('replicated_service_order_id');
            $table->uuid('source_uuid')->unique();
            $table->decimal('amount', 14, 4);
            $table->string('status')->default('unpaid');
            $table->timestamps();
        });

        Schema::create('replicated_service_expenses', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('replicated_service_order_id');
            $table->uuid('source_uuid')->unique();
            $table->decimal('amount', 14, 4);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('replicated_service_revenue_allocations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('replicated_service_order_id');
            $table->string('status')->default('pending');
            $table->decimal('amount', 14, 4)->default(0);
            $table->json('components')->nullable();
            $table->timestamps();
        });

        Schema::create('replicated_staff_earning_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('replicated_service_order_id')->nullable();
            $table->decimal('amount', 14, 4);
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('replicated_staff_settlements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('staff_id');
            $table->decimal('amount', 14, 4);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replicated_staff_settlements');
        Schema::dropIfExists('replicated_staff_earning_transactions');
        Schema::dropIfExists('replicated_service_revenue_allocations');
        Schema::dropIfExists('replicated_service_expenses');
        Schema::dropIfExists('replicated_service_payments');
        Schema::dropIfExists('replicated_service_staff_assignments');
        Schema::dropIfExists('replicated_service_order_items');
        Schema::dropIfExists('replicated_service_orders');
    }
};

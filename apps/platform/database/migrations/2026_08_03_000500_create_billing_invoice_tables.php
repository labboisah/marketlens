<?php

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('balance_minor')->default(0);
            $table->char('currency', 3)->default('NGN');
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_account_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_account_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('type');
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->char('currency', 3)->default('NGN');
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();
            $table->index(['billing_account_id', 'type', 'recorded_at'], 'billing_account_adjustments_idx');
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('invoice_number')->unique();
            $table->string('status')->default(InvoiceStatus::Issued->value);
            $table->char('currency', 3)->default('NGN');
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->string('description')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'status'], 'invoices_business_status_idx');
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->char('currency', 3)->default('NGN');
            $table->string('status')->default(PaymentStatus::Pending->value);
            $table->string('method')->default('manual');
            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'status'], 'invoice_payments_invoice_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('billing_account_adjustments');
        Schema::dropIfExists('billing_accounts');
    }
};
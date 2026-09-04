<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('billing_name');
            $table->string('billing_email');
            $table->string('billing_phone')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('tax_number')->nullable();
            $table->char('currency', 3)->default('NGN');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->json('warnings')->nullable();
            $table->json('usage_snapshot')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'type', 'status'], 'subscription_changes_business_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_change_requests');
        Schema::dropIfExists('billing_profiles');
    }
};

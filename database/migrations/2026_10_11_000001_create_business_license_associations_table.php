<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_license_associations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('provider')->default('kernelbridge');
            $table->string('product_code');
            $table->string('license_uuid')->nullable();
            $table->string('subscription_uuid')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('grace_expires_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->json('entitlements')->nullable();
            $table->string('entitlement_revision')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->string('provider_event_id')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'provider', 'product_code'], 'biz_license_assoc_unique');
            $table->index(['provider', 'product_code', 'status'], 'biz_license_assoc_provider_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_license_associations');
    }
};

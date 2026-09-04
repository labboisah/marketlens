<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            if (! Schema::hasColumn('businesses', 'default_branch_id')) {
                $table->foreignId('default_branch_id')->nullable()->after('monitoring_preferences')->constrained('branches')->nullOnDelete();
            }

            if (! Schema::hasColumn('businesses', 'account_closure_requested_at')) {
                $table->timestamp('account_closure_requested_at')->nullable()->after('default_branch_id');
            }

            if (! Schema::hasColumn('businesses', 'account_closure_requested_by')) {
                $table->foreignId('account_closure_requested_by')->nullable()->after('account_closure_requested_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('businesses', 'account_closure_reason')) {
                $table->text('account_closure_reason')->nullable()->after('account_closure_requested_by');
            }
        });

        if (! Schema::hasTable('business_data_exports')) {
            Schema::create('business_data_exports', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
                $table->uuid('uuid')->unique();
                $table->string('status')->default('completed');
                $table->string('format')->default('json');
                $table->json('filters')->nullable();
                $table->longText('payload')->nullable();
                $table->timestamp('requested_at');
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'status', 'requested_at'], 'business_exports_business_status_requested_idx');
            });
        }

        if (! Schema::hasTable('business_trusted_devices')) {
            Schema::create('business_trusted_devices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('trusted_at');
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'user_id', 'revoked_at'], 'trusted_devices_business_user_revoked_idx');
            });
        }

        if (! Schema::hasTable('user_security_settings')) {
            Schema::create('user_security_settings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->boolean('two_factor_enabled')->default(false);
                $table->timestamp('two_factor_confirmed_at')->nullable();
                $table->json('recovery_codes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Intentionally left non-destructive. This migration repairs partially-applied local schemas.
    }
};

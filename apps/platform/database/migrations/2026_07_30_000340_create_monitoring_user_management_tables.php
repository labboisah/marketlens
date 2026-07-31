<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('monitoring_roles')) {
            Schema::create('monitoring_roles', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_owner_role')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('monitoring_permissions')) {
            Schema::create('monitoring_permissions', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('monitoring_permission_role')) {
            Schema::create('monitoring_permission_role', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('monitoring_role_id')->constrained('monitoring_roles')->cascadeOnDelete();
                $table->foreignId('monitoring_permission_id')->constrained('monitoring_permissions')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['monitoring_role_id', 'monitoring_permission_id'], 'monitoring_role_permission_unique');
            });
        }

        if (! Schema::hasTable('business_monitoring_users')) {
            Schema::create('business_monitoring_users', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('monitoring_role_id')->constrained('monitoring_roles')->restrictOnDelete();
                $table->string('status')->default('active');
                $table->timestamp('suspended_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('module_permissions')->nullable();
                $table->timestamps();
                $table->unique(['business_id', 'user_id'], 'business_monitoring_users_unique');
                $table->index(['business_id', 'status']);
            });
        }

        if (! Schema::hasTable('business_monitoring_user_branch')) {
            Schema::create('business_monitoring_user_branch', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('business_monitoring_user_id');
                $table->foreign('business_monitoring_user_id', 'bmu_branch_assignment_fk')
                    ->references('id')
                    ->on('business_monitoring_users')
                    ->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['business_monitoring_user_id', 'branch_id'], 'monitoring_user_branch_unique');
            });
        }

        if (! Schema::hasTable('monitoring_user_invitations')) {
            Schema::create('monitoring_user_invitations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->foreignId('monitoring_role_id')->constrained('monitoring_roles')->restrictOnDelete();
                $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
                $table->uuid('uuid')->unique();
                $table->string('email');
                $table->string('token_hash')->unique();
                $table->json('branch_ids')->nullable();
                $table->json('module_permissions')->nullable();
                $table->string('status')->default('pending');
                $table->timestamp('expires_at');
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('last_sent_at')->nullable();
                $table->unsignedInteger('send_count')->default(1);
                $table->timestamps();
                $table->index(['business_id', 'email', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_user_invitations');
        Schema::dropIfExists('business_monitoring_user_branch');
        Schema::dropIfExists('business_monitoring_users');
        Schema::dropIfExists('monitoring_permission_role');
        Schema::dropIfExists('monitoring_permissions');
        Schema::dropIfExists('monitoring_roles');
    }
};


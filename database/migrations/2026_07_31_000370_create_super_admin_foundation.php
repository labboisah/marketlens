<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'is_super_admin')) {
                $table->boolean('is_super_admin')->default(false)->after('account_status');
            }
            if (! Schema::hasColumn('users', 'super_admin_confirmed_at')) {
                $table->timestamp('super_admin_confirmed_at')->nullable()->after('is_super_admin');
            }
        });

        if (! Schema::hasTable('system_announcements')) {
            Schema::create('system_announcements', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title');
                $table->text('body');
                $table->string('severity')->default('information');
                $table->boolean('is_published')->default(false);
                $table->timestamp('published_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->index(['is_published', 'published_at']);
            });
        }

        if (! Schema::hasTable('super_admin_action_confirmations')) {
            Schema::create('super_admin_action_confirmations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('action');
                $table->string('confirmation_phrase');
                $table->timestamp('confirmed_at');
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'action', 'confirmed_at'], 'super_admin_confirmation_idx');
            });
        }

        if (! Schema::hasTable('support_access_requests')) {
            Schema::create('support_access_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reason');
                $table->string('status')->default('pending');
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->index(['business_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_access_requests');
        Schema::dropIfExists('super_admin_action_confirmations');
        Schema::dropIfExists('system_announcements');

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'super_admin_confirmed_at')) {
                $table->dropColumn('super_admin_confirmed_at');
            }
            if (Schema::hasColumn('users', 'is_super_admin')) {
                $table->dropColumn('is_super_admin');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_notifications', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->after('business_id')->constrained()->nullOnDelete();
            $table->string('severity')->default('information')->after('type');
            $table->string('category')->default('general')->after('severity');
            $table->string('status')->default('open')->after('category');
            $table->string('related_record_type')->nullable()->after('body');
            $table->string('related_record_uuid')->nullable()->after('related_record_type');
            $table->string('dedupe_key')->nullable()->after('related_record_uuid');
            $table->json('channels')->nullable()->after('dedupe_key');
            $table->json('delivered_channels')->nullable()->after('channels');
            $table->timestamp('resolved_at')->nullable()->after('read_at');
            $table->text('resolution_note')->nullable()->after('resolved_at');
            $table->foreignId('resolved_by')->nullable()->after('resolution_note')->constrained('users')->nullOnDelete();
            $table->index(['business_id', 'branch_id', 'category', 'created_at'], 'notifications_scope_category_created_idx');
            $table->index(['business_id', 'read_at'], 'notifications_business_read_idx');
            $table->index(['business_id', 'resolved_at'], 'notifications_business_resolved_idx');
            $table->unique(['business_id', 'dedupe_key'], 'notifications_business_dedupe_unique');
        });

        Schema::create('alert_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('channel');
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'user_id', 'category', 'channel'], 'alert_preferences_unique');
            $table->index(['business_id', 'category', 'channel'], 'alert_preferences_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_notification_preferences');

        Schema::table('platform_notifications', function (Blueprint $table): void {
            $table->dropUnique('notifications_business_dedupe_unique');
            $table->dropIndex('notifications_scope_category_created_idx');
            $table->dropIndex('notifications_business_read_idx');
            $table->dropIndex('notifications_business_resolved_idx');
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn([
                'severity',
                'category',
                'status',
                'related_record_type',
                'related_record_uuid',
                'dedupe_key',
                'channels',
                'delivered_channels',
                'resolved_at',
                'resolution_note',
            ]);
        });
    }
};

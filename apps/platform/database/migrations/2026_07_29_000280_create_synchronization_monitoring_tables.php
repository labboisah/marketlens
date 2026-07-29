<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->timestamp('requires_update_at')->nullable()->after('revoked_at');
            $table->string('required_version')->nullable()->after('requires_update_at');
            $table->foreignId('update_required_by')->nullable()->after('required_version')->constrained('users')->nullOnDelete();
            $table->index(['business_id', 'type', 'last_seen_at'], 'devices_business_type_last_seen_idx');
            $table->index(['business_id', 'type', 'last_synchronized_at'], 'devices_business_type_last_sync_idx');
        });

        Schema::table('sync_batches', function (Blueprint $table): void {
            $table->timestamp('retry_requested_at')->nullable()->after('last_acknowledged_at');
            $table->foreignId('retry_requested_by')->nullable()->after('retry_requested_at')->constrained('users')->nullOnDelete();
            $table->index(['business_id', 'status', 'received_at'], 'sync_batches_business_status_received_idx');
            $table->index(['business_id', 'protocol_version'], 'sync_batches_business_protocol_idx');
        });

        Schema::table('sync_records', function (Blueprint $table): void {
            $table->timestamp('reviewed_at')->nullable()->after('acknowledged_at');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->index(['business_id', 'status', 'created_at'], 'sync_records_business_status_created_idx');
        });

        Schema::create('device_heartbeat_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_uuid');
            $table->string('status');
            $table->string('app_version')->nullable();
            $table->json('operating_system')->nullable();
            $table->json('synchronization_errors')->nullable();
            $table->timestamp('last_synchronized_at')->nullable();
            $table->timestamp('reported_at');
            $table->timestamps();

            $table->index(['business_id', 'device_id', 'reported_at'], 'heartbeat_business_device_reported_idx');
            $table->index(['business_id', 'branch_id', 'reported_at'], 'heartbeat_business_branch_reported_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_heartbeat_logs');

        Schema::table('sync_records', function (Blueprint $table): void {
            $table->dropIndex('sync_records_business_status_created_idx');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn('reviewed_at');
        });

        Schema::table('sync_batches', function (Blueprint $table): void {
            $table->dropIndex('sync_batches_business_status_received_idx');
            $table->dropIndex('sync_batches_business_protocol_idx');
            $table->dropConstrainedForeignId('retry_requested_by');
            $table->dropColumn('retry_requested_at');
        });

        Schema::table('devices', function (Blueprint $table): void {
            $table->dropIndex('devices_business_type_last_seen_idx');
            $table->dropIndex('devices_business_type_last_sync_idx');
            $table->dropConstrainedForeignId('update_required_by');
            $table->dropColumn(['requires_update_at', 'required_version']);
        });
    }
};

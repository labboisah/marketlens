<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_batches', function (Blueprint $table): void {
            $table->string('protocol_version')->default('1.0')->after('uuid');
            $table->string('trace_id')->nullable()->after('records_count');
            $table->string('payload_hash')->nullable()->after('trace_id');
            $table->json('accepted_records')->nullable()->after('payload_hash');
            $table->json('rejected_records')->nullable()->after('accepted_records');
            $table->json('conflicting_records')->nullable()->after('rejected_records');
            $table->json('processing_errors')->nullable()->after('conflicting_records');
            $table->text('invalid_payload_excerpt')->nullable()->after('processing_errors');
            $table->timestamp('last_acknowledged_at')->nullable()->after('processed_at');
            $table->index(['business_id', 'branch_id', 'received_at'], 'sync_batches_business_branch_received_idx');
            $table->index(['business_id', 'uuid'], 'sync_batches_business_uuid_idx');
            $table->index(['trace_id'], 'sync_batches_trace_id_idx');
        });

        Schema::create('sync_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sync_batch_id')->constrained('sync_batches')->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->uuid('record_uuid');
            $table->string('entity');
            $table->string('operation');
            $table->unsignedInteger('version');
            $table->string('idempotency_key');
            $table->string('payload_hash');
            $table->string('status');
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('retryable')->default(false);
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'idempotency_key'], 'sync_records_business_idempotency_unique');
            $table->unique(['business_id', 'record_uuid', 'version'], 'sync_records_business_record_version_unique');
            $table->index(['business_id', 'branch_id', 'entity', 'occurred_at'], 'sync_records_business_branch_entity_time_idx');
            $table->index(['business_id', 'record_uuid'], 'sync_records_business_record_uuid_idx');
        });

        Schema::create('sync_checkpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->uuid('last_batch_uuid')->nullable();
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->unsignedBigInteger('accepted_records_count')->default(0);
            $table->unsignedBigInteger('rejected_records_count')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'branch_id', 'device_id'], 'sync_checkpoints_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_checkpoints');
        Schema::dropIfExists('sync_records');

        Schema::table('sync_batches', function (Blueprint $table): void {
            $table->dropIndex('sync_batches_business_branch_received_idx');
            $table->dropIndex('sync_batches_business_uuid_idx');
            $table->dropIndex('sync_batches_trace_id_idx');
            $table->dropColumn([
                'protocol_version',
                'trace_id',
                'payload_hash',
                'accepted_records',
                'rejected_records',
                'conflicting_records',
                'processing_errors',
                'invalid_payload_excerpt',
                'last_acknowledged_at',
            ]);
        });
    }
};

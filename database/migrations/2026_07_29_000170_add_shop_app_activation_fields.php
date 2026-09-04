<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->foreignId('application_version_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->string('credential_hash')->nullable()->after('status');
            $table->string('credential_preview')->nullable()->after('credential_hash');
            $table->string('identity_hash')->nullable()->after('credential_preview');
            $table->string('app_version')->nullable()->after('identity_hash');
            $table->json('operating_system')->nullable()->after('app_version');
            $table->json('synchronization_errors')->nullable()->after('operating_system');
            $table->timestamp('activated_at')->nullable()->after('last_seen_at');
            $table->timestamp('last_synchronized_at')->nullable()->after('activated_at');
            $table->timestamp('revoked_at')->nullable()->after('last_synchronized_at');
            $table->index(['business_id', 'type', 'status'], 'devices_business_type_status_idx');
            $table->index(['credential_hash'], 'devices_credential_hash_idx');
        });

        Schema::table('device_activations', function (Blueprint $table): void {
            $table->foreignId('application_version_id')->nullable()->after('device_id')->constrained()->nullOnDelete();
            $table->timestamp('used_at')->nullable()->after('activated_at');
            $table->timestamp('revoked_at')->nullable()->after('used_at');
            $table->unsignedSmallInteger('failed_attempts')->default(0)->after('revoked_at');
            $table->index(['activation_code_hash'], 'device_activation_code_hash_idx');
            $table->index(['expires_at', 'revoked_at', 'used_at'], 'device_activation_validity_idx');
        });
    }

    public function down(): void
    {
        Schema::table('device_activations', function (Blueprint $table): void {
            $table->dropIndex('device_activation_code_hash_idx');
            $table->dropIndex('device_activation_validity_idx');
            $table->dropConstrainedForeignId('application_version_id');
            $table->dropColumn(['used_at', 'revoked_at', 'failed_attempts']);
        });

        Schema::table('devices', function (Blueprint $table): void {
            $table->dropIndex('devices_business_type_status_idx');
            $table->dropIndex('devices_credential_hash_idx');
            $table->dropConstrainedForeignId('application_version_id');
            $table->dropColumn([
                'credential_hash',
                'credential_preview',
                'identity_hash',
                'app_version',
                'operating_system',
                'synchronization_errors',
                'activated_at',
                'last_synchronized_at',
                'revoked_at',
            ]);
        });
    }
};

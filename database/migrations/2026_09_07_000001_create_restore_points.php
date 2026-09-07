<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restore_points', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('device_id')->constrained();
            $table->string('name', 120);
            $table->string('application_version', 80);
            $table->unsignedInteger('schema_version');
            $table->timestamp('captured_at');
            $table->string('checksum', 64);
            $table->string('encrypted_checksum', 64);
            $table->unsignedBigInteger('size');
            $table->string('disk');
            $table->string('path');
            $table->string('verification_status')->default('ciphertext_verified');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['business_id', 'branch_id', 'device_id']);
        });
        Schema::create('restore_point_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('device_id')->constrained();
            $table->foreignId('restore_point_id')->nullable()->constrained();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('kind');
            $table->string('name', 120);
            $table->string('status')->default('pending');
            $table->string('detail', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['device_id', 'status']);
        });
        foreach (['view' => 'View restore points', 'create' => 'Create restore points', 'restore' => 'Request database recovery', 'delete' => 'Delete restore points'] as $key => $name) {
            DB::table('monitoring_permissions')->insertOrIgnore(['slug' => 'backups.'.$key, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restore_point_requests');
        Schema::dropIfExists('restore_points');
    }
};

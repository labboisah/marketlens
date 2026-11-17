<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->uuid('kernelbridge_license_uuid')->nullable();
            $table->uuid('kernelbridge_installation_uuid')->nullable();
            $table->uuid('kernelbridge_installation_grant_uuid')->nullable()->unique('device_kb_grant_unique');
            $table->string('kernelbridge_grant_status', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table): void {
            $table->dropUnique('device_kb_grant_unique');
            $table->dropColumn([
                'kernelbridge_license_uuid',
                'kernelbridge_installation_uuid',
                'kernelbridge_installation_grant_uuid',
                'kernelbridge_grant_status',
            ]);
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_records', function (Blueprint $table): void {
            $table->dropUnique('sync_records_business_record_version_unique');
            $table->unique(
                ['business_id', 'entity', 'record_uuid', 'version'],
                'sync_records_business_entity_record_version_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('sync_records', function (Blueprint $table): void {
            $table->dropUnique('sync_records_business_entity_record_version_unique');
            $table->unique(
                ['business_id', 'record_uuid', 'version'],
                'sync_records_business_record_version_unique',
            );
        });
    }
};

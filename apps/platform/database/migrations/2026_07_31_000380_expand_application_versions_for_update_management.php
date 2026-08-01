<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_versions', function (Blueprint $table): void {
            if (! Schema::hasColumn('application_versions', 'platform')) {
                $table->string('platform')->nullable()->after('application');
            }
            if (! Schema::hasColumn('application_versions', 'architecture')) {
                $table->string('architecture')->nullable()->after('platform');
            }
            if (! Schema::hasColumn('application_versions', 'release_channel')) {
                $table->string('release_channel')->default('stable')->after('version');
            }
            if (! Schema::hasColumn('application_versions', 'minimum_supported_version')) {
                $table->string('minimum_supported_version')->nullable()->after('release_notes');
            }
            if (! Schema::hasColumn('application_versions', 'recommended_version')) {
                $table->string('recommended_version')->nullable()->after('minimum_supported_version');
            }
            if (! Schema::hasColumn('application_versions', 'mandatory_update')) {
                $table->boolean('mandatory_update')->default(false)->after('recommended_version');
            }
            if (! Schema::hasColumn('application_versions', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('mandatory_update');
            }
            if (! Schema::hasColumn('application_versions', 'download_disk')) {
                $table->string('download_disk')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('application_versions', 'download_path')) {
                $table->string('download_path')->nullable()->after('download_disk');
            }
            if (! Schema::hasColumn('application_versions', 'download_original_name')) {
                $table->string('download_original_name')->nullable()->after('download_path');
            }
            if (! Schema::hasColumn('application_versions', 'download_mime_type')) {
                $table->string('download_mime_type')->nullable()->after('download_original_name');
            }
            if (! Schema::hasColumn('application_versions', 'download_size_bytes')) {
                $table->unsignedBigInteger('download_size_bytes')->nullable()->after('download_mime_type');
            }
            if (! Schema::hasColumn('application_versions', 'checksum')) {
                $table->string('checksum')->nullable()->after('download_size_bytes');
            }

            $table->index(['application', 'platform', 'architecture', 'release_channel', 'status', 'is_active'], 'app_versions_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::table('application_versions', function (Blueprint $table): void {
            $table->dropIndex('app_versions_lookup_idx');
            $table->dropColumn([
                'platform',
                'architecture',
                'release_channel',
                'minimum_supported_version',
                'recommended_version',
                'mandatory_update',
                'is_active',
                'download_disk',
                'download_path',
                'download_original_name',
                'download_mime_type',
                'download_size_bytes',
                'checksum',
            ]);
        });
    }
};

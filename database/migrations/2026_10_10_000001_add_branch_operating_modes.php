<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->json('operating_modes')->nullable()->after('module_settings');
        });

        DB::table('branches')->whereNull('operating_modes')->update([
            'operating_modes' => json_encode(['sales'], JSON_THROW_ON_ERROR),
        ]);
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('operating_modes');
        });
    }
};
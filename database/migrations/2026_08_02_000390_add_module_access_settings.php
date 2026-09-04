<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_features', function (Blueprint $table): void {
            $table->string('key')->nullable()->after('plan_id');
            $table->unique(['plan_id', 'key'], 'plan_features_plan_key_unique');
        });

        Schema::table('businesses', function (Blueprint $table): void {
            $table->json('module_settings')->nullable()->after('monitoring_preferences');
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->json('module_settings')->nullable()->after('is_head_office');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('module_settings');
        });

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn('module_settings');
        });

        Schema::table('plan_features', function (Blueprint $table): void {
            $table->dropUnique('plan_features_plan_key_unique');
            $table->dropColumn('key');
        });
    }
};

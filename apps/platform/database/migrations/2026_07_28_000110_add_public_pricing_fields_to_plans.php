<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
            $table->text('description')->nullable()->after('slug');
            $table->unsignedSmallInteger('trial_days')->default(14)->after('currency');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('trial_days');
        });

        Schema::create('plan_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('tooltip')->nullable();
            $table->boolean('is_highlighted')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['plan_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');

        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn(['slug', 'description', 'trial_days', 'sort_order']);
        });
    }
};

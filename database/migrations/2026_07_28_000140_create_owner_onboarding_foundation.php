<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('business_type')->nullable()->after('legal_name');
            $table->string('registration_number')->nullable()->after('business_type');
            $table->string('tax_number')->nullable()->after('registration_number');
            $table->string('phone')->nullable()->after('tax_number');
            $table->string('alternative_phone')->nullable()->after('phone');
            $table->string('email')->nullable()->after('alternative_phone');
            $table->text('address')->nullable()->after('email');
            $table->string('state')->nullable()->after('address');
            $table->string('local_government_area')->nullable()->after('state');
            $table->char('default_currency', 3)->default('NGN')->after('local_government_area');
            $table->string('timezone')->default('Africa/Lagos')->after('default_currency');
            $table->string('preferred_language')->default('en')->after('timezone');
            $table->string('logo_path')->nullable()->after('preferred_language');
            $table->string('onboarding_status')->default('completed')->after('status');
            $table->json('monitoring_preferences')->nullable()->after('onboarding_status');
            $table->timestamp('onboarded_at')->nullable()->after('monitoring_preferences');
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->string('phone')->nullable()->after('code');
            $table->string('email')->nullable()->after('phone');
            $table->text('address')->nullable()->after('email');
            $table->string('state')->nullable()->after('address');
            $table->string('local_government_area')->nullable()->after('state');
            $table->boolean('is_head_office')->default(true)->after('local_government_area');
        });

        Schema::create('owner_onboarding_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->json('data')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_onboarding_drafts');

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn(['phone', 'email', 'address', 'state', 'local_government_area', 'is_head_office']);
        });

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn([
                'legal_name',
                'business_type',
                'registration_number',
                'tax_number',
                'phone',
                'alternative_phone',
                'email',
                'address',
                'state',
                'local_government_area',
                'default_currency',
                'timezone',
                'preferred_language',
                'logo_path',
                'onboarding_status',
                'monitoring_preferences',
                'onboarded_at',
            ]);
        });
    }
};

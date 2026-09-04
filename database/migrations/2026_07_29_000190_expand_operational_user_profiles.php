<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operational_user_profiles', function (Blueprint $table): void {
            $table->string('employee_number')->nullable()->after('uuid');
            $table->string('username')->nullable()->after('display_name');
            $table->string('staff_code')->nullable()->after('username');
            $table->string('role_code')->nullable()->after('role_name');
            $table->string('status')->default('active')->after('role_code');
            $table->timestamp('replicated_created_at')->nullable()->after('status');
            $table->timestamp('replicated_updated_at')->nullable()->after('replicated_created_at');
            $table->index(['business_id', 'branch_id', 'status'], 'operational_profiles_scope_status_idx');
            $table->index(['business_id', 'role_code'], 'operational_profiles_business_role_idx');
        });

        Schema::create('staff_activity_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->uuid('operational_user_uuid');
            $table->date('summary_date');
            $table->string('display_name');
            $table->string('role_name')->nullable();
            $table->unsignedInteger('sales_count')->default(0);
            $table->unsignedBigInteger('gross_sales_minor')->default(0);
            $table->unsignedInteger('voided_sales_count')->default(0);
            $table->unsignedInteger('returns_count')->default(0);
            $table->bigInteger('cashier_variance_minor')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'branch_id', 'operational_user_uuid', 'summary_date'], 'staff_activity_scope_user_date_unique');
            $table->index(['business_id', 'branch_id', 'summary_date'], 'staff_activity_scope_date_idx');
            $table->index(['business_id', 'operational_user_uuid'], 'staff_activity_business_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_activity_summaries');

        Schema::table('operational_user_profiles', function (Blueprint $table): void {
            $table->dropIndex('operational_profiles_scope_status_idx');
            $table->dropIndex('operational_profiles_business_role_idx');
            $table->dropColumn([
                'employee_number',
                'username',
                'staff_code',
                'role_code',
                'status',
                'replicated_created_at',
                'replicated_updated_at',
            ]);
        });
    }
};

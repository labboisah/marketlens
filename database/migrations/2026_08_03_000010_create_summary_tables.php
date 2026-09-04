    <?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_service_summaries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->date('date');
            $table->integer('services_rendered')->default(0);
            $table->integer('services_completed')->default(0);
            $table->decimal('service_revenue', 14, 4)->default(0);
            $table->decimal('direct_expenses', 14, 4)->default(0);
            $table->decimal('distributable_amount', 14, 4)->default(0);
            $table->decimal('staff_share', 14, 4)->default(0);
            $table->decimal('business_share', 14, 4)->default(0);
            $table->timestamps();
            $table->unique(['business_id','branch_id','date'], 'uniq_dss_bus_branch_date');
        });

        Schema::create('daily_service_staff_summaries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('staff_id');
            $table->date('date');
            $table->integer('services_assigned')->default(0);
            $table->integer('services_completed')->default(0);
            $table->decimal('staff_earnings', 14, 4)->default(0);
            $table->timestamps();
            $table->unique(['business_id','branch_id','staff_id','date'], 'uniq_dsss_bus_branch_staff_date');
        });

        Schema::create('daily_service_share_summaries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->date('date');
            $table->decimal('revenue', 14, 4)->default(0);
            $table->decimal('distributable', 14, 4)->default(0);
            $table->timestamps();
            $table->unique(['business_id','branch_id','service_id','date'], 'uniq_dssshare_bus_branch_srv_date');
        });

        Schema::create('branch_service_performance_summaries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('service_id');
            $table->date('date');
            $table->integer('rendered')->default(0);
            $table->decimal('revenue', 14, 4)->default(0);
            $table->timestamps();
            $table->unique(['business_id','branch_id','service_id','date'], 'uniq_bsp_bus_branch_srv_date');
        });

        Schema::create('staff_service_performance_summaries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('staff_id');
            $table->date('date');
            $table->integer('assigned')->default(0);
            $table->integer('completed')->default(0);
            $table->decimal('earnings', 14, 4)->default(0);
            $table->timestamps();
            $table->unique(['business_id','branch_id','staff_id','date'], 'uniq_ssp_bus_branch_staff_date');
        });

        Schema::create('profit_period_summaries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('profit_period_id');
            $table->decimal('gross_revenue', 14, 4)->default(0);
            $table->decimal('cogs', 14, 4)->default(0);
            $table->decimal('direct_service_expenses', 14, 4)->default(0);
            $table->decimal('operating_expenses', 14, 4)->default(0);
            $table->decimal('tax_provision', 14, 4)->default(0);
            $table->decimal('net_profit', 14, 4)->default(0);
            $table->decimal('retained_amount', 14, 4)->default(0);
            $table->decimal('distributable_amount', 14, 4)->default(0);
            $table->timestamps();
            $table->unique(['business_id','profit_period_id'], 'uniq_pps_business_profit');
        });

        Schema::create('shareholder_distribution_summaries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('profit_period_id');
            $table->unsignedBigInteger('shareholder_id');
            $table->decimal('entitlement', 14, 4)->default(0);
            $table->decimal('paid', 14, 4)->default(0);
            $table->decimal('outstanding', 14, 4)->default(0);
            $table->timestamps();
            $table->unique(['business_id','profit_period_id','shareholder_id'], 'uniq_sds_business_profit_sh');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shareholder_distribution_summaries');
        Schema::dropIfExists('profit_period_summaries');
        Schema::dropIfExists('staff_service_performance_summaries');
        Schema::dropIfExists('branch_service_performance_summaries');
        Schema::dropIfExists('daily_service_share_summaries');
        Schema::dropIfExists('daily_service_staff_summaries');
        Schema::dropIfExists('daily_service_summaries');
    }
};

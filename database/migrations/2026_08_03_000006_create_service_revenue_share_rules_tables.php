<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_revenue_share_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->boolean('requires_approval')->default(false);
            $table->integer('priority')->default(0);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('service_revenue_share_rule_components', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rule_id');
            $table->string('type');
            $table->decimal('percentage', 8, 4)->nullable();
            $table->decimal('fixed_amount', 14, 4)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->timestamps();
        });

        Schema::create('service_revenue_share_rule_histories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rule_id');
            $table->uuid('changed_by')->nullable();
            $table->text('changes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_revenue_share_rule_histories');
        Schema::dropIfExists('service_revenue_share_rule_components');
        Schema::dropIfExists('service_revenue_share_rules');
    }
};

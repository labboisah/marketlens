<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('service_category_id')->nullable();
            $table->string('code')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('standard_price', 14, 4)->nullable();
            $table->decimal('minimum_price', 14, 4)->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->boolean('requires_staff')->default(true);
            $table->boolean('allow_multiple_staff')->default(false);
            $table->boolean('allow_direct_expenses')->default(false);
            $table->boolean('allow_discounts')->default(true);
            $table->boolean('allow_revenue_sharing')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};

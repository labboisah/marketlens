<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_prices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->decimal('price', 14, 4);
            $table->boolean('is_current')->default(true);
            $table->timestamp('effective_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_prices');
    }
};

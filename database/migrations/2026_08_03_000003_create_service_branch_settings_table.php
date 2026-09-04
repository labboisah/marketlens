<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_branch_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('service_id');
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['branch_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_branch_settings');
    }
};

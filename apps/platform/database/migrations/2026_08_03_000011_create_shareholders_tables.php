<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shareholders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->string('number')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('share_classes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('business_id');
            $table->string('code');
            $table->string('name');
            $table->text('notes')->nullable();
            $table->boolean('voting_rights')->default(true);
            $table->boolean('distribution_rights')->default(true);
            $table->timestamps();
        });

        Schema::create('shareholdings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shareholder_id');
            $table->unsignedBigInteger('share_class_id');
            $table->unsignedBigInteger('business_id');
            $table->decimal('shares', 20, 6)->default(0);
            $table->decimal('percentage', 8, 6)->default(0);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('share_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('shareholder_id');
            $table->string('type');
            $table->decimal('shares', 20, 6)->default(0);
            $table->decimal('price', 14, 4)->nullable();
            $table->text('notes')->nullable();
            $table->date('effective_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shareholder_accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('shareholder_id');
            $table->unsignedBigInteger('business_id');
            $table->decimal('balance', 14, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('shareholder_account_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_id');
            $table->string('type');
            $table->decimal('amount', 14, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shareholder_account_transactions');
        Schema::dropIfExists('shareholder_accounts');
        Schema::dropIfExists('share_transactions');
        Schema::dropIfExists('shareholdings');
        Schema::dropIfExists('share_classes');
        Schema::dropIfExists('shareholders');
    }
};

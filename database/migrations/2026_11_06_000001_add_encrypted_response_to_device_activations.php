<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_activations', function (Blueprint $table): void {
            $table->longText('response_encrypted')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('device_activations', function (Blueprint $table): void {
            $table->dropColumn('response_encrypted');
        });
    }
};

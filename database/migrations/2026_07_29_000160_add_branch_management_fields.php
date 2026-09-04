<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->timestamp('last_synchronized_at')->nullable()->after('is_head_office');
            $table->timestamp('archived_at')->nullable()->after('last_synchronized_at');
            $table->unique(['business_id', 'code'], 'branches_business_code_unique');
            $table->index(['business_id', 'is_head_office'], 'branches_business_head_office_idx');
            $table->index(['business_id', 'last_synchronized_at'], 'branches_business_last_sync_idx');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropUnique('branches_business_code_unique');
            $table->dropIndex('branches_business_head_office_idx');
            $table->dropIndex('branches_business_last_sync_idx');
            $table->dropColumn(['last_synchronized_at', 'archived_at']);
        });
    }
};

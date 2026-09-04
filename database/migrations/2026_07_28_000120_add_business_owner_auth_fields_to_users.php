<?php

use App\Enums\AccountStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone')->nullable()->after('email');
            $table->string('account_status')->default(AccountStatus::PendingVerification->value)->after('password');
            $table->timestamp('terms_accepted_at')->nullable()->after('account_status');
            $table->timestamp('privacy_accepted_at')->nullable()->after('terms_accepted_at');
            $table->index('account_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['account_status']);
            $table->dropColumn(['phone', 'account_status', 'terms_accepted_at', 'privacy_accepted_at']);
        });
    }
};

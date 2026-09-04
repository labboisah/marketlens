<?php

namespace Database\Seeders;

use App\Domain\Legal\Services\LegalDocumentService;
use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('marketlens.super_admin.email');
        $password = config('marketlens.super_admin.password');

        if (! is_string($email) || trim($email) === '') {
            $this->command?->warn('Skipped super-admin seeding: MARKETLENS_SUPER_ADMIN_EMAIL is not configured.');

            return;
        }

        $existingUser = User::query()->where('email', $email)->first();

        if (! $existingUser && (! is_string($password) || $password === '')) {
            $this->command?->warn('Skipped super-admin seeding: MARKETLENS_SUPER_ADMIN_PASSWORD is required when creating the account.');

            return;
        }

        $attributes = [
            'name' => config('marketlens.super_admin.name', 'MarketLens Super Admin'),
            'phone' => config('marketlens.super_admin.phone'),
            'email_verified_at' => $existingUser?->email_verified_at ?? now(),
            'account_status' => AccountStatus::Active,
            'is_super_admin' => true,
            'super_admin_confirmed_at' => $existingUser?->super_admin_confirmed_at ?? now(),
            'terms_accepted_at' => $existingUser?->terms_accepted_at ?? now(),
            'privacy_accepted_at' => $existingUser?->privacy_accepted_at ?? now(),
        ];

        if (is_string($password) && $password !== '') {
            $attributes['password'] = Hash::make($password);
        }

        $user = User::query()->updateOrCreate(['email' => $email], $attributes);

        $legal = app(LegalDocumentService::class);
        $legal->acceptVersions($user, $legal->requiredVersionsFor($user)->pluck('id')->all());

        $this->command?->info('Super-admin account seeded for '.$email.'.');
    }
}

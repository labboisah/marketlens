<?php

use App\Domain\Legal\Models\LegalAcceptance;
use App\Domain\Legal\Services\LegalDocumentService;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('does not create a super admin without configured creation credentials', function (): void {
    config([
        'marketlens.super_admin.email' => 'root@example.test',
        'marketlens.super_admin.password' => null,
    ]);

    $this->seed(SuperAdminSeeder::class);

    expect(User::query()->where('email', 'root@example.test')->exists())->toBeFalse();
});

it('creates and updates one active super admin from configuration', function (): void {
    config([
        'marketlens.super_admin.name' => 'Platform Root',
        'marketlens.super_admin.email' => 'root@example.test',
        'marketlens.super_admin.phone' => '+2349000000000',
        'marketlens.super_admin.password' => 'SecureRootPass1!',
    ]);

    $this->seed(SuperAdminSeeder::class);
    $this->seed(SuperAdminSeeder::class);

    $user = User::query()->where('email', 'root@example.test')->firstOrFail();
    $requiredVersions = app(LegalDocumentService::class)->requiredVersionsFor($user);

    expect(User::query()->where('email', 'root@example.test')->count())->toBe(1)
        ->and($user->name)->toBe('Platform Root')
        ->and($user->isSuperAdmin())->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->super_admin_confirmed_at)->not->toBeNull()
        ->and(Hash::check('SecureRootPass1!', $user->password))->toBeTrue()
        ->and($requiredVersions)->toHaveCount(0)
        ->and(LegalAcceptance::query()->where('user_id', $user->id)->exists())->toBeTrue();
});

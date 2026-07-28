<?php

use App\Domain\Audit\Models\AuditLog;
use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('shows business owner authentication pages', function (): void {
    $this->get('/login')->assertOk()->assertSee('Login')->assertSee('images/logo.png');
    $this->get('/register')->assertOk()->assertSee('Create your MarketLens account')->assertSee('images/logo.png');
    $this->get('/forgot-password')->assertOk()->assertSee('Reset your password');
    $this->get('/account-disabled')->assertOk()->assertSee('Account access is unavailable');
});

it('registers a pending verification business owner and sends verification email', function (): void {
    Notification::fake();

    $response = $this->post('/register', [
        'name' => 'Ada Market',
        'email' => 'ada@example.test',
        'phone' => '+234 800 000 0000',
        'password' => 'SecurePass1!',
        'password_confirmation' => 'SecurePass1!',
        'terms' => '1',
        'privacy' => '1',
    ]);

    $user = User::query()->where('email', 'ada@example.test')->firstOrFail();

    $response->assertRedirect(route('verification.notice'));
    $this->assertAuthenticatedAs($user);
    expect($user->account_status)->toBe(AccountStatus::PendingVerification)
        ->and($user->terms_accepted_at)->not->toBeNull()
        ->and($user->privacy_accepted_at)->not->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
    expect(AuditLog::query()->where('action', 'platform_user.registered')->exists())->toBeTrue();
});

it('requires terms privacy acceptance and a strong confirmed password during registration', function (): void {
    $this->post('/register', [
        'name' => 'Ada Market',
        'email' => 'ada@example.test',
        'phone' => '+234 800 000 0000',
        'password' => 'weak',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors(['password', 'terms', 'privacy']);
});

it('logs in active verified platform users with remember me and regenerates access', function (): void {
    $user = User::factory()->create([
        'email' => 'owner@example.test',
        'account_status' => AccountStatus::Active,
    ]);

    $response = $this->post('/login', [
        'email' => 'owner@example.test',
        'password' => 'password',
        'remember' => '1',
    ]);

    $response->assertRedirect(route('onboarding.wizard'));
    $this->assertAuthenticatedAs($user);
    expect(AuditLog::query()->where('action', 'platform_user.logged_in')->exists())->toBeTrue();
});

it('uses generic login failure messages', function (): void {
    User::factory()->create(['email' => 'owner@example.test']);

    $this->post('/login', [
        'email' => 'owner@example.test',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
});

it('prevents suspended users from business records', function (): void {
    $user = User::factory()->create([
        'account_status' => AccountStatus::Suspended,
    ]);

    $this->actingAs($user)
        ->get(route('platform.dashboard'))
        ->assertRedirect(route('account.disabled'));
});

it('activates pending users when they verify email', function (): void {
    $user = User::factory()->unverified()->create([
        'account_status' => AccountStatus::PendingVerification,
    ]);

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(30),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
    );

    $this->actingAs($user)->get($url)->assertRedirect(route('onboarding.wizard'));

    $user->refresh();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->account_status)->toBe(AccountStatus::Active)
        ->and(AuditLog::query()->where('action', 'platform_user.email_verified')->exists())->toBeTrue();
});

it('sends rate limited password reset links and resets passwords', function (): void {
    Notification::fake();

    $user = User::factory()->create(['email' => 'owner@example.test']);

    $this->post('/forgot-password', ['email' => 'owner@example.test'])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($user, ResetPassword::class);

    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'owner@example.test',
        'password' => 'NewSecurePass1!',
        'password_confirmation' => 'NewSecurePass1!',
    ])->assertRedirect(route('login'));

    expect(Hash::check('NewSecurePass1!', $user->refresh()->password))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'platform_user.password_reset')->exists())->toBeTrue();
});

it('issues sanctum tokens for verified active monitoring users', function (): void {
    $user = User::factory()->create([
        'email' => 'monitor@example.test',
        'account_status' => AccountStatus::Active,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'monitor@example.test',
        'password' => 'password',
        'device_name' => 'Owner Android',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonStructure(['data' => ['token']]);
});

it('does not issue monitoring tokens for unverified users', function (): void {
    User::factory()->unverified()->create([
        'email' => 'pending@example.test',
        'account_status' => AccountStatus::PendingVerification,
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'pending@example.test',
        'password' => 'password',
        'device_name' => 'Owner Android',
    ])
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');
});

it('blocks suspended token users from monitoring business records', function (): void {
    $user = User::factory()->create([
        'account_status' => AccountStatus::Suspended,
    ]);

    $token = $user->createToken('Owner Android', ['monitoring:read'])->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/monitoring/dashboard')
        ->assertForbidden()
        ->assertJsonPath('code', 'ACCOUNT_INACTIVE');
});

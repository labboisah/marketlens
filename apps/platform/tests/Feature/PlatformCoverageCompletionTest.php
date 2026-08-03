<?php

use App\Domain\Legal\Services\LegalDocumentService;
use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

function coverageAcceptedUser(array $attributes = []): User
{
    $user = User::factory()->create(array_merge([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
        'terms_accepted_at' => now(),
        'privacy_accepted_at' => now(),
    ], $attributes));

    $legal = app(LegalDocumentService::class);
    $legal->acceptVersions($user, $legal->requiredVersionsFor($user)->pluck('id')->all());

    return $user;
}

it('keeps every public marketing and legal route reachable without admin data leakage', function (): void {
    app(LegalDocumentService::class)->syncConfiguredDocuments();

    $routes = [
        route('welcome') => ['MarketLens', 'See Every Sale. Control Every Stock.'],
        route('public.product') => ['Product', 'Shop App', 'Monitoring App'],
        route('public.features') => ['Features', 'Business problem', 'MarketLens solution'],
        route('public.pricing') => ['Pricing', 'Hosting and Platform access are included'],
        route('public.business-types') => ['Business types', 'Common operational challenges'],
        route('legal.index') => ['Legal centre', 'Terms of Service', 'Privacy Policy'],
        route('legal.show', ['slug' => 'terms-of-service']) => ['Terms of Service'],
        route('legal.show', ['slug' => 'privacy-policy']) => ['Privacy Policy'],
    ];

    foreach ($routes as $uri => $expectedCopy) {
        $response = $this->get($uri)->assertOk();

        foreach ($expectedCopy as $copy) {
            $response->assertSee($copy);
        }

        $response
            ->assertDontSee('Platform administration')
            ->assertDontSee('Tenant Context')
            ->assertDontSee('Signed-in admin');
    }
});

it('rate limits repeated platform login attempts', function (): void {
    User::factory()->create(['email' => 'rate-limit-owner@example.test']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login'), [
            'email' => 'rate-limit-owner@example.test',
            'password' => 'incorrect-password',
        ])->assertSessionHasErrors('email');
    }

    $this->post(route('login'), [
        'email' => 'rate-limit-owner@example.test',
        'password' => 'incorrect-password',
    ])->assertTooManyRequests();
});

it('applies api rate limiting without exposing sensitive headers', function (): void {
    RateLimiter::for('api', fn () => Limit::perMinute(2)->by('coverage-api-rate-limit'));

    $this->getJson(route('api.v1.device.versions'))->assertOk();
    $this->getJson(route('api.v1.device.versions'))->assertOk();

    $this
        ->withHeader('Authorization', 'Bearer should-not-leak')
        ->getJson(route('api.v1.device.versions'))
        ->assertTooManyRequests()
        ->assertHeader('X-Trace-Id')
        ->assertJsonMissing(['Authorization' => 'Bearer should-not-leak'])
        ->assertJsonMissing(['authorization' => 'Bearer should-not-leak']);
});

it('keeps system health behind the super-admin boundary', function (): void {
    $owner = coverageAcceptedUser();
    $superAdmin = coverageAcceptedUser(['is_super_admin' => true]);

    $this
        ->actingAs($owner)
        ->get(route('super-admin.system-health'))
        ->assertForbidden();

    $this
        ->actingAs($superAdmin)
        ->get(route('super-admin.system-health'))
        ->assertOk()
        ->assertSee('System Health')
        ->assertSee('Platform observability')
        ->assertDontSee('DB_PASSWORD')
        ->assertDontSee('Authorization')
        ->assertDontSee('device_credential');
});

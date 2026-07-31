<?php

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Legal\Models\LegalAcceptance;
use App\Domain\Legal\Models\LegalDocument;
use App\Domain\Legal\Models\LegalDocumentVersion;
use App\Domain\Legal\Services\LegalDocumentService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\AccountStatus;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\SubscriptionStatus;
use App\Livewire\Legal\LegalAcceptancePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function legalFixture(): array
{
    app(LegalDocumentService::class)->syncConfiguredDocuments();

    $user = User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
    ]);

    $plan = Plan::query()->create([
        'name' => 'Legal Plan',
        'slug' => 'legal-plan-'.Str::random(6),
        'billing_interval' => 'monthly',
        'price_minor' => 10000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $plan->features()->create(['name' => 'Remote dashboard', 'sort_order' => 1]);

    $business = Business::query()->create([
        'name' => 'Legal Stores',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
    ]);
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Main Branch',
        'status' => BranchStatus::Active,
    ]);
    $user->ownedBusinesses()->attach($business->id, ['role' => 'owner']);

    Subscription::query()->create([
        'business_id' => $business->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    return [$user, $business, $branch];
}

function materialLegalVersion(string $slug = 'terms-of-service', string $version = '2099.01.01'): LegalDocumentVersion
{
    app(LegalDocumentService::class)->syncConfiguredDocuments();
    $document = LegalDocument::query()->where('slug', $slug)->firstOrFail();

    return LegalDocumentVersion::query()->create([
        'legal_document_id' => $document->id,
        'version' => $version,
        'summary' => 'A material legal update requires acceptance.',
        'body' => '# Updated legal document'.PHP_EOL.PHP_EOL.'This material update is effective now.',
        'published_at' => now()->subDays(2),
        'effective_at' => now()->subDay(),
        'is_published' => true,
        'is_material' => true,
        'metadata' => ['test' => true],
    ]);
}

it('renders public legal index and document pages from configured published versions', function (): void {
    app(LegalDocumentService::class)->syncConfiguredDocuments();

    $this->get(route('legal.index'))
        ->assertOk()
        ->assertSee('MarketLens legal documents')
        ->assertSee('Terms of Service')
        ->assertSee('Privacy Policy')
        ->assertSee('Data Processing Information')
        ->assertSee('Acceptable Use Policy')
        ->assertSee('Subscription and Billing Policy')
        ->assertSee('Support Policy')
        ->assertSee('Data Retention Policy')
        ->assertSee('Security Overview')
        ->assertSee('v2026.07.31');

    $this->get(route('legal.show', 'privacy-policy'))
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('Version')
        ->assertSee('2026.07.31')
        ->assertSee('acceptance timestamps');

    $this->get('/terms')->assertRedirect('/legal/terms-of-service');
    $this->get('/privacy')->assertRedirect('/legal/privacy-policy');
});

it('syncs configurable legal document versions into durable records', function (): void {
    app(LegalDocumentService::class)->syncConfiguredDocuments();

    expect(LegalDocument::query()->count())->toBe(8)
        ->and(LegalDocumentVersion::query()->where('version', '2026.07.31')->count())->toBe(8)
        ->and(LegalDocumentVersion::query()->where('is_material', true)->count())->toBe(6)
        ->and(LegalDocument::query()->where('slug', 'support-policy')->first()?->publishedVersion?->is_material)->toBeFalse();
});

it('requires acceptance when an effective material legal version changes and records metadata', function (): void {
    [$user, $business, $branch] = legalFixture();
    $version = materialLegalVersion();

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.dashboard'))
        ->assertRedirect(route('legal.acceptance'));

    $this->actingAs($user)
        ->withHeader('User-Agent', 'MarketLens Test Browser')
        ->from(route('platform.dashboard'));

    Livewire::actingAs($user)
        ->test(LegalAcceptancePage::class)
        ->assertSee('Legal acceptance required')
        ->assertSee('Terms of Service')
        ->set('acceptedVersions.'.$version->id, true)
        ->call('accept')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $acceptance = LegalAcceptance::query()
        ->where('user_id', $user->id)
        ->where('legal_document_version_id', $version->id)
        ->firstOrFail();

    expect($acceptance->accepted_at)->not->toBeNull()
        ->and($acceptance->ip_address)->not->toBeNull()
        ->and($acceptance->user_agent)->not->toBeNull();

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.dashboard'))
        ->assertOk();
});

it('does not require acceptance for non material legal updates', function (): void {
    [$user, $business, $branch] = legalFixture();
    $document = LegalDocument::query()->where('slug', 'support-policy')->firstOrFail();

    LegalDocumentVersion::query()->create([
        'legal_document_id' => $document->id,
        'version' => '2099.02.01',
        'summary' => 'A non-material support wording update.',
        'body' => '# Support Policy'.PHP_EOL.PHP_EOL.'Clarified support response language.',
        'published_at' => now()->subDays(2),
        'effective_at' => now()->subDay(),
        'is_published' => true,
        'is_material' => false,
    ]);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.dashboard'))
        ->assertOk();
});

it('requires every pending material version before recording acceptance', function (): void {
    [$user] = legalFixture();
    materialLegalVersion('terms-of-service', '2099.03.01');
    materialLegalVersion('privacy-policy', '2099.03.01');

    Livewire::actingAs($user)
        ->test(LegalAcceptancePage::class)
        ->call('accept')
        ->assertHasErrors(['acceptedVersions']);

    expect(LegalAcceptance::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

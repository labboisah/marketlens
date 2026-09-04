<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Devices\Models\Device;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\SupportTicketAttachment;
use App\Domain\Support\Models\SupportTicketCategory;
use App\Domain\Support\Services\SupportCentreService;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Enums\AccountStatus;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\DeviceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\SupportTicketStatus;
use App\Enums\SyncBatchStatus;
use App\Livewire\Platform\Support\SupportCentrePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function supportCentreFixture(): array
{
    app(SupportCentreService::class)->ensureDefaults();

    $user = User::factory()->create([
        'account_status' => AccountStatus::Active,
        'email_verified_at' => now(),
    ]);
    $plan = Plan::query()->create([
        'name' => 'Support Plan',
        'slug' => 'support-plan-'.Str::random(6),
        'billing_interval' => 'monthly',
        'price_minor' => 12000,
        'currency' => 'NGN',
        'trial_days' => 14,
        'sort_order' => 1,
        'is_active' => true,
    ]);
    foreach (['Sales', 'Inventory', 'Reports', 'Security alerts', 'Remote dashboard', 'More Client App devices'] as $index => $feature) {
        $plan->features()->create(['name' => $feature, 'sort_order' => $index + 1]);
    }

    $business = Business::query()->create([
        'name' => 'Support Stores',
        'status' => BusinessStatus::Active,
        'onboarding_status' => 'completed',
    ]);
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Main Branch',
        'code' => 'MAIN',
        'status' => BranchStatus::Active,
        'is_head_office' => true,
    ]);
    $user->ownedBusinesses()->attach($business->id, ['role' => 'owner']);

    Subscription::query()->create([
        'business_id' => $business->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
        'ends_at' => now()->addMonth(),
    ]);

    $device = Device::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'name' => 'Main Business Server',
        'type' => 'shop_app',
        'status' => DeviceStatus::Active,
        'last_seen_at' => now(),
    ]);

    $batch = SyncBatch::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'device_id' => $device->id,
        'protocol_version' => '1.0',
        'status' => SyncBatchStatus::Failed,
        'records_count' => 3,
        'trace_id' => (string) Str::uuid(),
        'received_at' => now()->subMinutes(5),
    ]);

    return [$user, $business, $branch, $device, $batch];
}

it('renders authenticated support centre with knowledge base faq ticket form and navigation links', function (): void {
    [$user, $business, $branch] = supportCentreFixture();

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.support'))
        ->assertOk()
        ->assertSee('Support and help centre')
        ->assertSee('Knowledge base')
        ->assertSee('Frequently asked questions')
        ->assertSee('Submit support ticket')
        ->assertSee('Business Server device activation works');

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee(route('platform.support'));
});

it('provides contextual support links from device synchronization and subscription pages', function (): void {
    [$user, $business, $branch] = supportCentreFixture();

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.devices'))
        ->assertOk()
        ->assertSee('Get device support')
        ->assertSee('category=device', false);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.synchronization'))
        ->assertOk()
        ->assertSee('Get sync support')
        ->assertSee('category=synchronization', false);

    $this->actingAs($user)
        ->withSession(['platform.business_id' => $business->id, 'platform.branch_id' => $branch->id])
        ->get(route('platform.subscription'))
        ->assertOk()
        ->assertSee('Billing support')
        ->assertSee('category=billing', false);
});

it('creates support tickets with related records private attachments messages and audit logs', function (): void {
    Storage::fake('local');
    [$user, $business, $branch, $device, $batch] = supportCentreFixture();
    $category = SupportTicketCategory::query()->where('slug', 'synchronization')->firstOrFail();

    Livewire::actingAs($user)
        ->test(SupportCentrePage::class)
        ->set('ticketCategoryId', $category->id)
        ->set('priority', 'high')
        ->set('subject', 'Failed synchronization batch')
        ->set('description', 'The latest synchronization batch failed validation and needs review.')
        ->set('relatedBranchId', (string) $branch->id)
        ->set('relatedDeviceId', (string) $device->id)
        ->set('relatedSyncBatchId', (string) $batch->id)
        ->set('attachments', [UploadedFile::fake()->create('sync-report.pdf', 120, 'application/pdf')])
        ->call('submitTicket')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $ticket = SupportTicket::query()->with(['messages', 'attachments'])->where('business_id', $business->id)->firstOrFail();
    $attachment = SupportTicketAttachment::query()->where('support_ticket_id', $ticket->id)->firstOrFail();

    expect($ticket->support_ticket_category_id)->toBe($category->id)
        ->and($ticket->branch_id)->toBe($branch->id)
        ->and($ticket->device_id)->toBe($device->id)
        ->and($ticket->sync_batch_id)->toBe($batch->id)
        ->and($ticket->priority)->toBe('high')
        ->and($ticket->messages)->toHaveCount(1)
        ->and($attachment->disk)->toBe('local')
        ->and($attachment->original_name)->toBe('sync-report.pdf')
        ->and($attachment->checksum)->not->toBeNull()
        ->and(AuditLog::query()->where('business_id', $business->id)->where('action', 'support.ticket_created')->exists())->toBeTrue();

    Storage::disk('local')->assertExists($attachment->path);
});

it('rejects unsafe attachments and cross tenant related records', function (): void {
    [$user, $business, $branch] = supportCentreFixture();
    $category = SupportTicketCategory::query()->where('slug', 'device')->firstOrFail();

    $otherBusiness = Business::query()->create(['name' => 'Hidden Support Store', 'status' => BusinessStatus::Active, 'onboarding_status' => 'completed']);
    $otherBranch = Branch::query()->create(['business_id' => $otherBusiness->id, 'name' => 'Hidden Branch', 'status' => BranchStatus::Active]);
    $otherDevice = Device::query()->create([
        'business_id' => $otherBusiness->id,
        'branch_id' => $otherBranch->id,
        'name' => 'Hidden Business Server',
        'type' => 'shop_app',
        'status' => DeviceStatus::Active,
    ]);

    Livewire::actingAs($user)
        ->test(SupportCentrePage::class)
        ->set('ticketCategoryId', $category->id)
        ->set('priority', 'normal')
        ->set('subject', 'Blocked upload')
        ->set('description', 'This attachment should not pass secure validation.')
        ->set('attachments', [UploadedFile::fake()->create('payload.exe', 10, 'application/x-msdownload')])
        ->call('submitTicket')
        ->assertHasErrors(['attachments.0' => 'mimes']);

    Livewire::actingAs($user)
        ->test(SupportCentrePage::class)
        ->set('ticketCategoryId', $category->id)
        ->set('priority', 'normal')
        ->set('subject', 'Cross tenant device')
        ->set('description', 'This ticket references a device from another tenant.')
        ->set('relatedDeviceId', (string) $otherDevice->id)
        ->call('submitTicket')
        ->assertHasErrors(['relatedDeviceId']);

    expect(SupportTicket::query()->where('business_id', $business->id)->exists())->toBeFalse();
});

it('supports ticket conversation replies status changes and tenant isolation', function (): void {
    Storage::fake('local');
    [$user, $business, $branch] = supportCentreFixture();
    $category = SupportTicketCategory::query()->where('slug', 'general')->firstOrFail();
    $ticket = app(SupportCentreService::class)->createTicket($business, $user, [
        'support_ticket_category_id' => $category->id,
        'subject' => 'Need account help',
        'priority' => 'normal',
        'description' => 'Please review this account support request.',
    ]);

    Livewire::actingAs($user)
        ->test(SupportCentrePage::class)
        ->call('selectTicket', $ticket->id)
        ->set('replyBody', 'Adding more context for the support team.')
        ->set('replyAttachments', [UploadedFile::fake()->create('context.txt', 2, 'text/plain')])
        ->call('reply')
        ->assertHasNoErrors()
        ->assertDispatched('notify')
        ->call('updateTicketStatus', $ticket->id, SupportTicketStatus::Resolved->value)
        ->assertDispatched('notify');

    expect($ticket->refresh()->status)->toBe(SupportTicketStatus::Resolved)
        ->and($ticket->closed_at)->not->toBeNull()
        ->and($ticket->messages()->count())->toBe(2)
        ->and($ticket->attachments()->count())->toBe(1)
        ->and(AuditLog::query()->where('business_id', $business->id)->where('action', 'support.ticket_replied')->exists())->toBeTrue();

    $otherOwner = User::factory()->create(['account_status' => AccountStatus::Active, 'email_verified_at' => now()]);
    $otherBusiness = Business::query()->create(['name' => 'Other Support Store', 'status' => BusinessStatus::Active, 'onboarding_status' => 'completed']);
    $otherOwner->ownedBusinesses()->attach($otherBusiness->id, ['role' => 'owner']);

    Livewire::actingAs($otherOwner)
        ->test(SupportCentrePage::class)
        ->assertDontSee('Need account help');
});

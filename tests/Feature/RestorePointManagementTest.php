<?php

use App\Domain\Access\Models\BusinessMonitoringUser;
use App\Domain\Access\Models\MonitoringRole;
use App\Domain\Backups\Models\RestorePoint;
use App\Domain\Backups\Models\RestorePointRequest;
use App\Domain\Backups\Services\RestorePointService;
use App\Domain\Devices\Models\Device;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BusinessContext;
use App\Enums\BranchStatus;
use App\Enums\BusinessStatus;
use App\Enums\DeviceStatus;
use App\Http\Middleware\EnsureLegalDocumentsAreAccepted;
use App\Livewire\Platform\Backups\RestorePointManagementPage;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);
function restoreFixture(): array
{
    app(BusinessContext::class)->clear();
    $user = User::factory()->create();
    $business = Business::query()->create(['name' => 'Backup business', 'status' => BusinessStatus::Active]);
    $user->ownedBusinesses()->attach($business->id, ['role' => 'owner']);
    $branch = Branch::query()->create(['business_id' => $business->id, 'name' => 'Branch', 'status' => BranchStatus::Active, 'is_head_office' => true]);
    $token = Str::random(48);
    $device = Device::query()->create(['business_id' => $business->id, 'branch_id' => $branch->id, 'name' => 'Backup server', 'type' => 'shop_app', 'status' => DeviceStatus::Active, 'credential_hash' => hash_hmac('sha256', $token, (string) config('app.key'))]);

    return [$user, $business, $branch, $device, $token];
}
function restoreUpload(Device $device, array $override = []): array
{
    $content = 'MLBACK01'.random_bytes(100);
    $metadata = array_merge(['uuid' => (string) Str::uuid(), 'name' => 'Before closing', 'applicationVersion' => '1.0.2', 'schemaVersion' => 3, 'capturedAt' => now()->toISOString(), 'checksum' => str_repeat('a', 64), 'encryptedChecksum' => hash('sha256', $content), 'businessUuid' => $device->business->uuid, 'branchUuid' => $device->branch->uuid, 'deviceUuid' => $device->uuid], $override);

    return ['metadata' => json_encode($metadata), 'file' => UploadedFile::fake()->createWithContent('backup.mlbackup', $content)];
}
beforeEach(function () {
    Storage::fake('local');
});
it('stores encrypted copies idempotently and lists only the authenticated server points', function () {
    [$user,$business,$branch,$device,$token] = restoreFixture();
    $upload = restoreUpload($device);
    $uuid = json_decode($upload['metadata'], true)['uuid'];
    $this->withToken($token)->postJson('/api/v1/device/restore-points', $upload)->assertOk()->assertJsonPath('data.uuid', $uuid);
    $this->withToken($token)->postJson('/api/v1/device/restore-points', $upload)->assertOk();
    expect(RestorePoint::count())->toBe(1);
    $this->withToken($token)->getJson('/api/v1/device/restore-points')->assertOk()->assertJsonCount(1, 'data.points');
    $this->withToken($token)->get('/api/v1/device/restore-points/'.$uuid.'/download')->assertOk();
    [,,$otherBranch,$otherDevice,$otherToken] = restoreFixture();
    $this->withToken($otherToken)->getJson('/api/v1/device/restore-points')->assertOk()->assertJsonCount(0, 'data.points');
    $this->withToken($otherToken)->getJson('/api/v1/device/restore-points/'.$uuid.'/download')->assertNotFound();
    $this->withToken($otherToken)->deleteJson('/api/v1/device/restore-points/'.$uuid)->assertNotFound();
});
it('rejects wrong branch metadata, corrupt envelopes, invalid credentials and revoked devices', function () {
    [,,,$device,$token] = restoreFixture();
    $this->withToken($token)->postJson('/api/v1/device/restore-points', restoreUpload($device, ['branchUuid' => (string) Str::uuid()]))->assertForbidden();
    $this->withToken($token)->postJson('/api/v1/device/restore-points', restoreUpload($device, ['encryptedChecksum' => str_repeat('0', 64)]))->assertUnprocessable();
    $this->withToken('bad-token')->getJson('/api/v1/device/restore-points')->assertUnprocessable();
    $device->update(['revoked_at' => now()]);
    $this->withToken($token)->getJson('/api/v1/device/restore-points')->assertUnprocessable();
    expect(RestorePoint::count())->toBe(0);
});
it('does not allow deleted restore points to be reuploaded by an offline retry', function () {
    [,,,$device,$token] = restoreFixture();
    $upload = restoreUpload($device);
    $uuid = json_decode($upload['metadata'], true)['uuid'];
    $this->withToken($token)->postJson('/api/v1/device/restore-points', $upload)->assertOk();
    $this->withToken($token)->deleteJson('/api/v1/device/restore-points/'.$uuid)->assertOk();
    $this->withToken($token)->postJson('/api/v1/device/restore-points', $upload)->assertStatus(410);
});
it('requires local processing before completion and prevents deleting a requested point', function () {
    [$user,$business,,$device,$token] = restoreFixture();
    $this->withToken($token)->postJson('/api/v1/device/restore-points', restoreUpload($device))->assertOk();
    $point = RestorePoint::firstOrFail();
    app(BusinessContext::class)->set($business->id);
    $job = app(RestorePointService::class)->request($user, $device, 'restore', $point->name, $point);
    $this->withToken($token)->patchJson('/api/v1/device/restore-points/requests/'.$job->uuid, ['status' => 'completed'])->assertStatus(409);
    $this->withToken($token)->deleteJson('/api/v1/device/restore-points/'.$point->uuid)->assertStatus(409);
    $this->withToken($token)->patchJson('/api/v1/device/restore-points/requests/'.$job->uuid, ['status' => 'processing'])->assertOk();
    $this->withToken($token)->patchJson('/api/v1/device/restore-points/requests/'.$job->uuid, ['status' => 'completed'])->assertOk();
    $this->withToken($token)->patchJson('/api/v1/device/restore-points/requests/'.$job->uuid, ['status' => 'completed'])->assertOk();
    $this->withToken($token)->patchJson('/api/v1/device/restore-points/requests/'.$job->uuid, ['status' => 'processing'])->assertStatus(409);
});
it('queues from the UI and cancels requests before local processing', function () {
    [$user,$business,,$device,$token] = restoreFixture();
    app(BusinessContext::class)->set($business->id);
    $page = Livewire::actingAs($user)->test(RestorePointManagementPage::class)->set('deviceId', $device->id)->set('name', 'Before inventory')->call('createPoint')->assertHasNoErrors()->assertSee('Backup requested');
    $job = RestorePointRequest::firstOrFail();
    expect($job->status)->toBe('pending');
    $page->call('cancelRequest', $job->uuid)->assertHasNoErrors();
    expect($job->fresh()->status)->toBe('cancelled');
    $this->withToken($token)->patchJson('/api/v1/device/restore-points/requests/'.$job->uuid, ['status' => 'processing'])->assertStatus(409);
});
it('denies unrelated users and foreign server actions', function () {
    [$owner,$business,,$device] = restoreFixture();
    app(BusinessContext::class)->set($business->id);
    $other = User::factory()->create();
    try {
        app(RestorePointService::class)->request($other, $device, 'create', 'Unauthorized');
        $this->fail('Expected denial');
    } catch (HttpException $error) {
        expect($error->getStatusCode())->toBe(403);
    }
    Livewire::actingAs($other)->test(RestorePointManagementPage::class)->set('deviceId', $device->id)->assertForbidden();
});
it('requires typed UI confirmation before requesting a restore', function () {
    [$user,$business,,$device,$token] = restoreFixture();
    $this->withToken($token)->postJson('/api/v1/device/restore-points', restoreUpload($device))->assertOk();
    $point = RestorePoint::firstOrFail();
    app(BusinessContext::class)->set($business->id);
    $page = Livewire::actingAs($user)->test(RestorePointManagementPage::class)->set('deviceId', $device->id)->call('selectPoint', $point->uuid, 'restore')->call('confirmPoint')->assertHasErrors('confirmation');
    expect(RestorePointRequest::count())->toBe(0);
    $page->set('confirmation', 'RESTORE')->call('confirmPoint')->assertHasNoErrors();
    expect(RestorePointRequest::first()->status)->toBe('pending');
});

it('enforces backup permissions within assigned branches of the same business', function (): void {
    [$owner, $business, $branch, $device] = restoreFixture();
    $otherBranch = Branch::query()->create(['business_id' => $business->id, 'name' => 'Restricted branch', 'status' => BranchStatus::Active]);
    $otherDevice = Device::query()->create(['business_id' => $business->id, 'branch_id' => $otherBranch->id, 'name' => 'Restricted server', 'type' => 'shop_app', 'status' => DeviceStatus::Active]);
    $delegate = User::factory()->create();
    $role = MonitoringRole::query()->create(['name' => 'Backup delegate', 'slug' => 'backup-delegate']);
    $assignment = BusinessMonitoringUser::query()->create(['monitoring_role_id' => $role->id, 'user_id' => $delegate->id, 'business_id' => $business->id, 'status' => 'active', 'module_permissions' => ['backups.view', 'backups.create', 'backups.restore', 'backups.delete']]);
    $assignment->branches()->attach($branch->id);
    app(BusinessContext::class)->set($business->id);
    $service = app(RestorePointService::class);
    foreach (['view', 'create', 'restore', 'delete'] as $permission) {
        $service->authorize($delegate, $device, $permission);
        try {
            $service->authorize($delegate, $otherDevice, $permission);
            $this->fail('Foreign branch allowed');
        } catch (HttpException $error) {
            expect($error->getStatusCode())->toBe(403);
        }
        $service->authorize($owner, $otherDevice, $permission);
    }
    Livewire::actingAs($delegate)->test(RestorePointManagementPage::class)->assertDontSee('Restricted server');
    expect(fn () => Livewire::actingAs($delegate)->test(RestorePointManagementPage::class)->set('deviceId', $otherDevice->id))->toThrow(ModelNotFoundException::class);
});

it('allows cancellation of a stuck create request but not an executing restore', function (): void {
    [$owner, $business, , $device] = restoreFixture();
    app(BusinessContext::class)->set($business->id);
    $job = app(RestorePointService::class)->request($owner, $device, 'create', 'Stuck upload');
    $job->update(['status' => 'processing']);
    Livewire::actingAs($owner)->test(RestorePointManagementPage::class)->set('deviceId', $device->id)->call('cancelRequest', $job->uuid)->assertHasNoErrors();
    expect($job->fresh()->status)->toBe('cancelled');
    $job->update(['kind' => 'restore', 'status' => 'processing']);
    Livewire::actingAs($owner)->test(RestorePointManagementPage::class)->set('deviceId', $device->id)->call('cancelRequest', $job->uuid)->assertStatus(409);
});

it('rotates a recovered server credential and invalidates the original machine token', function (): void {
    [,,, $device, $oldToken] = restoreFixture();
    $next = bin2hex(random_bytes(48));
    $this->withToken($oldToken)->postJson('/api/v1/device/restore-points/rotate-credential', ['credential' => $next])->assertOk();
    $this->withToken($oldToken)->getJson('/api/v1/device/restore-points')->assertUnprocessable();
    $this->withToken($next)->getJson('/api/v1/device/restore-points')->assertOk();
    $this->withToken($next)->postJson('/api/v1/device/restore-points/rotate-credential', ['credential' => $next])->assertOk();
});

it('streams recovery copies only to authorized owners in the selected business', function (): void {
    [$owner, $business, , $device, $token] = restoreFixture();
    $this->withToken($token)->postJson('/api/v1/device/restore-points', restoreUpload($device))->assertOk();
    $point = RestorePoint::firstOrFail();
    $this->withoutMiddleware(EnsureLegalDocumentsAreAccepted::class);
    $this->actingAs($owner)->withSession(['platform.business_id' => $business->id])->get(route('platform.restore-points.download', $point->uuid))->assertOk()->assertDownload($point->uuid.'.mlbackup');
    $this->actingAs(User::factory()->create())->get(route('platform.restore-points.download', $point->uuid))->assertForbidden();
});

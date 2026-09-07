<?php

namespace App\Domain\Backups\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Backups\Models\RestorePoint;
use App\Domain\Backups\Models\RestorePointRequest;
use App\Domain\Devices\Models\Device;
use App\Domain\Tenancy\Services\BusinessContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RestorePointService
{
    public function authorize(User $user, Device $device, string $permission): void
    {
        abort_unless($user->isSuperAdmin() || (app(BusinessContext::class)->id() === $device->business_id && $user->hasBusinessPermission($device->business_id, 'backups.'.$permission) && $user->canAccessBranch($device->business_id, $device->branch_id)), 403);
    }

    public function request(User $user, Device $device, string $kind, string $name, ?RestorePoint $point = null): RestorePointRequest
    {
        abort_unless(in_array($kind, ['create', 'restore']) && trim($name) !== '' && mb_strlen($name) <= 120, 422);
        $this->authorize($user, $device, $kind === 'create' ? 'create' : 'restore');
        abort_unless($device->type === 'shop_app' && ! $device->revoked_at && $device->status->value === 'active', 422, 'An active Business Server is required.');
        if ($kind === 'restore') {
            abort_unless($point && ! $point->trashed() && $point->device_id === $device->id && $point->business_id === $device->business_id && $point->branch_id === $device->branch_id, 422);
        }

        return DB::transaction(function () use ($user, $device, $kind, $name, $point) {
            Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();
            if ($point) {
                abort_unless(RestorePoint::query()->whereKey($point->id)->exists(), 409, 'Restore point was deleted.');
            }
            abort_if(RestorePointRequest::query()->where('device_id', $device->id)->whereIn('status', ['pending', 'processing'])->exists(), 409, 'This server already has a pending request.');
            $request = RestorePointRequest::query()->create(['uuid' => (string) Str::uuid(), 'business_id' => $device->business_id, 'branch_id' => $device->branch_id, 'device_id' => $device->id, 'restore_point_id' => $point?->id, 'kind' => $kind, 'name' => $name, 'requested_by' => $user->id]);
            $this->audit('backup.'.$kind.'_requested', $device, $request->uuid, $user->id);

            return $request;
        });
    }

    public function delete(User $user, RestorePoint $point): void
    {
        $device = Device::query()->findOrFail($point->device_id);
        $this->authorize($user, $device, 'delete');
        DB::transaction(function () use ($point, $device, $user): void {
            Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();
            abort_if(RestorePointRequest::query()->where('restore_point_id', $point->id)->whereIn('status', ['pending', 'processing'])->exists(), 409, 'Cancel the pending restore request first.');
            // Keep a tombstone so an offline upload retry cannot recreate deleted points.
            $point->delete();
            $this->audit('backup.deleted', $device, $point->uuid, $user->id);
        });
        Storage::disk($point->disk)->delete($point->path);
    }

    public function audit(string $action, Device $device, string $uuid, ?int $actor = null): void
    {
        AuditLog::query()->create(['business_id' => $device->business_id, 'branch_id' => $device->branch_id, 'actor_id' => $actor, 'action' => $action, 'subject_type' => 'restore_point', 'subject_id' => $device->id, 'metadata' => ['uuid' => $uuid, 'device_uuid' => $device->uuid]]);
    }

    public function payload(RestorePoint $point): array
    {
        return ['uuid' => $point->uuid, 'name' => $point->name, 'applicationVersion' => $point->application_version, 'schemaVersion' => $point->schema_version, 'capturedAt' => $point->captured_at->toISOString(), 'checksum' => $point->checksum, 'encryptedChecksum' => $point->encrypted_checksum, 'size' => $point->size, 'verificationStatus' => $point->verification_status];
    }
}

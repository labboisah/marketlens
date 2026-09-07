<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Backups\Models\RestorePoint;
use App\Domain\Backups\Models\RestorePointRequest;
use App\Domain\Backups\Services\RestorePointService;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\ShopAppActivationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class RestorePointController extends Controller
{
    private function device(Request $request): Device
    {
        $device = app(ShopAppActivationService::class)->deviceForCredential($request->bearerToken() ?? '');
        abort_unless($device->type === 'shop_app', 403);

        return $device;
    }

    private function points(Device $device)
    {
        return RestorePoint::query()->where('business_id', $device->business_id)->where('branch_id', $device->branch_id)->where('device_id', $device->id);
    }

    private function requests(Device $device)
    {
        return RestorePointRequest::query()->where('business_id', $device->business_id)->where('branch_id', $device->branch_id)->where('device_id', $device->id);
    }

    public function rotateCredential(Request $request, RestorePointService $service)
    {
        $device = $this->device($request);
        $data = $request->validate(['credential' => 'required|string|regex:/^[a-f0-9]{96}$/']);
        DB::transaction(function () use ($request, $device, $data, $service): void {
            $locked = Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($locked->credential_hash, hash_hmac('sha256', $request->bearerToken(), (string) config('app.key'))), 409);
            $locked->update(['credential_hash' => hash_hmac('sha256', $data['credential'], (string) config('app.key'))]);
            $service->audit('backup.recovery_credential_rotated', $locked, $locked->uuid);
        });

        return response()->json(['success' => true]);
    }

    public function index(Request $request, RestorePointService $service)
    {
        $device = $this->device($request);

        return response()->json(['success' => true, 'data' => [
            'points' => $this->points($device)->latest()->limit(100)->get()->map(fn ($point) => $service->payload($point)),
            'requests' => $this->requests($device)->whereIn('status', ['pending', 'processing'])->oldest()->get()->map(fn ($job) => ['uuid' => $job->uuid, 'kind' => $job->kind, 'name' => $job->name, 'status' => $job->status, 'pointUuid' => $job->restore_point_id ? RestorePoint::withTrashed()->find($job->restore_point_id)?->uuid : null, 'requestedAt' => $job->created_at->toISOString()]),
        ]]);
    }

    public function store(Request $request, RestorePointService $service)
    {
        $device = $this->device($request);
        $request->validate(['metadata' => ['required', 'json'], 'file' => ['required', 'file', 'max:'.config('restore_points.max_size_kb')]]);
        $metadata = json_decode((string) $request->string('metadata'), true);
        abort_unless(is_array($metadata), 422, 'Metadata must be a JSON object.');
        $data = Validator::make($metadata, [
            'uuid' => 'required|uuid', 'name' => 'required|string|max:120', 'applicationVersion' => 'required|string|max:80',
            'schemaVersion' => 'required|integer|min:0', 'capturedAt' => 'required|date',
            'checksum' => 'required|regex:/^[a-f0-9]{64}$/', 'encryptedChecksum' => 'required|regex:/^[a-f0-9]{64}$/',
            'businessUuid' => 'required|uuid', 'branchUuid' => 'required|uuid', 'deviceUuid' => 'required|uuid',
        ])->validate();
        abort_unless($data['businessUuid'] === $device->business->uuid && $data['branchUuid'] === $device->branch->uuid && $data['deviceUuid'] === $device->uuid, 403);
        $file = $request->file('file');
        abort_unless($file->getSize() > 52 && file_get_contents($file->getRealPath(), false, null, 0, 8) === 'MLBACK01', 422, 'Invalid encrypted backup envelope.');
        abort_unless(hash_equals($data['encryptedChecksum'], hash_file('sha256', $file->getRealPath())), 422, 'Encrypted checksum mismatch.');
        $point = DB::transaction(function () use ($device, $data, $file, $service) {
            Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();
            $existing = RestorePoint::withTrashed()->where('uuid', $data['uuid'])->first();
            if ($existing) {
                abort_unless($existing->device_id === $device->id && $existing->business_id === $device->business_id && $existing->branch_id === $device->branch_id, 403);
                abort_if($existing->trashed(), 410, 'This restore point was deleted.');
                abort_unless(hash_equals($existing->encrypted_checksum, $data['encryptedChecksum']) && hash_equals($existing->checksum, $data['checksum']) && $existing->schema_version === (int) $data['schemaVersion'], 409, 'Restore point UUID is already used.');

                return $existing;
            }
            abort_if($this->points($device)->count() >= config('restore_points.max_points_per_device'), 422, 'Restore point limit reached. Delete an older point before uploading.');
            $disk = config('restore_points.disk');
            abort_if($disk === 'public' || config('filesystems.disks.'.$disk.'.visibility') === 'public', 503, 'Restore points require private storage.');
            $stored = $file->storeAs('restore-points/'.$device->business->uuid.'/'.$device->branch->uuid.'/'.$device->uuid, $data['uuid'].'.mlbackup', ['disk' => $disk, 'visibility' => 'private']);
            abort_unless($stored, 503, 'Could not store backup.');
            $point = RestorePoint::query()->create(['uuid' => $data['uuid'], 'business_id' => $device->business_id, 'branch_id' => $device->branch_id, 'device_id' => $device->id, 'name' => $data['name'], 'application_version' => $data['applicationVersion'], 'schema_version' => $data['schemaVersion'], 'captured_at' => $data['capturedAt'], 'checksum' => $data['checksum'], 'encrypted_checksum' => $data['encryptedChecksum'], 'size' => $file->getSize(), 'disk' => $disk, 'path' => $stored]);
            $service->audit('backup.uploaded', $device, $point->uuid);

            return $point;
        });

        return response()->json(['success' => true, 'data' => $service->payload($point)]);
    }

    public function download(Request $request, string $uuid, RestorePointService $service)
    {
        $device = $this->device($request);
        $point = $this->points($device)->where('uuid', $uuid)->firstOrFail();
        abort_unless(Storage::disk($point->disk)->exists($point->path), 404);
        $service->audit('backup.downloaded', $device, $point->uuid);

        return Storage::disk($point->disk)->download($point->path, $point->uuid.'.mlbackup', ['Cache-Control' => 'no-store']);
    }

    public function delete(Request $request, string $uuid, RestorePointService $service)
    {
        $device = $this->device($request);
        DB::transaction(function () use ($device, $uuid, $service): void {
            Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();
            $point = $this->points($device)->where('uuid', $uuid)->firstOrFail();
            abort_if($this->requests($device)->where('restore_point_id', $point->id)->whereIn('status', ['pending', 'processing'])->exists(), 409);
            $point->delete();
            Storage::disk($point->disk)->delete($point->path);
            $service->audit('backup.deleted_by_server', $device, $uuid);
        });

        return response()->json(['success' => true]);
    }

    public function updateRequest(Request $request, string $uuid, RestorePointService $service)
    {
        $device = $this->device($request);
        $data = $request->validate(['status' => 'required|in:processing,completed,failed,rejected', 'detail' => 'nullable|string|max:500']);
        $job = DB::transaction(function () use ($device, $uuid, $data, $service) {
            $job = $this->requests($device)->where('uuid', $uuid)->lockForUpdate()->firstOrFail();
            if ($job->status === $data['status']) {
                return $job;
            }
            abort_unless(in_array($job->status, ['pending', 'processing']), 409, 'Request is no longer active.');
            if ($data['status'] === 'completed' && $job->kind === 'create') {
                abort_unless($this->points($device)->where('uuid', $uuid)->exists(), 409, 'Upload the restore point before completing the request.');
            }
            if ($data['status'] === 'completed') {
                abort_unless($job->status === 'processing', 409);
            }
            $job->update(['status' => $data['status'], 'detail' => $data['detail'] ?? null, 'completed_at' => in_array($data['status'], ['completed', 'failed', 'rejected']) ? now() : null]);
            $service->audit('backup.request_'.$data['status'], $device, $uuid);

            return $job;
        });

        return response()->json(['success' => true, 'data' => ['uuid' => $job->uuid, 'status' => $job->status]]);
    }
}

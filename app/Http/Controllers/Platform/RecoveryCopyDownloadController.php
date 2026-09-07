<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Backups\Models\RestorePoint;
use App\Domain\Backups\Services\RestorePointService;
use App\Domain\Devices\Models\Device;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecoveryCopyDownloadController
{
    public function __invoke(string $uuid, RestorePointService $service): StreamedResponse
    {
        $point = RestorePoint::query()->where('uuid', $uuid)->firstOrFail();
        $device = Device::query()->findOrFail($point->device_id);
        $service->authorize(auth()->user(), $device, 'restore');
        abort_unless($point->business_id === $device->business_id && $point->branch_id === $device->branch_id, 403);
        $disk = Storage::disk($point->disk);
        abort_unless($disk->exists($point->path), 404);
        $service->audit('backup.recovery_copy_downloaded', $device, $uuid, auth()->id());

        return $disk->download($point->path, $uuid.'.mlbackup', ['Cache-Control' => 'no-store']);
    }
}

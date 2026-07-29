<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Synchronization\Services\SyncBatchIntakeService;
use App\Domain\Synchronization\Services\SyncDeviceAuthenticator;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\SuccessResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SyncController extends Controller
{
    public function store(Request $request, SyncDeviceAuthenticator $authenticator, SyncBatchIntakeService $intake): SuccessResource
    {
        $rawBody = $request->getContent();
        $device = $authenticator->authenticate($request, $rawBody);
        $decoded = $intake->decodePayload($rawBody, $request->header('Content-Encoding'));

        return new SuccessResource([
            'message' => 'Sync batch received.',
            ...$intake->receive($device->device, $decoded['payload'], $decoded['payload_hash'], $request->headers->get('X-ML-Trace-Id') ?: (string) Str::uuid()),
        ]);
    }

    public function show(string $batchUuid, Request $request, SyncDeviceAuthenticator $authenticator, SyncBatchIntakeService $intake): SuccessResource
    {
        $device = $authenticator->authenticate($request, $request->getContent());
        $batch = $intake->getBatchForDevice($device->device, $batchUuid);

        return new SuccessResource([
            'batch_uuid' => $batch->uuid,
            'status' => $batch->status->value,
            'accepted' => $batch->accepted_records ?? [],
            'rejected' => $batch->rejected_records ?? [],
            'conflicts' => $batch->conflicting_records ?? [],
            'trace_id' => $batch->trace_id,
            'received_at' => $batch->received_at?->toISOString(),
            'processed_at' => $batch->processed_at?->toISOString(),
        ]);
    }

    public function acknowledge(Request $request, SyncDeviceAuthenticator $authenticator, SyncBatchIntakeService $intake): SuccessResource
    {
        $device = $authenticator->authenticate($request, $request->getContent());

        return new SuccessResource([
            'message' => 'Sync acknowledgement received.',
            ...$intake->acknowledge($device->device, $request->all()),
        ]);
    }

    public function checkpoint(Request $request, SyncDeviceAuthenticator $authenticator, SyncBatchIntakeService $intake): SuccessResource
    {
        $device = $authenticator->authenticate($request, $request->getContent());

        return new SuccessResource($intake->checkpoint($device->device));
    }
}

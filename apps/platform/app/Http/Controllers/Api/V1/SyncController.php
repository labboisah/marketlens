<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Synchronization\Jobs\ProcessSyncBatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sync\StoreSyncBatchRequest;
use App\Http\Resources\Api\SuccessResource;

class SyncController extends Controller
{
    public function store(StoreSyncBatchRequest $request): SuccessResource
    {
        return new SuccessResource([
            'message' => 'Sync batch accepted placeholder.',
            'batch_uuid' => $request->string('batch_uuid')->toString(),
            'queued_job' => ProcessSyncBatch::class,
        ]);
    }

    public function show(string $batch): SuccessResource
    {
        return new SuccessResource(['batch' => $batch, 'status' => 'placeholder']);
    }
}

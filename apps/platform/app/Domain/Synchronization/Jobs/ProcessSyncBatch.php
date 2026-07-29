<?php

namespace App\Domain\Synchronization\Jobs;

use App\Domain\Synchronization\Models\SyncBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessSyncBatch implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $syncBatchId)
    {
        $this->onQueue('sync');
    }

    public function handle(): void
    {
        $batch = SyncBatch::query()->findOrFail($this->syncBatchId);

        $batch->forceFill([
            'processed_at' => now(),
        ])->save();
    }
}

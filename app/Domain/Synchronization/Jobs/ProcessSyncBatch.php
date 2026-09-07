<?php

namespace App\Domain\Synchronization\Jobs;

use App\Domain\Reporting\Jobs\RefreshDashboardSummaries;
use App\Domain\Synchronization\Models\SyncBatch;
use App\Domain\Synchronization\Models\SyncRecord;
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

        $dates = SyncRecord::query()
            ->withoutGlobalScopes()
            ->where('sync_batch_id', $batch->id)
            ->where('status', 'accepted')
            ->get('occurred_at')
            ->map(fn (SyncRecord $record): string => $record->occurred_at->toDateString())
            ->unique();

        foreach ($dates as $date) {
            (new RefreshDashboardSummaries($batch->business_id, $batch->branch_id, $date))->handle();
        }
    }
}

<?php

namespace App\Domain\Reporting\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshDashboardSummaries implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $businessId,
        public readonly ?int $branchId,
        public readonly string $summaryDate,
    ) {}

    public function handle(): void
    {
        // Summary calculation is intentionally deferred until synchronization
        // payloads define the production aggregate inputs.
    }
}

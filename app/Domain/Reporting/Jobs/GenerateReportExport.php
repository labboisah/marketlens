<?php

namespace App\Domain\Reporting\Jobs;

use App\Domain\Reporting\Models\ReportExport;
use App\Domain\Reporting\Services\ReportCentreService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateReportExport implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $reportExportId)
    {
        $this->onQueue('reports');
    }

    public function handle(ReportCentreService $reports): void
    {
        $export = ReportExport::query()->findOrFail($this->reportExportId);
        $export->forceFill(['status' => 'processing', 'started_at' => now()])->save();

        try {
            $result = $reports->generate($export);

            $export->forceFill([
                'status' => 'completed',
                'content' => $result['content'],
                'rows_count' => $result['rows_count'],
                'completed_at' => now(),
                'expires_at' => now()->addHours(24),
            ])->save();
        } catch (Throwable $throwable) {
            $export->forceFill([
                'status' => 'failed',
                'failure_reason' => str($throwable->getMessage())->limit(500)->toString(),
                'completed_at' => now(),
            ])->save();

            throw $throwable;
        }
    }
}

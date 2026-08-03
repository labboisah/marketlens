<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Domain\Services\Services\ServiceMonitoringSummary;

class RefreshServiceMonitoringSummaries extends Command
{
    protected $signature = 'services:refresh-summaries {business_id} {--branch=}
        ';

    protected $description = 'Refresh cached service monitoring summaries for a business (and optional branch)';

    public function handle(ServiceMonitoringSummary $summaryService)
    {
        $businessId = (int) $this->argument('business_id');
        $branch = $this->option('branch') ? (int) $this->option('branch') : null;

        $this->info('Refreshing summaries...');
        $res = $summaryService->refresh($businessId, $branch);
        $this->info('Refreshed.');
        $this->line(json_encode($res));
        return 0;
    }
}

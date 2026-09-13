<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Production\ProductionMonitoring;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BranchContext;
use App\Domain\Tenancy\Services\BusinessContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductionExportController
{
    public function __invoke(Request $request, BusinessContext $context, BranchContext $branches, ProductionMonitoring $monitoring): StreamedResponse
    {
        $business = Business::query()->findOrFail($context->id());
        abort_unless($request->user()->hasBusinessPermission($business, 'export-production'), 403);
        $data = $monitoring->data($request->user(), $business, $branches->id());

        return response()->streamDownload(function () use ($data): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['uuid', 'kind', 'data'], ',', '"', '');
            foreach ($data['records'] as $record) {
                $values = [$record['uuid'], $record['kind'], json_encode($record['data'], JSON_THROW_ON_ERROR)];
                $values = array_map(fn ($value) => preg_match('/^[\\s]*[=+@-]/', $value) ? "'".$value : $value, $values);
                fputcsv($output, $values, ',', '"', '');
            }
            fclose($output);
        }, 'production.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

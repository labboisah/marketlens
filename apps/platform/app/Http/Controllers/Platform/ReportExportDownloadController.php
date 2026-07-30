<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Reporting\Models\ReportExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportDownloadController extends Controller
{
    public function __invoke(Request $request, string $export): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $reportExport = ReportExport::query()
            ->where('uuid', $export)
            ->firstOrFail();

        abort_unless(auth()->user()?->ownedBusinesses()->whereKey($reportExport->business_id)->exists(), 403);
        abort_if($reportExport->status !== 'completed' || ! $reportExport->content, 404);
        abort_if($reportExport->expires_at?->isPast(), 410);

        $extension = $reportExport->format === 'pdf' ? 'pdf' : 'csv';
        $contentType = $reportExport->format === 'pdf' ? 'application/pdf' : 'text/csv';

        return response()->streamDownload(
            fn () => print $reportExport->content,
            'marketlens-'.$reportExport->report_key.'-'.$reportExport->created_at->format('YmdHis').'.'.$extension,
            ['Content-Type' => $contentType]
        );
    }
}



<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Versions\Services\ApplicationVersionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DesktopDownloadController extends Controller
{
    public function __invoke(Request $request, string $application, ApplicationVersionService $versions): RedirectResponse
    {
        $architecture = $request->query('architecture', 'x64');
        abort_unless(is_string($architecture) && in_array($architecture, ['x64', 'arm64', 'ia32'], true), 422, 'Unsupported installer architecture.');
        $manifest = $versions->desktopManifest($application === 'business-server' ? 'shop_app' : 'client_app', 'stable', $architecture);
        abort_unless($manifest && $manifest['downloadUrl'], 404, 'No published Windows installer is available for this application and architecture.');

        return redirect($manifest['downloadUrl'])->header('Cache-Control', 'no-store');
    }
}

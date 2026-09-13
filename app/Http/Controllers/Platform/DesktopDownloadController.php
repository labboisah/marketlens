<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Versions\Services\ApplicationVersionService;
use App\Domain\Versions\Services\ManagedReleasePackages;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DesktopDownloadController extends Controller
{
    public function __invoke(Request $request, string $application, ApplicationVersionService $versions, ManagedReleasePackages $packages): RedirectResponse|BinaryFileResponse
    {
        $architecture = $request->query('architecture', 'x64');
        abort_unless(is_string($architecture) && in_array($architecture, ['x64', 'arm64', 'ia32'], true), 422, 'Unsupported installer architecture.');
        if ($package = $packages->latest($application, $architecture)) {
            return response()->download($packages->verifiedPath($package, 'bundle'), $package['bundle']['name'], [
                'Content-Type' => 'application/zip',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'no-store',
                'X-Release-Version' => $package['version'],
                'X-Release-Channel' => $package['channel'],
                'X-Checksum-SHA256' => $package['bundle']['sha256'],
            ]);
        }
        $manifest = $versions->desktopManifest($application === 'business-server' ? 'shop_app' : 'client_app', 'stable', $architecture);
        if (! $manifest || ! $manifest['downloadUrl']) {
            abort_if($request->expectsJson(), 404, 'No published Windows installer is available for this application and architecture.');

            $label = $application === 'business-server' ? 'Business Server' : 'Business App';

            return redirect()->route('public.deployment', ['architecture' => $architecture])
                ->with('download_unavailable', $label.' for Windows '.$architecture.' is not available yet. Generate its managed setup package in the Platform downloads folder before downloading.')
                ->header('Cache-Control', 'no-store');
        }

        return redirect($manifest['downloadUrl'])->header('Cache-Control', 'no-store');
    }
}

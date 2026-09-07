<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Versions\Services\ApplicationVersionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReleaseManifestController extends Controller
{
    public function __invoke(Request $request, string $application, ApplicationVersionService $versions): JsonResponse
    {
        $input = $request->validate([
            'channel' => ['sometimes', Rule::in(['stable', 'beta'])],
            'platform' => ['sometimes', Rule::in(['windows'])],
            'architecture' => ['sometimes', Rule::in(['x64', 'arm64', 'ia32'])],
        ]);
        $manifest = $versions->desktopManifest(
            $application === 'business-server' ? 'shop_app' : 'client_app',
            $input['channel'] ?? 'stable',
            $input['architecture'] ?? 'x64',
        );

        return response()->json($manifest, $manifest ? 200 : 204)->header('Cache-Control', 'no-store');
    }
}

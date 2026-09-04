<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Versions\Models\ApplicationVersion;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationVersionDownloadController extends Controller
{
    public function __invoke(ApplicationVersion $version): StreamedResponse
    {
        abort_unless($version->hasDownload(), 404);
        abort_unless(Storage::disk($version->download_disk)->exists($version->download_path), 404);

        return Storage::disk($version->download_disk)->download(
            $version->download_path,
            $version->download_original_name ?: 'marketlens-'.$version->application.'-'.$version->version
        );
    }
}

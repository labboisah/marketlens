<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ClientSetupKitDownloadController
{
    public function __construct(private readonly ?string $directory = null) {}

    public function __invoke(): BinaryFileResponse
    {
        $directory = $this->directory ?? resource_path('setup-kits');
        $path = $directory.'/marketlens-client-setup-v1.zip';
        $manifestPath = $directory.'/manifest.json';
        abort_unless(is_file($path) && is_file($manifestPath), 404, 'The client setup pack is not available.');
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        abort_unless(
            is_array($manifest) && is_string($manifest['sha256'] ?? null)
            && hash_equals($manifest['sha256'], (string) hash_file('sha256', $path)),
            503, 'The client setup pack is being updated. Please try again later.',
        );

        return response()->download($path, 'marketlens-client-setup-v1.zip', [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

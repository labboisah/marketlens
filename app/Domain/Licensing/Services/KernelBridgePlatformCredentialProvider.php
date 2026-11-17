<?php

namespace App\Domain\Licensing\Services;

use KernelBridge\LicensingClient\Platform\PlatformCredentialProvider;

final class KernelBridgePlatformCredentialProvider implements PlatformCredentialProvider
{
    public function credential(): string
    {
        return trim((string) config('kernelbridge.api_token', ''));
    }
}

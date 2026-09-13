<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

function managedPackageFixture(string $application = 'business-server', string $version = '2.0.0', string $channel = 'stable', string $architecture = 'x64'): string
{
    Storage::disk('downloads')->put('fixture.exe', 'MZ-test-installer-'.$version);
    $result = Artisan::call('releases:pack', [
        'application' => $application, 'installer' => Storage::disk('downloads')->path('fixture.exe'),
        '--release-version' => $version, '--channel' => $channel, '--architecture' => $architecture,
    ]);
    if ($result !== 0) {
        throw new RuntimeException(Artisan::output());
    }

    return "$application/$channel/$version/$architecture";
}

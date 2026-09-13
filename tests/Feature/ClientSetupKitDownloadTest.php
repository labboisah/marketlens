<?php

use App\Http\Controllers\Platform\ClientSetupKitDownloadController;
use Illuminate\Support\Facades\Blade;

it('downloads the complete public setup pack with a verified checksum', function (): void {
    $response = $this->get('/downloads/client-setup-kit')->assertOk()
        ->assertDownload('marketlens-client-setup-v1.zip')
        ->assertHeader('Content-Type', 'application/zip')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    $path = $response->baseResponse->getFile()->getPathname();
    $manifest = json_decode(file_get_contents(resource_path('setup-kits/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
    expect(hash_file('sha256', $path))->toBe($manifest['sha256']);
    expect(filesize($path))->toBe($manifest['bytes']);
    expect($manifest['schemaVersion'])->toBe(1);
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    try {
        $expected = ['READ-ME.txt', 'START-HERE.md', 'NEW-INSTALLATION.md', 'UPGRADE-CHECKLIST.md', 'manifest.json', 'guides/CSV-DATA-TRANSFERS.md'];
        $keys = array_column($manifest['datasets'], 'key');
        expect($keys)->toHaveCount(18)->toContain('products', 'opening_stock', 'opening_receivables', 'opening_payables', 'purchase_headers', 'purchase_lines', 'stock_counts', 'services');
        expect($keys)->not->toContain('users', 'bank_accounts', 'share_ownership');
        foreach ($manifest['datasets'] as $dataset) {
            $template = $dataset['key'].'-v1-template.csv';
            $expected = [...$expected, $template, 'examples/'.$dataset['key'].'-example.csv', 'guides/'.$dataset['key'].'.txt'];
            $content = $zip->getFromName($template);
            expect($content)->toStartWith("\xEF\xBB\xBF");
            expect(substr_count($content, "\n"))->toBe(1);
            expect(str_getcsv(trim(substr($content, 3)), ',', '"', ''))->toBe(['schema_version', ...array_column($dataset['columns'], 'key')]);
            expect($zip->getFromName('examples/'.$dataset['key'].'-example.csv'))->not->toBeFalse();
            expect($zip->getFromName('guides/'.$dataset['key'].'.txt'))->toContain('required');
        }
        expect($manifest['productionDatasets'])->toHaveCount(7);
        $expected[] = 'production/README.md';
        foreach ($manifest['productionDatasets'] as $dataset) {
            $template = 'production/templates/'.$dataset['key'].'.csv';
            $example = 'production/examples/'.$dataset['key'].'.csv';
            $expected = [...$expected, $template, $example];
            expect($zip->getFromName($template))->toContain('schema_version');
            expect($zip->getFromName($example))->not->toBeFalse();
        }
        expect($zip->getFromName('production/README.md'))->toContain('production.setup');
        $actual = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $actual[] = $zip->getNameIndex($i);
        }
        sort($actual);
        sort($expected);
        expect($actual)->toBe($expected);
        expect($zip->getFromName('UPGRADE-CHECKLIST.md'))->toContain('Upgrade Business Server first');
        expect($zip->getFromName('NEW-INSTALLATION.md'))->toContain('pairing');
        $inside = json_decode($zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        expect($inside['datasets'])->toBe($manifest['datasets']);
    } finally {
        $zip->close();
    }
});

it('exposes the setup pack alongside the existing installer links', function (): void {
    $html = Blade::render('<x-desktop-downloads />');
    expect($html)->toContain(route('public.client-setup-kit'), route('public.desktop-download', ['application' => 'business-server']), route('public.desktop-download', ['application' => 'business-app']));
});

it('refuses an incomplete or inconsistent setup pack deployment', function (): void {
    $temporary = sys_get_temp_dir().'/marketlens-kit-'.bin2hex(random_bytes(8));
    mkdir($temporary.'/setup-kits', 0777, true);
    $this->app->bind(ClientSetupKitDownloadController::class, fn () => new ClientSetupKitDownloadController($temporary.'/setup-kits'));
    try {
        $this->get('/downloads/client-setup-kit')->assertNotFound();
        file_put_contents($temporary.'/setup-kits/marketlens-client-setup-v1.zip', 'incomplete');
        file_put_contents($temporary.'/setup-kits/manifest.json', json_encode(['sha256' => str_repeat('0', 64)]));
        $this->get('/downloads/client-setup-kit')->assertStatus(503);
    } finally {
        foreach (glob($temporary.'/setup-kits/*') as $file) {
            unlink($file);
        }
        rmdir($temporary.'/setup-kits');
        rmdir($temporary);
    }
});

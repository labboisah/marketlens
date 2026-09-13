<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Versions\Services\ManagedReleasePackages;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

class PackDesktopRelease extends Command
{
    protected $signature = 'releases:pack {application : business-server or business-app} {installer : Built Windows installer path} {--release-version=} {--channel=beta} {--architecture=x64}';

    protected $description = 'Package a built installer and setup requirements into the Platform downloads folder';

    public function handle(ManagedReleasePackages $packages): int
    {
        $application = $this->argument('application');
        $version = $this->option('release-version');
        $channel = $this->option('channel');
        $architecture = $this->option('architecture');
        $installer = realpath($this->argument('installer'));
        if (! in_array($application, ['business-server', 'business-app'], true) ||
            ! is_string($version) || ! preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/D', $version) ||
            ! in_array($channel, ['stable', 'beta', 'internal'], true) ||
            ! in_array($architecture, ['x64', 'arm64', 'ia32'], true) ||
            ! $installer || ! is_file($installer) || strtolower(pathinfo($installer, PATHINFO_EXTENSION)) !== 'exe') {
            $this->error('Provide a built .exe, semantic version, valid application, channel and architecture.');

            return self::FAILURE;
        }
        $handle = fopen($installer, 'rb');
        $magic = fread($handle, 2);
        fclose($handle);
        if ($magic !== 'MZ') {
            $this->error('The input is not a Windows executable.');

            return self::FAILURE;
        }
        $kit = resource_path('setup-kits/marketlens-client-setup-v1.zip');
        $kitManifest = json_decode(file_get_contents(resource_path('setup-kits/manifest.json')), true);
        if (! is_file($kit) || ! hash_equals($kitManifest['sha256'] ?? '', hash_file('sha256', $kit))) {
            $this->error('Rebuild the client setup kit before packaging.');

            return self::FAILURE;
        }
        $key = "$application/$channel/$version/$architecture";
        $target = $packages->root().'/'.$key;
        if (file_exists($target)) {
            $this->error('This release folder already exists. Release artifacts are immutable; use a new version.');

            return self::FAILURE;
        }
        $parent = dirname($target);
        File::ensureDirectoryExists($parent);
        $staging = $parent.'/.packing-'.Str::uuid();
        File::ensureDirectoryExists($staging);
        try {
            $installerName = "$application-$version-$architecture-setup.exe";
            $bundleName = "$application-$version-$architecture.zip";
            File::copy($installer, $staging.'/'.$installerName);
            $label = $application === 'business-server' ? 'Business Server' : 'Business App';
            $instructions = "# $label $version\n\nChannel: $channel. Architecture: Windows $architecture.\n\nExtract the whole ZIP, read REQUIREMENTS.md, then run $installerName.\n\n".
                ($application === 'business-server'
                    ? "Install on the main branch computer. Activate through the Platform, configure local users and backups, then validate setup templates. Upgrade the Server before staff Apps.\n"
                    : "Install on a staff workstation. First install/upgrade the branch Server, connect over its LAN address, authorize this workstation and sign in with a local staff account. Do not import opening stock on each workstation.\n").
                "\nFor upgrades, finish active work and verify a Server backup. Preserve existing data. Never downgrade a schema-5 Server database to a schema-4 executable.\n".
                "\nThe setup-templates ZIP contains templates, examples and installation/upgrade checklists. Do not import examples into a live business.\n".
                ($channel === 'stable' ? '' : "\nThis is a $channel package for testing. Development signing is not a publicly trusted production certificate. Verify the publisher with your administrator; do not disable Windows security or import an unknown certificate.\n");
            $requirements = "# Requirements\n\n- A supported Windows computer matching $architecture, with permission to install applications.\n- Sufficient free disk space for installation, business data and backups.\n- A private LAN or Wi-Fi connection between workstations and the branch Server.\n- Internet for Platform activation, subscription refresh and synchronization.\n- A configured business/branch and subscription; staff accounts are created on the Server.\n- Verify printer drivers where receipts are used.\n\nThe installer bundles Electron and the application runtime. End users do not need to install Node.js, PHP, PostgreSQL or development tools.\n\n".
                ($application === 'business-server' ? "Keep the main computer available during trading. Configure a separate backup destination and verify recovery before live use.\n" : "An activated compatible Business Server must be available on the local network. The Business App never owns or opens the Server database.\n");
            $zip = new ZipArchive;
            if ($zip->open($staging.'/'.$bundleName, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new \RuntimeException('Cannot create release ZIP.');
            }
            $zip->addFile($staging.'/'.$installerName, $installerName);
            $zip->setCompressionName($installerName, ZipArchive::CM_STORE);
            $zip->addFromString('START-HERE.md', $instructions);
            $zip->addFromString('REQUIREMENTS.md', $requirements);
            $zip->addFile($kit, 'setup-templates.zip');
            if (! $zip->close()) {
                throw new \RuntimeException('Cannot finish release ZIP.');
            }
            $metadata = fn ($name) => ['name' => $name, 'bytes' => filesize($staging.'/'.$name), 'sha256' => hash_file('sha256', $staging.'/'.$name)];
            $manifest = ['schemaVersion' => 1, 'application' => $application, 'version' => $version, 'channel' => $channel, 'architecture' => $architecture, 'installer' => $metadata($installerName), 'bundle' => $metadata($bundleName)];
            File::put($staging.'/release.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
            if (! rename($staging, $target)) {
                throw new \RuntimeException('Cannot publish the completed package directory.');
            }
            $this->info('Package ready: '.$key);

            return self::SUCCESS;
        } finally {
            // Only this command's unique staging directory may be removed.
            if (is_dir($staging)) {
                File::deleteDirectory($staging);
            }
        }
    }
}

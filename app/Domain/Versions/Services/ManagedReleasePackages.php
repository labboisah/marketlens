<?php

declare(strict_types=1);

namespace App\Domain\Versions\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ManagedReleasePackages
{
    public function root(): string
    {
        return rtrim(str_replace('\\', '/', Storage::disk('downloads')->path('')), '/');
    }

    public function find(string $key): ?array
    {
        if (! preg_match('~^(business-server|business-app)/(stable|beta|internal)/(\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?)/(x64|arm64|ia32)$~D', $key)) {
            return null;
        }
        $root = realpath($this->root());
        $directory = realpath($this->root().'/'.$key);
        if (! $root || ! $directory || ! str_starts_with(str_replace('\\', '/', $directory).'/', rtrim(str_replace('\\', '/', $root), '/').'/')) {
            return null;
        }
        $manifest = $directory.'/release.json';
        if (! is_file($manifest)) {
            return null;
        }
        $data = json_decode(file_get_contents($manifest), true);
        [$application, $channel, $version, $architecture] = explode('/', $key);
        if (! is_array($data) || ($data['schemaVersion'] ?? null) !== 1 ||
            ($data['application'] ?? null) !== $application || ($data['channel'] ?? null) !== $channel ||
            ($data['version'] ?? null) !== $version || ($data['architecture'] ?? null) !== $architecture) {
            return null;
        }
        foreach (['installer' => 'exe', 'bundle' => 'zip'] as $kind => $extension) {
            $file = $data[$kind] ?? [];
            $name = $file['name'] ?? '';
            if (! is_string($name) || ! preg_match('/^[A-Za-z0-9._-]+$/D', $name) ||
                strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== $extension ||
                ! preg_match('/^[a-f0-9]{64}$/D', $file['sha256'] ?? '') ||
                ! is_int($file['bytes'] ?? null)) {
                return null;
            }
            $path = realpath($directory.'/'.$name);
            if (! $path || dirname($path) !== $directory || ! is_file($path) || filesize($path) !== $file['bytes']) {
                return null;
            }
        }

        return [...$data, 'key' => $key, 'directory' => $directory];
    }

    public function all(): array
    {
        $packages = [];
        foreach (glob($this->root().'/*/*/*/*/release.json') ?: [] as $path) {
            $relative = substr(str_replace('\\', '/', dirname($path)), strlen(rtrim(str_replace('\\', '/', $this->root()), '/')) + 1);
            if ($package = $this->find($relative)) {
                $packages[] = $package;
            }
        }
        usort($packages, fn ($a, $b) => version_compare($b['version'], $a['version']));

        return $packages;
    }

    public function latest(string $application, string $architecture): ?array
    {
        $packages = array_values(array_filter($this->all(), fn ($p) => $p['application'] === $application && $p['architecture'] === $architecture && $p['channel'] !== 'internal'));
        // A stable package remains the public default while a newer beta is tested.
        foreach ($packages as $package) {
            if ($package['channel'] === 'stable') {
                return $package;
            }
        }

        return $packages[0] ?? null;
    }

    public function verifiedPath(array $package, string $kind): string
    {
        $path = $package['directory'].'/'.$package[$kind]['name'];
        abort_unless(hash_equals($package[$kind]['sha256'], hash_file('sha256', $path)), 503, 'Release package checksum mismatch.');

        return $path;
    }

    public function installerMetadata(array $data): array
    {
        $package = $this->find($data['managed_package'] ?? '');
        if (! $package ||
            $data['application'] !== ($package['application'] === 'business-server' ? 'shop_app' : 'client_app') ||
            strtolower($data['platform']) !== 'windows' || $data['architecture'] !== $package['architecture'] ||
            $data['version'] !== $package['version'] || $data['release_channel'] !== $package['channel']) {
            throw ValidationException::withMessages(['managedPackage' => 'Select a complete package matching the application, version, channel and architecture.']);
        }
        $this->verifiedPath($package, 'installer');
        $this->verifiedPath($package, 'bundle');

        return [
            'download_disk' => 'downloads',
            'download_path' => $package['key'].'/'.$package['installer']['name'],
            'download_original_name' => $package['installer']['name'],
            'download_mime_type' => 'application/octet-stream',
            'download_size_bytes' => $package['installer']['bytes'],
            'checksum' => $package['installer']['sha256'],
        ];
    }
}

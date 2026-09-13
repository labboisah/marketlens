<?php

namespace App\Domain\Versions\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Devices\Models\Device;
use App\Domain\Versions\Models\ApplicationVersion;
use App\Enums\ApplicationVersionStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ApplicationVersionService
{
    /** @param array<string, mixed> $data */
    public function createVersion(User $actor, array $data, ?UploadedFile $download): ApplicationVersion
    {
        $payload = [
            'application' => $data['application'],
            'platform' => $data['platform'] ?: null,
            'architecture' => $data['architecture'] ?: null,
            'version' => $data['version'],
            'release_channel' => $data['release_channel'],
            'status' => $data['status'],
            'release_notes' => $data['release_notes'] ?: null,
            'minimum_supported_version' => $data['minimum_supported_version'] ?: null,
            'recommended_version' => $data['recommended_version'] ?: null,
            'mandatory_update' => (bool) ($data['mandatory_update'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'released_at' => $data['released_at'] ?: null,
        ];

        if ($download) {
            $extension = $download->getClientOriginalExtension() ?: 'bin';
            $path = $download->storeAs(
                'application-versions/'.$payload['application'].'/'.$payload['release_channel'].'/'.$payload['version'],
                (string) Str::uuid().'.'.$extension,
                'local'
            );
            $fullPath = Storage::disk('local')->path($path);

            $payload = [
                ...$payload,
                'download_disk' => 'local',
                'download_path' => $path,
                'download_original_name' => $download->getClientOriginalName(),
                'download_mime_type' => $download->getMimeType() ?: 'application/octet-stream',
                'download_size_bytes' => $download->getSize() ?: 0,
                'checksum' => is_file($fullPath) ? hash_file('sha256', $fullPath) : null,
            ];
        }

        if (! empty($data['managed_package'])) {
            $payload = [...$payload, ...app(ManagedReleasePackages::class)->installerMetadata($data)];
        }

        $version = ApplicationVersion::query()->create($payload);

        AuditLog::query()->create([
            'actor_id' => $actor->id,
            'action' => 'application_version.created',
            'subject_type' => ApplicationVersion::class,
            'subject_id' => $version->id,
            'metadata' => ['application' => $version->application, 'version' => $version->version, 'channel' => $version->release_channel],
        ]);

        return $version;
    }

    /** @param array<string, mixed> $filters */
    public function updatePayload(Device $device, array $filters = []): array
    {
        $application = $filters['application'] ?? $device->type;
        $platform = $filters['platform'] ?? data_get($device->operating_system, 'name');
        $architecture = $filters['architecture'] ?? data_get($device->operating_system, 'architecture');
        $channel = $filters['release_channel'] ?? 'stable';
        $currentVersion = (string) ($filters['current_version'] ?? $device->app_version ?? '0.0.0');

        $latest = $this->latestVersion($application, $platform, $architecture, $channel);
        $minimumSupported = $latest?->minimum_supported_version;
        $updateAvailable = $latest ? $this->isNewer($latest->version, $currentVersion) : false;
        $belowMinimum = $minimumSupported ? $this->isNewer($minimumSupported, $currentVersion) : false;
        $mandatory = $latest ? ($belowMinimum || ($latest->mandatory_update && $updateAvailable)) : false;

        return [
            'current_version' => $currentVersion,
            'latest_version' => $latest?->version,
            'minimum_supported_version' => $minimumSupported,
            'recommended_version' => $latest?->recommended_version,
            'update_available' => $updateAvailable,
            'mandatory_update' => $mandatory,
            'release_channel' => $channel,
            'application' => $application,
            'platform' => $platform,
            'architecture' => $architecture,
            'download' => $latest ? $this->downloadMetadata($latest) : null,
            'checksum' => $latest?->checksum,
            'release_notes' => $latest?->release_notes,
        ];
    }

    public function signedDownloadUrl(ApplicationVersion $version): ?string
    {
        if (! $version->hasDownload()) {
            return null;
        }

        return URL::temporarySignedRoute('platform.application-versions.download', now()->addMinutes(30), ['version' => $version->id]);
    }

    /** @return array<string, mixed>|null */
    public function desktopManifest(string $application, string $channel, string $architecture): ?array
    {
        $releases = ApplicationVersion::query()
            ->where('application', $application)
            ->where('release_channel', $channel)
            ->where('status', ApplicationVersionStatus::Published)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('released_at')->orWhere('released_at', '<=', now()))
            ->whereRaw('lower(platform) = ?', ['windows'])
            ->whereRaw('lower(architecture) = ?', [$architecture])
            ->get()
            ->filter(fn (ApplicationVersion $release): bool => preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $release->version) === 1
                && preg_match('/^[a-f0-9]{64}$/i', $release->checksum ?? '') === 1
                && strtolower(pathinfo($release->download_original_name ?? '', PATHINFO_EXTENSION)) === 'exe'
                && $release->hasDownload()
                && Storage::disk($release->download_disk)->exists($release->download_path))
            ->sort(fn (ApplicationVersion $a, ApplicationVersion $b): int => version_compare($b->version, $a->version));
        $release = $releases->first();
        if (! $release) {
            return null;
        }

        return [
            'application' => $application === 'shop_app' ? 'business-server' : 'business-app',
            'platform' => 'windows',
            'architecture' => $architecture,
            'latestVersion' => $release->version,
            'minimumSupportedVersion' => $release->minimum_supported_version ?: '0.0.0',
            'mandatory' => (bool) $release->mandatory_update,
            'releaseNotes' => $release->release_notes ?? '',
            'downloadUrl' => $this->signedDownloadUrl($release),
            'checksum' => strtolower($release->checksum),
            'channel' => $channel,
        ];
    }

    private function latestVersion(string $application, ?string $platform, ?string $architecture, string $channel): ?ApplicationVersion
    {
        return ApplicationVersion::query()
            ->where('application', $application)
            ->where('release_channel', $channel)
            ->where('status', ApplicationVersionStatus::Published)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('released_at')->orWhere('released_at', '<=', now()))
            ->where(function ($query) use ($platform): void {
                $query->whereNull('platform');
                if (filled($platform)) {
                    $query->orWhereRaw('lower(platform) = ?', [strtolower((string) $platform)]);
                }
            })
            ->where(function ($query) use ($architecture): void {
                $query->whereNull('architecture');
                if (filled($architecture)) {
                    $query->orWhereRaw('lower(architecture) = ?', [strtolower((string) $architecture)]);
                }
            })
            ->orderByRaw('case when platform is null then 0 else 1 end desc')
            ->orderByRaw('case when architecture is null then 0 else 1 end desc')
            ->orderByDesc('released_at')
            ->orderByDesc('id')
            ->first();
    }

    private function isNewer(string $candidate, string $current): bool
    {
        return version_compare($this->normalizeVersion($candidate), $this->normalizeVersion($current), '>');
    }

    private function normalizeVersion(string $version): string
    {
        return ltrim(trim($version), 'vV') ?: '0.0.0';
    }

    /** @return array<string, mixed> */
    private function downloadMetadata(ApplicationVersion $version): array
    {
        return [
            'url' => $this->signedDownloadUrl($version),
            'file_name' => $version->download_original_name,
            'mime_type' => $version->download_mime_type,
            'size_bytes' => $version->download_size_bytes,
            'checksum' => $version->checksum,
        ];
    }
}

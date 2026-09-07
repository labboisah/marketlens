<?php

namespace App\Livewire\SuperAdmin;

use App\Domain\Versions\Models\ApplicationVersion;
use App\Domain\Versions\Services\ApplicationVersionService;
use App\Enums\ApplicationVersionStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.super-admin')]
class ApplicationVersionManagementPage extends Component
{
    use WithFileUploads;

    public string $application = 'shop_app';

    public string $platform = '';

    public string $architecture = '';

    public string $version = '';

    public string $releaseChannel = 'stable';

    public string $status = 'draft';

    public string $releaseNotes = '';

    public string $minimumSupportedVersion = '';

    public string $recommendedVersion = '';

    public bool $mandatoryUpdate = false;

    public bool $isActive = true;

    public string $releasedAt = '';

    public ?TemporaryUploadedFile $download = null;

    public function render(ApplicationVersionService $versions): View
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        return view('livewire.super-admin.application-version-management-page', [
            'versions' => ApplicationVersion::query()->latest()->limit(20)->get(),
            'downloadUrls' => ApplicationVersion::query()->latest()->limit(20)->get()->mapWithKeys(fn (ApplicationVersion $version): array => [
                $version->id => $versions->signedDownloadUrl($version),
            ]),
            'statuses' => ApplicationVersionStatus::cases(),
        ])->title('Application Versions');
    }

    public function createVersion(ApplicationVersionService $versions): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $validated = $this->validate([
            'application' => ['required', Rule::in(['shop_app', 'client_app'])],
            'platform' => ['nullable', 'string', 'max:80'],
            'architecture' => ['nullable', 'string', 'max:80'],
            'version' => ['required', 'string', 'max:80', 'regex:/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/'],
            'releaseChannel' => ['required', Rule::in(['stable', 'beta', 'internal'])],
            'status' => ['required', Rule::enum(ApplicationVersionStatus::class)],
            'releaseNotes' => ['nullable', 'string', 'max:10000'],
            'minimumSupportedVersion' => ['nullable', 'string', 'max:80', 'regex:/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/'],
            'recommendedVersion' => ['nullable', 'string', 'max:80'],
            'mandatoryUpdate' => ['boolean'],
            'isActive' => ['boolean'],
            'releasedAt' => ['nullable', 'date'],
            'download' => ['required_if:status,published', 'nullable', 'file', 'max:768000'],
        ]);

        $versions->createVersion(auth()->user(), [
            'application' => $validated['application'],
            'platform' => $validated['platform'] ?? '',
            'architecture' => $validated['architecture'] ?? '',
            'version' => $validated['version'],
            'release_channel' => $validated['releaseChannel'],
            'status' => $validated['status'],
            'release_notes' => $validated['releaseNotes'] ?? '',
            'minimum_supported_version' => $validated['minimumSupportedVersion'] ?? '',
            'recommended_version' => $validated['recommendedVersion'] ?? '',
            'mandatory_update' => $validated['mandatoryUpdate'] ?? false,
            'is_active' => $validated['isActive'] ?? true,
            'released_at' => $validated['releasedAt'] ?? null,
        ], $this->download);

        $this->reset(['platform', 'architecture', 'version', 'releaseNotes', 'minimumSupportedVersion', 'recommendedVersion', 'releasedAt', 'download']);
        $this->mandatoryUpdate = false;
        $this->isActive = true;
        $this->status = 'draft';
        $this->releaseChannel = 'stable';

        $this->dispatch('notify', tone: 'success', title: 'Version created', message: 'The application release was stored and audited.');
    }
}

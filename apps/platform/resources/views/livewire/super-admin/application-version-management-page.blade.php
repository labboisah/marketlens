<div class="flex flex-col gap-6">
  <section class="rounded-lg border border-border bg-surface p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold uppercase text-muted">Release operations</p>
        <h1 class="mt-2 text-2xl font-bold">Application-Version Management</h1>
        <p class="mt-2 max-w-3xl text-sm text-muted">Manage Business Server and Client App releases, rollout channels, minimum supported versions, and secure signed downloads.</p>
      </div>
      <a href="{{ route('super-admin.dashboard', ['module' => 'application-versions']) }}" class="rounded-md border border-border px-3 py-2 text-sm font-semibold hover:border-primary/50">Dashboard module</a>
    </div>
  </section>

  <section class="grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
    <form wire:submit="createVersion" class="rounded-lg border border-border bg-surface p-4">
      <h2 class="font-semibold">Create release</h2>
      <div class="mt-4 grid gap-3 md:grid-cols-2">
        <label class="text-sm font-medium">Application
          <select wire:model="application" class="ml-field mt-1 w-full">
            <option value="shop_app">Business Server</option>
            <option value="client_app">Client App</option>
          </select>
        </label>
        <label class="text-sm font-medium">Version
          <input wire:model="version" class="ml-field mt-1 w-full" placeholder="2.0.0">
          @error('version') <span class="text-xs text-danger">{{ $message }}</span> @enderror
        </label>
        <label class="text-sm font-medium">Platform
          <input wire:model="platform" class="ml-field mt-1 w-full" placeholder="windows, macos, linux, android">
        </label>
        <label class="text-sm font-medium">Architecture
          <input wire:model="architecture" class="ml-field mt-1 w-full" placeholder="x64, arm64">
        </label>
        <label class="text-sm font-medium">Release channel
          <select wire:model="releaseChannel" class="ml-field mt-1 w-full">
            <option value="stable">Stable</option>
            <option value="beta">Beta</option>
            <option value="internal">Internal</option>
          </select>
        </label>
        <label class="text-sm font-medium">Status
          <select wire:model="status" class="ml-field mt-1 w-full">
            @foreach ($statuses as $releaseStatus)
              <option value="{{ $releaseStatus->value }}">{{ ucfirst($releaseStatus->value) }}</option>
            @endforeach
          </select>
        </label>
        <label class="text-sm font-medium">Minimum supported version
          <input wire:model="minimumSupportedVersion" class="ml-field mt-1 w-full" placeholder="1.8.0">
        </label>
        <label class="text-sm font-medium">Recommended version
          <input wire:model="recommendedVersion" class="ml-field mt-1 w-full" placeholder="2.0.0">
        </label>
        <label class="text-sm font-medium">Release date
          <input wire:model="releasedAt" type="datetime-local" class="ml-field mt-1 w-full">
        </label>
        <label class="text-sm font-medium">Download file
          <input wire:model="download" type="file" class="ml-field mt-1 w-full">
          @error('download') <span class="text-xs text-danger">{{ $message }}</span> @enderror
        </label>
      </div>

      <label class="mt-3 block text-sm font-medium">Release notes
        <textarea wire:model="releaseNotes" rows="5" class="ml-field mt-1 w-full" placeholder="What changed in this release"></textarea>
      </label>

      <div class="mt-4 flex flex-wrap gap-4 text-sm">
        <label class="inline-flex items-center gap-2"><input wire:model="mandatoryUpdate" type="checkbox" class="rounded border-border"> Mandatory update</label>
        <label class="inline-flex items-center gap-2"><input wire:model="isActive" type="checkbox" class="rounded border-border"> Active</label>
      </div>

      <div class="mt-5 flex justify-end">
        <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white">Create version</button>
      </div>
    </form>

    <div class="rounded-lg border border-border bg-surface p-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="font-semibold">Recent releases</h2>
          <p class="mt-1 text-sm text-muted">Signed download links expire and are generated only for stored release files.</p>
        </div>
      </div>
      <div class="mt-4 overflow-x-auto">
        <table class="ml-table">
          <thead><tr><th>Release</th><th>Channel</th><th>Policy</th><th>Download</th></tr></thead>
          <tbody>
            @forelse ($versions as $release)
              <tr>
                <td>
                  <p class="font-medium">{{ str_replace('_', ' ', $release->application) }} {{ $release->version }}</p>
                  <p class="text-xs text-muted">{{ $release->platform ?: 'Any platform' }} / {{ $release->architecture ?: 'Any architecture' }}</p>
                  <p class="font-mono text-xs text-muted">{{ $release->checksum ?: 'No checksum yet' }}</p>
                </td>
                <td>
                  <p class="font-semibold">{{ $release->release_channel }}</p>
                  <p class="text-xs text-muted">{{ $release->status->value ?? $release->status }}</p>
                </td>
                <td class="text-sm">
                  <p>Min: {{ $release->minimum_supported_version ?: 'None' }}</p>
                  <p>Recommended: {{ $release->recommended_version ?: 'None' }}</p>
                  <p>{{ $release->mandatory_update ? 'Mandatory' : 'Optional' }} / {{ $release->is_active ? 'Active' : 'Inactive' }}</p>
                </td>
                <td class="text-sm">
                  @if ($downloadUrls[$release->id] ?? null)
                    <a href="{{ $downloadUrls[$release->id] }}" class="font-semibold text-primary">Signed download</a>
                    <p class="text-xs text-muted">{{ number_format((int) $release->download_size_bytes) }} bytes</p>
                  @else
                    <span class="text-muted">No file</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-sm text-muted">No application versions have been created.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>

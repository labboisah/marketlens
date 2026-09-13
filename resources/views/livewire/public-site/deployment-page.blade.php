<div>
  @if (session('download_unavailable'))
    <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8" role="status"><p class="rounded-md border border-border bg-surface p-4 text-sm text-foreground">{{ session('download_unavailable') }}</p></div>
  @endif
  <section class="border-b border-border bg-background">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
      <x-public.breadcrumbs :items="[['label' => 'Deployment']]" />
      <p class="mt-8 text-sm font-semibold uppercase text-accent">Install ? Connect ? Upgrade</p>
      <h1 class="mt-4 max-w-3xl text-4xl font-bold text-foreground sm:text-5xl">Set up your branch. Keep every workstation connected.</h1>
      <p class="mt-5 max-w-3xl text-lg leading-8 text-muted">Install Business Server on the main computer, then connect Business Apps over your local network. Start with your owner-approved data and keep it through future upgrades.</p>
      <div class="mt-6 flex flex-wrap items-center gap-4">
        <a href="{{ route('public.client-setup-kit') }}" class="inline-flex rounded-md bg-accent px-5 py-3 font-semibold text-white hover:bg-accent/90">Download setup templates (ZIP)</a>
        <a href="{{ route('public.setup-kits') }}" class="font-semibold text-accent underline">Browse the setup kit</a>
      </div>
    </div>
  </section>
  <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8" aria-labelledby="installers-heading">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div><h2 id="installers-heading" class="text-2xl font-bold">Windows setup packages</h2><p class="mt-2 text-muted">Download the installer, requirements and setup templates together.</p></div>
      <div><label for="architecture" class="block text-sm font-semibold">Computer architecture</label><select id="architecture" wire:model.live="architecture" class="mt-2 rounded-md border border-border bg-surface px-3 py-2"><option value="x64">x64 ? most Windows PCs</option><option value="arm64">ARM64</option><option value="ia32">32-bit (ia32)</option></select></div>
    </div>
    <div class="mt-6 grid gap-6 md:grid-cols-2">
      @foreach ($installers as $installer)
        <article class="rounded-lg border border-border bg-surface p-6" wire:key="{{ $installer['key'] }}">
          <h3 class="text-xl font-semibold">{{ $installer['label'] }}</h3>
          <p class="mt-3 leading-7 text-muted">{{ $installer['description'] }}</p>
          @if ($installer['version'])
            <p class="mt-4 text-sm">Version {{ $installer['version'] }} / Windows {{ $architecture }} / {{ ucfirst($installer['channel']) }}</p>
            <a href="{{ route('public.desktop-download', ['application' => $installer['key'], 'architecture' => $architecture]) }}" class="mt-5 inline-flex rounded-md bg-primary px-4 py-2 font-semibold text-white">Download {{ $installer['label'] }}{{ $installer['bundle'] ? ' (ZIP)' : '' }}</a>
            @if ($installer['channel'] !== 'stable')<p class="mt-3 text-sm text-muted">Development-signed beta for testing. Confirm the publisher and deployment requirements before installation.</p>@endif
          @else
            <p class="mt-5 rounded-md border border-border bg-background p-3 text-sm text-muted" role="status">No published installer for {{ $architecture }} yet. Check another architecture only if it matches your computer.</p>
          @endif
        </article>
      @endforeach
    </div>
  </section>
  <section class="border-y border-border bg-surface">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
      <h2 class="text-2xl font-bold">A new business, in four steps</h2>
      <ol class="mt-6 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
        <li class="rounded-lg border border-border p-5"><span class="font-semibold text-accent">01 ? Prepare</span><h3 class="mt-3 font-semibold">Agree your starting data</h3><p class="mt-2 text-sm leading-7 text-muted">Confirm the business, branch and subscription. Fill the setup templates and agree stock and debt totals with the owner.</p></li>
        <li class="rounded-lg border border-border p-5"><span class="font-semibold text-accent">02 ? Activate</span><h3 class="mt-3 font-semibold">Set up Business Server</h3><p class="mt-2 text-sm leading-7 text-muted">Generate an activation code in the Platform. Activate the main computer, configure local users and backups, then validate and approve the owner?s data.</p></li>
        <li class="rounded-lg border border-border p-5"><span class="font-semibold text-accent">03 ? Pair</span><h3 class="mt-3 font-semibold">Connect staff workstations</h3><p class="mt-2 text-sm leading-7 text-muted">Install Business App, enter the Server?s LAN address, authorize the workstation on the Server and sign in with a local staff account.</p></li>
        <li class="rounded-lg border border-border p-5"><span class="font-semibold text-accent">04 ? Verify</span><h3 class="mt-3 font-semibold">Reconcile before trading</h3><p class="mt-2 text-sm leading-7 text-muted">Check roles, printers, stock and balances. Verify backups, local operation without internet and synchronization to the correct Platform branch.</p></li>
      </ol>
      <p class="mt-6 text-sm leading-7 text-muted">Adding another workstation? Pair it to the existing Server. Do not activate another Server or import opening balances again for the same live branch.</p>
    </div>
  </section>
  <section class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-2 lg:px-8">
    <div><h2 class="text-2xl font-bold">Upgrade Server before Apps</h2><ol class="mt-5 list-decimal space-y-3 pl-5 leading-7 text-muted"><li>Read release notes, finish active work and verify a current backup.</li><li>Check System Update on Business Server, download and confirm the update.</li><li>Restart and verify activation, data, backup health and synchronization.</li><li>Update one Business App, confirm pairing and role access, then update the remaining workstations.</li></ol><p class="mt-5 leading-7 text-muted">Preserve your existing installation and data directories. Upgrades do not require recreating the business or reimporting opening stock and debts.</p></div>
    <div class="rounded-lg border border-border bg-surface p-6"><h2 class="text-xl font-semibold">Before your first installation</h2><ul class="mt-4 list-disc space-y-3 pl-5 leading-7 text-muted"><li>A main Windows computer available during trading.</li><li>A private LAN or Wi-Fi network shared with staff workstations.</li><li>Internet for activation, downloads and synchronization; daily operations remain local.</li><li>Owner-approved setup data and a tested backup/recovery arrangement.</li></ul><a href="{{ route('public.setup-kits') }}" class="mt-6 inline-block font-semibold text-accent underline">Get the templates and full checklists</a></div>
  </section>
</div>

<div>
  <section class="border-b border-border bg-background">
    <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
      <x-public.breadcrumbs :items="[['label' => 'Setup kits']]" />
      <p class="mt-8 text-sm font-semibold uppercase text-accent">Prepare once. Import with confidence.</p>
      <h1 class="mt-4 text-4xl font-bold text-foreground sm:text-5xl">Your business setup kit.</h1>
      <p class="mt-5 max-w-3xl text-lg leading-8 text-muted">Give the owner one ZIP with blank CSV templates, separate examples, field guides and installation checklists. Prepare the data before setting up the branch.</p>
      @if ($pack)
        <x-desktop-downloads class="mt-6 justify-start" />
        <p class="mt-3 text-sm text-muted">{{ count($pack['datasets']) + count($pack['productionDatasets'] ?? []) }} template sets | CSV schema {{ $pack['schemaVersion'] }} | {{ number_format($pack['bytes'] / 1024, 1) }} KB | Templates from Server {{ $pack['sourceApplicationVersion'] }}</p>
      @else
        <p class="mt-6 rounded-md border border-border p-4 text-muted" role="status">The setup pack is temporarily unavailable. Please check again shortly.</p>
      @endif
      <p class="mt-4 max-w-3xl text-sm leading-7 text-muted">Use a Business Server build with Admin | Data Setup &amp; Transfers. Import the completed files locally on that Server. Each Windows x64 application download is a ZIP containing its installer, requirements, setup instructions and templates. See the deployment guide for current release versions, beta/stable status and other computer architectures.</p>
      <a href="{{ route('public.deployment') }}" class="mt-3 inline-block font-semibold text-accent underline">Installation and upgrade guide</a>
    </div>
  </section>
  <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8" aria-labelledby="templates-heading">
    <h2 id="templates-heading" class="text-2xl font-bold">What's in the pack</h2>
    <p class="mt-3 text-muted">Business templates, fictional examples and guides are included. Production templates and examples are grouped in the production folder.</p>
    @if ($pack)
      <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($pack['datasets'] as $dataset)
          <details class="rounded-lg border border-border bg-surface p-5">
            <summary class="cursor-pointer font-semibold">{{ $dataset['label'] }} @if ($dataset['optionalServiceModule'])<span class="block text-xs font-normal text-muted">Requires the services module</span>@endif</summary>
            <p class="mt-3 text-xs text-muted">{{ $dataset['key'] }}-v1-template.csv</p>
            <ul class="mt-3 space-y-2 text-sm">@foreach ($dataset['columns'] as $column)<li><span class="font-mono">{{ $column['key'] }}</span> <span class="text-muted">{{ ($column['required'] ?? false) ? 'Required' : 'Optional' }}</span></li>@endforeach</ul>
          </details>
        @endforeach
        @foreach ($pack['productionDatasets'] ?? [] as $productionDataset)
          <div class="rounded-lg border border-border bg-surface p-4"><h3 class="font-semibold">Production: {{ str($productionDataset['key'])->headline() }}</h3><p class="mt-1 text-sm text-muted">Template, example and owner setup guide included.</p></div>
        @endforeach
      </div>
    @endif
  </section>
  <section class="border-y border-border bg-surface">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-2 lg:px-8">
      <div><h2 class="text-2xl font-bold">Fill, validate, review, import</h2><ol class="mt-5 list-decimal space-y-3 pl-5 leading-7 text-muted"><li>Start with categories, brands, units and warehouses, then products, customers and suppliers.</li><li>Keep identifiers as text, use reference codes and YYYY-MM-DD dates. Replace example dates with real document dates.</li><li>Upload related files together in Data Setup &amp; Transfers. Review the preview and correct any row errors.</li><li>Confirm the import. Opening stock and debts require approval and a backup. Purchase and stock-count drafts require a separate posting action.</li></ol></div>
      <div><h2 class="text-2xl font-bold">Know the limits</h2><ul class="mt-5 list-disc space-y-3 pl-5 leading-7 text-muted"><li>General data setup: up to 30 files, 10,000 rows and 8 MiB combined per batch.</li><li>Production setup: use the Production CSV panel; up to 500 rows per preview.</li><li>Examples are fictional. Fill the blank templates with the owner's data.</li><li>Staff, bank/cash configuration and ownership imports are outside this pack?s supported import scope.</li><li>Adding a workstation or upgrading an installation does not require importing opening data again.</li><li>Keep completed owner files private and reconcile the imported totals before trading.</li></ul></div>
    </div>
  </section>
</div>

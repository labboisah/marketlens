<div class="flex flex-col gap-6">
  <section class="rounded-lg border border-border bg-surface p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <p class="text-sm font-semibold uppercase text-muted">Platform observability</p>
        <h1 class="mt-2 text-2xl font-bold">System Health</h1>
        <p class="mt-2 max-w-3xl text-sm text-muted">Restricted operational status for Platform administrators. Secrets, credentials, authorization headers, device credentials, and sensitive customer data are intentionally omitted.</p>
      </div>
      <span class="rounded-md border px-3 py-2 text-sm font-semibold {{ $snapshot['status'] === 'healthy' ? 'border-success/40 text-success' : ($snapshot['status'] === 'degraded' ? 'border-warning/40 text-warning' : 'border-danger/40 text-danger') }}">
        {{ str($snapshot['status'])->title() }}
      </span>
    </div>
  </section>

  <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach ($snapshot['checks'] as $name => $check)
      <article class="rounded-lg border border-border bg-surface p-4">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="font-semibold">{{ str($name)->replace('_', ' ')->title() }}</h2>
            <p class="mt-1 text-sm text-muted">{{ $check['message'] }}</p>
          </div>
          <span class="rounded-md border px-2 py-1 text-xs font-semibold {{ $check['status'] === 'healthy' ? 'border-success/40 text-success' : ($check['status'] === 'degraded' ? 'border-warning/40 text-warning' : 'border-danger/40 text-danger') }}">
            {{ str($check['status'])->title() }}
          </span>
        </div>

        @if (! empty($check['metrics']))
          <dl class="mt-4 grid gap-2 text-sm">
            @foreach ($check['metrics'] as $metric => $value)
              <div class="flex items-center justify-between gap-3 border-t border-border pt-2">
                <dt class="text-muted">{{ str($metric)->replace('_', ' ')->title() }}</dt>
                <dd class="max-w-44 truncate text-right font-mono text-xs text-foreground">{{ is_bool($value) ? ($value ? 'yes' : 'no') : ($value ?? 'none') }}</dd>
              </div>
            @endforeach
          </dl>
        @endif
      </article>
    @endforeach
  </section>

  <section class="rounded-lg border border-border bg-surface p-4">
    <h2 class="font-semibold">Monitoring Endpoints</h2>
    <div class="mt-4 grid gap-3 md:grid-cols-2">
      <div class="rounded-md border border-border bg-background p-3">
        <p class="text-sm font-semibold">Liveness</p>
        <p class="mt-1 font-mono text-xs text-muted">{{ route('health.live') }}</p>
      </div>
      <div class="rounded-md border border-border bg-background p-3">
        <p class="text-sm font-semibold">Readiness</p>
        <p class="mt-1 font-mono text-xs text-muted">{{ route('health.ready') }}</p>
      </div>
    </div>
  </section>
</div>

@props(['shell'])

@php
  $business = $shell['business'];
  $branch = $shell['branch'];
  $subscription = $shell['subscription'];
  $dateRange = session('platform.date_range', 'today');
@endphp

<header class="ml-top-nav sticky top-0 z-30 border-b border-border bg-background/95 backdrop-blur">
  <div class="flex min-h-16 flex-col gap-3 px-4 py-3 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between gap-4">
      <div class="flex min-w-0 items-center gap-3">
        <button type="button" class="rounded-md p-2 text-muted hover:bg-surface-muted hover:text-foreground focus:outline-none focus:ring-2 focus:ring-primary lg:hidden" x-on:click="sidebarOpen = true" aria-label="Open navigation">
          <span aria-hidden="true">☰</span>
        </button>
        <div class="min-w-0">
          <p class="truncate text-sm font-semibold text-foreground">{{ $business?->name ?? 'Cloud Platform' }}</p>
          <p class="truncate text-xs text-muted">{{ $branch?->name ?? 'No branch selected' }}</p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <x-app.status-badge tone="information">{{ $subscription?->plan?->name ?? 'No plan' }}</x-app.status-badge>
        <x-app.status-badge tone="warning">Sync stale</x-app.status-badge>
        <a href="#support" class="hidden rounded-md border border-border px-3 py-2 text-sm font-medium text-foreground hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary sm:inline-flex">Support</a>
        <button type="button" class="rounded-md border border-border px-3 py-2 text-sm font-medium" x-on:click="toggleTheme" aria-label="Toggle theme">
          Theme
        </button>
      </div>
    </div>

    <div class="grid gap-2 xl:grid-cols-[1.4fr_1fr_auto]">
      <form method="POST" action="{{ route('platform.context.update') }}" class="grid gap-2 md:grid-cols-3">
        @csrf
        <label class="sr-only" for="platform-business-selector">Business</label>
        <select id="platform-business-selector" name="business_id" class="h-10 rounded-md border border-border bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary" onchange="this.form.submit()">
          @foreach ($shell['businesses'] as $option)
            <option value="{{ $option->id }}" @selected($business?->is($option))>{{ $option->name }}</option>
          @endforeach
        </select>

        <label class="sr-only" for="platform-branch-selector">Branch</label>
        <select id="platform-branch-selector" name="branch_id" class="h-10 rounded-md border border-border bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary" onchange="this.form.submit()">
          @foreach ($shell['branches'] as $option)
            <option value="{{ $option->id }}" @selected($branch?->is($option))>{{ $option->name }}</option>
          @endforeach
        </select>

        <label class="sr-only" for="platform-date-range">Date range</label>
        <select id="platform-date-range" name="date_range" class="h-10 rounded-md border border-border bg-surface px-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary" onchange="this.form.submit()">
          <option value="today" @selected($dateRange === 'today')>Today</option>
          <option value="7_days" @selected($dateRange === '7_days')>Last 7 days</option>
          <option value="30_days" @selected($dateRange === '30_days')>Last 30 days</option>
          <option value="month" @selected($dateRange === 'month')>This month</option>
        </select>
      </form>

      <form role="search" class="relative">
        <label class="sr-only" for="platform-search">Search</label>
        <input id="platform-search" type="search" placeholder="Search reports, branches, devices" class="h-10 w-full rounded-md border border-border bg-surface px-3 text-sm text-foreground placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-primary">
      </form>

      <div class="flex items-center justify-between gap-2">
        <button type="button" class="rounded-md border border-border px-3 py-2 text-sm font-medium text-foreground hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary">
          Notifications
        </button>
        <div class="text-right text-xs text-muted">
          <p class="font-medium text-foreground">{{ auth()->user()?->name }}</p>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="hover:text-primary">Logout</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</header>

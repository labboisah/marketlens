@php
  $pageNotifications = collect([
      session('notify'),
      session('success') ? ['tone' => 'success', 'title' => session('success')] : null,
      session('warning') ? ['tone' => 'warning', 'title' => session('warning')] : null,
      session('danger') ? ['tone' => 'danger', 'title' => session('danger')] : null,
      session('branch_status') ? ['tone' => 'success', 'title' => session('branch_status')] : null,
      session('device_status') ? ['tone' => 'success', 'title' => session('device_status')] : null,
  ])->filter()->values();
@endphp

<section
  class="pointer-events-none fixed right-4 top-4 z-50 flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-3"
  aria-live="polite"
  aria-atomic="false"
>
  <template x-for="notification in notifications" :key="notification.id">
    <article
      x-show="notification.visible"
      x-transition:enter="motion-safe:transition motion-safe:duration-200"
      x-transition:enter-start="motion-safe:translate-x-3 motion-safe:opacity-0"
      x-transition:enter-end="motion-safe:translate-x-0 motion-safe:opacity-100"
      x-transition:leave="motion-safe:transition motion-safe:duration-150"
      x-transition:leave-start="motion-safe:translate-x-0 motion-safe:opacity-100"
      x-transition:leave-end="motion-safe:translate-x-3 motion-safe:opacity-0"
      class="pointer-events-auto rounded-lg border bg-surface p-4 shadow-md"
      :class="notificationClasses(notification.tone)"
      :role="notification.tone === 'danger' || notification.tone === 'warning' ? 'alert' : 'status'"
    >
      <div class="flex gap-3">
        <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full" :class="notificationIconClasses(notification.tone)">
          <span x-show="notification.tone === 'success'"><x-app.icon name="check-circle" class="h-5 w-5" /></span>
          <span x-show="notification.tone === 'warning'"><x-app.icon name="alert-triangle" class="h-5 w-5" /></span>
          <span x-show="notification.tone === 'danger'"><x-app.icon name="x-circle" class="h-5 w-5" /></span>
          <span x-show="notification.tone === 'information'"><x-app.icon name="info" class="h-5 w-5" /></span>
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold" x-text="notification.title"></p>
          <p class="mt-1 text-sm text-muted" x-show="notification.message" x-text="notification.message"></p>
        </div>
        <button type="button" class="rounded-md p-1 text-muted hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary" x-on:click="dismissNotification(notification.id)" aria-label="Dismiss notification">
          <x-app.icon name="x" class="h-4 w-4" />
        </button>
      </div>
    </article>
  </template>
</section>

@foreach ($pageNotifications as $notification)
  <span x-init="$nextTick(() => notify(@js($notification)))" class="hidden"></span>
@endforeach


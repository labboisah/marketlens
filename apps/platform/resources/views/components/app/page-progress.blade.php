<div
  x-data="{ visible: false, active: false, start() { this.visible = true; this.active = true }, finish() { this.active = false; setTimeout(() => this.visible = false, 220) } }"
  x-on:marketlens:page-progress-start.window="start()"
  x-on:marketlens:page-progress-finish.window="finish()"
  x-show="visible"
  x-cloak
  class="pointer-events-none absolute inset-x-0 bottom-[-1px] z-10 h-1 bg-transparent"
  role="progressbar"
  aria-label="Loading page"
  aria-valuetext="Loading"
>
  <div
    class="ml-linear-progress h-full"
    x-bind:class="active ? 'is-active' : 'is-complete'"
  ></div>
</div>

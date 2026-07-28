@props(['state' => 'synced', 'lastSyncedAt' => null])

@php
  $config = [
    'synced' => ['tone' => 'success', 'label' => 'Synced'],
    'syncing' => ['tone' => 'syncing', 'label' => 'Syncing'],
    'delayed' => ['tone' => 'warning', 'label' => 'Sync delayed'],
    'failed' => ['tone' => 'danger', 'label' => 'Sync failed'],
    'offline' => ['tone' => 'offline', 'label' => 'Offline'],
  ][$state] ?? ['tone' => 'information', 'label' => 'Sync status'];
@endphp

<x-app.status-badge :tone="$config['tone']" :title="$lastSyncedAt ? 'Last sync: ' . $lastSyncedAt : null">
  {{ $config['label'] }}
</x-app.status-badge>

import * as React from 'react';
import { CheckCircle2, RefreshCw, TriangleAlert } from 'lucide-react';
import { StatusBadge } from './StatusBadge';

export type SynchronizationState = 'synced' | 'syncing' | 'delayed' | 'failed';

export function SynchronizationStatusIndicator({ state, lastSyncedAt }: { state: SynchronizationState; lastSyncedAt?: string }) {
  const config = {
    synced: { tone: 'success' as const, label: 'Synced', Icon: CheckCircle2 },
    syncing: { tone: 'syncing' as const, label: 'Syncing', Icon: RefreshCw },
    delayed: { tone: 'warning' as const, label: 'Sync delayed', Icon: TriangleAlert },
    failed: { tone: 'danger' as const, label: 'Sync failed', Icon: TriangleAlert },
  }[state];
  const Icon = config.Icon;

  return (
    <StatusBadge tone={config.tone} title={lastSyncedAt ? `Last sync: ${lastSyncedAt}` : undefined}>
      <Icon className="h-4 w-4" aria-hidden="true" />
      {config.label}
    </StatusBadge>
  );
}

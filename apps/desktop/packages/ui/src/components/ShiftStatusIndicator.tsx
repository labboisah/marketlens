import * as React from 'react';
import { Clock, LockKeyhole, PlayCircle } from 'lucide-react';
import { StatusBadge } from './StatusBadge';

export type ShiftStatus = 'open' | 'closing' | 'closed';

export function ShiftStatusIndicator({ status }: { status: ShiftStatus }) {
  const config = {
    open: { tone: 'success' as const, label: 'Shift open', Icon: PlayCircle },
    closing: { tone: 'warning' as const, label: 'Closing shift', Icon: Clock },
    closed: { tone: 'neutral' as const, label: 'Shift closed', Icon: LockKeyhole },
  }[status];
  const Icon = config.Icon;

  return (
    <StatusBadge tone={config.tone}>
      <Icon className="h-4 w-4" aria-hidden="true" />
      {config.label}
    </StatusBadge>
  );
}

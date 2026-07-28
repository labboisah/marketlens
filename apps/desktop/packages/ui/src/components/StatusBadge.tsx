import * as React from 'react';
import { cn } from '../lib/cn';

export type StatusTone = 'neutral' | 'success' | 'warning' | 'danger' | 'information' | 'offline' | 'syncing';

const tones: Record<StatusTone, string> = {
  neutral: 'border-border bg-surface-muted text-muted',
  success: 'border-success/30 bg-success/10 text-success',
  warning: 'border-warning/30 bg-warning/10 text-warning',
  danger: 'border-danger/30 bg-danger/10 text-danger',
  information: 'border-information/30 bg-information/10 text-information',
  offline: 'border-warning/30 bg-warning/10 text-warning',
  syncing: 'border-teal/30 bg-teal/10 text-teal',
};

export type StatusBadgeProps = React.HTMLAttributes<HTMLSpanElement> & {
  tone?: StatusTone;
};

export function StatusBadge({ className, tone = 'neutral', ...props }: StatusBadgeProps) {
  return (
    <span
      className={cn('inline-flex items-center gap-1 rounded-sm border px-2 py-1 text-xs font-semibold', tones[tone], className)}
      {...props}
    />
  );
}

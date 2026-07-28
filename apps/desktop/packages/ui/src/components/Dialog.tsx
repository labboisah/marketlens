import * as React from 'react';
import { X } from 'lucide-react';
import { cn } from '../lib/cn';
import { Button } from './Button';

export type DialogProps = {
  open: boolean;
  title: string;
  description?: string;
  children: React.ReactNode;
  onClose: () => void;
  footer?: React.ReactNode;
};

export function Dialog({ open, title, description, children, onClose, footer }: DialogProps) {
  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-primary/40 p-4" role="presentation">
      <section
        className={cn('w-full max-w-lg rounded-lg border border-border bg-surface shadow-md')}
        role="dialog"
        aria-modal="true"
        aria-labelledby="dialog-title"
      >
        <header className="flex items-start justify-between gap-4 border-b border-border px-4 py-3">
          <div>
            <h2 id="dialog-title" className="text-lg font-semibold text-foreground">{title}</h2>
            {description ? <p className="mt-1 text-sm text-muted">{description}</p> : null}
          </div>
          <Button variant="ghost" size="icon" onClick={onClose} aria-label="Close dialog">
            <X className="h-5 w-5" aria-hidden="true" />
          </Button>
        </header>
        <div className="p-4">{children}</div>
        {footer ? <footer className="flex justify-end gap-2 border-t border-border px-4 py-3">{footer}</footer> : null}
      </section>
    </div>
  );
}

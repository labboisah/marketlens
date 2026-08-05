import * as React from 'react';
import { cn } from '../lib/cn';

export type InputProps = React.InputHTMLAttributes<HTMLInputElement> & {
  label?: string;
  error?: string;
};

export const Input = React.forwardRef<HTMLInputElement, InputProps>(function Input({ className, label, error, id, ...props }, ref) {
  const inputId = id ?? React.useId();

  return (
    <label className="grid gap-1.5 text-sm font-medium text-foreground" htmlFor={inputId}>
      {label ? <span>{label}</span> : null}
      <input
        id={inputId}
        ref={ref}
        className={cn(
          'h-10 rounded-md border border-border bg-surface px-3 text-sm text-foreground placeholder:text-muted',
          error && 'border-danger',
          className,
        )}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${inputId}-error` : undefined}
        {...props}
      />
      {error ? <span id={`${inputId}-error`} className="text-xs font-medium text-danger">{error}</span> : null}
    </label>
  );
});

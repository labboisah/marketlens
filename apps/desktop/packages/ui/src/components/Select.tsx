import * as React from 'react';
import { cn } from '../lib/cn';

export type SelectOption = {
  label: string;
  value: string;
};

export type SelectProps = React.SelectHTMLAttributes<HTMLSelectElement> & {
  label?: string;
  options: SelectOption[];
};

export function Select({ className, label, options, id, ...props }: SelectProps) {
  const selectId = id ?? React.useId();

  return (
    <label className="grid gap-1.5 text-sm font-medium text-foreground" htmlFor={selectId}>
      {label ? <span>{label}</span> : null}
      <select
        id={selectId}
        className={cn('h-10 rounded-md border border-border bg-surface px-3 text-sm text-foreground', className)}
        {...props}
      >
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </label>
  );
}

import * as React from 'react';
import { Banknote, CreditCard, Landmark, Wallet } from 'lucide-react';
import { cn } from '../lib/cn';

export type PaymentMethod = 'cash' | 'card' | 'transfer' | 'credit';

const options = [
  { value: 'cash', label: 'Cash', icon: Banknote },
  { value: 'card', label: 'Card', icon: CreditCard },
  { value: 'transfer', label: 'Transfer', icon: Landmark },
  { value: 'credit', label: 'Credit', icon: Wallet },
] satisfies Array<{ value: PaymentMethod; label: string; icon: React.ComponentType<{ className?: string }> }>;

export type PaymentMethodSelectorProps = {
  value: PaymentMethod;
  onChange: (value: PaymentMethod) => void;
};

export function PaymentMethodSelector({ value, onChange }: PaymentMethodSelectorProps) {
  return (
    <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Payment method">
      {options.map((option) => {
        const Icon = option.icon;
        const selected = value === option.value;

        return (
          <button
            key={option.value}
            type="button"
            role="radio"
            aria-checked={selected}
            className={cn(
              'flex h-14 items-center justify-center gap-2 rounded-md border text-sm font-semibold',
              selected ? 'border-accent bg-accent/10 text-accent' : 'border-border bg-surface text-foreground hover:bg-surface-muted',
            )}
            onClick={() => onChange(option.value)}
          >
            <Icon className="h-5 w-5" aria-hidden="true" />
            {option.label}
          </button>
        );
      })}
    </div>
  );
}

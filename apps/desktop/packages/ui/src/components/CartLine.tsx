import * as React from 'react';
import { Minus, Plus, Trash2 } from 'lucide-react';
import { Button } from './Button';

export type CartLineProps = {
  name: string;
  sku?: string;
  quantity: number;
  unitPrice: string;
  total: string;
  onDecrease?: () => void;
  onIncrease?: () => void;
  onRemove?: () => void;
};

export function CartLine({ name, sku, quantity, unitPrice, total, onDecrease, onIncrease, onRemove }: CartLineProps) {
  return (
    <article className="grid grid-cols-[1fr_auto] gap-3 border-b border-border py-3">
      <div className="min-w-0">
        <h3 className="truncate text-sm font-semibold text-foreground">{name}</h3>
        <p className="mt-1 text-xs text-muted">{sku ? `${sku} - ` : ''}{unitPrice}</p>
      </div>
      <div className="text-right">
        <p className="font-mono text-sm font-semibold tabular-nums text-foreground">{total}</p>
        <div className="mt-2 flex items-center justify-end gap-1">
          <Button variant="secondary" size="icon" onClick={onDecrease} aria-label={`Decrease ${name}`}>
            <Minus className="h-4 w-4" aria-hidden="true" />
          </Button>
          <span className="grid h-10 min-w-10 place-items-center rounded-md border border-border px-2 text-sm font-semibold tabular-nums">
            {quantity}
          </span>
          <Button variant="secondary" size="icon" onClick={onIncrease} aria-label={`Increase ${name}`}>
            <Plus className="h-4 w-4" aria-hidden="true" />
          </Button>
          <Button variant="ghost" size="icon" onClick={onRemove} aria-label={`Remove ${name}`}>
            <Trash2 className="h-4 w-4" aria-hidden="true" />
          </Button>
        </div>
      </div>
    </article>
  );
}

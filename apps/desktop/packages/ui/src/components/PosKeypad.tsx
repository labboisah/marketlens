import * as React from 'react';
import { Delete } from 'lucide-react';
import { Button } from './Button';

export type PosKeypadProps = {
  onInput: (value: string) => void;
  onBackspace: () => void;
  onClear: () => void;
};

const keys = ['7', '8', '9', '4', '5', '6', '1', '2', '3', '0', '.', '00'];

export function PosKeypad({ onInput, onBackspace, onClear }: PosKeypadProps) {
  return (
    <div className="grid grid-cols-3 gap-2" aria-label="POS keypad">
      {keys.map((key) => (
        <Button key={key} variant="secondary" size="lg" onClick={() => onInput(key)}>
          {key}
        </Button>
      ))}
      <Button variant="secondary" size="lg" onClick={onBackspace}>
        <Delete className="h-5 w-5" aria-hidden="true" />
        <span className="sr-only">Backspace</span>
      </Button>
      <Button variant="danger" size="lg" onClick={onClear}>
        Clear
      </Button>
    </div>
  );
}

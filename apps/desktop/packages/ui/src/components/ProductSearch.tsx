import * as React from 'react';
import { Search } from 'lucide-react';
import { Input, type InputProps } from './Input';

export const ProductSearch = React.forwardRef<HTMLInputElement, Omit<InputProps, 'type'>>(function ProductSearch(props, ref) {
  return (
    <div className="relative">
      <Search className="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" aria-hidden="true" />
      <Input
        ref={ref}
        type="search"
        className="h-12 pl-10 text-base"
        placeholder="Search products"
        aria-label="Search products"
        {...props}
      />
    </div>
  );
});

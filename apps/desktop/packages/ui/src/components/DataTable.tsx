import * as React from 'react';
import { cn } from '../lib/cn';

export type DataTableColumn<Row> = {
  key: string;
  header: string;
  align?: 'left' | 'right' | 'center';
  render?: (row: Row) => React.ReactNode;
};

export type DataTableProps<Row> = {
  columns: DataTableColumn<Row>[];
  rows: Row[];
  density?: 'compact' | 'comfortable' | 'spacious';
  getRowKey: (row: Row, index: number) => React.Key;
  emptyMessage?: string;
};

const rowDensity = {
  compact: 'h-8',
  comfortable: 'h-11',
  spacious: 'h-14',
};

export function DataTable<Row extends Record<string, unknown>>({
  columns,
  rows,
  density = 'comfortable',
  getRowKey,
  emptyMessage = 'No records found.',
}: DataTableProps<Row>) {
  return (
    <div className="overflow-hidden rounded-lg border border-border bg-surface">
      <table className="w-full border-separate border-spacing-0 text-sm">
        <thead className="bg-surface-muted text-muted">
          <tr>
            {columns.map((column) => (
              <th key={String(column.key)} className={cn('border-b border-border px-3 py-2 font-semibold', column.align === 'right' && 'text-right', column.align === 'center' && 'text-center')}>
                {column.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 ? (
            <tr>
              <td className="px-3 py-8 text-center text-muted" colSpan={columns.length}>{emptyMessage}</td>
            </tr>
          ) : (
            rows.map((row, index) => (
              <tr key={getRowKey(row, index)} className="hover:bg-surface-muted">
                {columns.map((column) => (
                  <td key={String(column.key)} className={cn('border-b border-border px-3', rowDensity[density], column.align === 'right' && 'text-right tabular-nums', column.align === 'center' && 'text-center')}>
                    {column.render ? column.render(row) : String(row[column.key] ?? '')}
                  </td>
                ))}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}

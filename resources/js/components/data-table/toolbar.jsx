import { Button } from '@/components/ui/button';
import { X } from 'lucide-react';
import { DataTableFacetedFilter } from './faceted-filter';
import { DataTableViewOptions } from './view-options';
import { Input } from '@/components/ui/input';

export function DataTableToolbar({ table, searchPlaceholder = 'Filter...', children, filters = [] }) {
  const isFiltered = table.getState().columnFilters.length > 0 || table.getState().globalFilter;
  return (
    <div className="flex w-full items-center justify-between gap-2">
      <div className="flex flex-1 flex-col-reverse items-start gap-2 sm:flex-row sm:items-center">
        {/* Global Search */}
        <Input
          placeholder={searchPlaceholder}
          value={table.getState().globalFilter ?? ''}
          onChange={(event) => table.setGlobalFilter(event.target.value)}
          className="max-w-sm"
        />
        {filters && (
          <div className="flex gap-x-2">
            {filters.map((filter) => {
              const column = table.getColumn(filter.columnId);
              if (!column) return null;
              return <DataTableFacetedFilter key={filter.columnId} column={column} title={filter.title} options={filter.options} />;
            })}
          </div>
        )}
        {isFiltered && (
          <Button
            variant="ghost"
            onClick={() => {
              table.resetColumnFilters();
              table.setGlobalFilter('');
            }}
            className="h-8 px-2 lg:px-3"
          >
            Reset
            <X className="ms-2 h-4 w-4" />
          </Button>
        )}
      </div>
      {children}
      <DataTableViewOptions columns={table.getAllColumns()} />
    </div>
  );
}

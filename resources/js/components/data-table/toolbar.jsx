import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { X } from 'lucide-react';
import { DataTableFacetedFilter } from './faceted-filter';
import { DataTableViewOptions } from './view-options';
import { DateRangePicker } from '@/components/ui/date-range-picker';

export function DataTableToolbar({ table, searchPlaceholder = 'Filter...', children, filters = [], resetTrigger, setResetTrigger }) {
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
        <DateRangePicker
          onUpdate={({ range: { from, to } }) => {
            const currentFilters = table.getState().columnFilters;
            const otherFilters = currentFilters.filter((filter) => filter.id !== 'from_date' && filter.id !== 'to_date');
            const newFilters = [...otherFilters, { id: 'from_date', value: from.toISOString() }, { id: 'to_date', value: to.toISOString() }];
            table.setColumnFilters(newFilters);
          }}
          isReset={resetTrigger}
          align="start"
          locale="en-US"
          showCompare={false}
        />
        {isFiltered && (
          <Button
            variant="destructive"
            onClick={() => {
              table.resetColumnFilters();
              table.setGlobalFilter('');
              setResetTrigger(true);
            }}
            className="px-2 lg:px-3 gap-1"
          >
            Reset
            <X className="h-4 w-4" />
          </Button>
        )}
      </div>
      {children}
      <DataTableViewOptions columns={table.getAllColumns()} />
    </div>
  );
}

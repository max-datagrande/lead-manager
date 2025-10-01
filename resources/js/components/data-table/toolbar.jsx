import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { X } from 'lucide-react';
import { useState } from 'react';
import { DataTableFacetedFilter } from './faceted-filter';
import { DataTableViewOptions } from './view-options';

export function DataTableToolbar({
  table,
  searchPlaceholder = 'Filter...',
  config = {
    filters: [],
    dateRange: { column: 'created_at', label: 'Created At' },
  },
}) {
  const {filters, dateRange} = config;
  const isFiltered = table.getState().columnFilters.length > 0 || table.getState().globalFilter;
  const [reset, setReset] = useState(false);
  return (
    <div className="flex w-full flex-col gap-2">
      <div className="flex w-full flex-1 flex-col-reverse items-start gap-2 sm:flex-row sm:items-center">
        {/* Global Search */}
        <div className="flex flex-col gap-2">
          <Input
            placeholder={searchPlaceholder}
            value={table.getState().globalFilter ?? ''}
            onChange={(event) => table.setGlobalFilter(event.target.value)}
            className="w-full max-w-sm"
            id="globalSearch"
          />
        </div>
        <div className="ml-auto flex flex-col gap-1">
          <Label className="mr-2 text-right text-sm">{dateRange.label}</Label>
          <DateRangePicker
            onUpdate={({ range: { from, to } }) => {
              const currentFilters = table.getState().columnFilters;
              const otherFilters = currentFilters.filter((filter) => filter.id !== 'from_date' && filter.id !== 'to_date');
              // Si es el mismo día, ajustar 'to' al final del día
              const adjustedTo =
                from.toDateString() === to.toDateString()
                  ? new Date(to.getTime() + 24 * 60 * 60 * 1000 - 1) // Agregar 24 horas menos 1ms
                  : to;

              const newFilters = [
                ...otherFilters,
                { id: 'from_date', value: from.toISOString() },
                { id: 'to_date', value: adjustedTo.toISOString() },
              ];
              table.setColumnFilters(newFilters);
            }}
            isReset={reset}
            align="start"
            locale="en-US"
            showCompare={false}
          />
          <DataTableViewOptions columns={table.getAllColumns()} />
        </div>
      </div>
      <div className="my-3 flex w-full gap-2">
        {filters.length > 0 && (
          <>
            <span className="flex items-center">Filters:</span>
            <div className="flex gap-x-2">
              {filters.map((filter) => {
                const column = table.getColumn(filter.columnId);
                if (!column) return null;
                return <DataTableFacetedFilter key={filter.columnId} column={column} title={filter.title} options={filter.options} />;
              })}
            </div>
          </>
        )}
        {isFiltered && (
          <Button
            variant="destructive"
            onClick={() => {
              table.resetColumnFilters();
              table.setGlobalFilter('');
              setReset(true);
            }}
            className="ml-auto gap-1 px-2 lg:px-3"
          >
            Reset
            <X className="h-4 w-4" />
          </Button>
        )}
      </div>
    </div>
  );
}

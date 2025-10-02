import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Filter, Search, X } from 'lucide-react';
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
  const { filters, dateRange } = config;
  const isFiltered = table.getState().columnFilters.length > 0 || table.getState().globalFilter;
  const [reset, setReset] = useState(false);

  return (
    <div className="w-full space-y-4">
      {/* Main toolbar row */}
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        {/* Left section: Search */}
        <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:gap-4 flex-1">
          <div className="flex flex-col gap-1.5 w-full">
            <Label htmlFor="globalSearch" className="flex items-center gap-1.5 text-sm font-medium text-muted-foreground">
              <Search className="h-4 w-4" />
              Global Search
            </Label>
            <Input
              placeholder={searchPlaceholder}
              value={table.getState().globalFilter ?? ''}
              onChange={(event) => table.setGlobalFilter(event.target.value)}
              className="w-full md:min-w-[280px] md:max-w-md"
              id="globalSearch"
            />
          </div>
        </div>

        {/* Right section: Date Range */}
        <div className="flex flex-col gap-1.5">
          <Label className="flex items-center gap-1.5 text-sm font-medium text-muted-foreground">
            <Filter className="h-4 w-4" />
            {dateRange.label}
          </Label>
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
            align="end"
            locale="en-US"
            showCompare={false}
          />
        </div>
      </div>

      {/* Separator */}
      {(filters.length > 0 || isFiltered) && <Separator />}

      {/* Filters and Actions row */}
      {(filters.length > 0 || isFiltered) && (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          {/* Filters section */}
          {filters.length > 0 && (
            <div className="flex flex-wrap items-center gap-2">
              <span className="flex items-center gap-1.5 text-sm font-medium text-muted-foreground">
                <Filter className="h-4 w-4" />
                Filters:
              </span>
              <div className="flex flex-wrap gap-2">
                {filters.map((filter) => {
                  const column = table.getColumn(filter.columnId);
                  if (!column) return null;
                  return <DataTableFacetedFilter key={filter.columnId} column={column} title={filter.title} options={filter.options} />;
                })}
              </div>
            </div>
          )}

          {/* Actions section */}
          <div className="flex items-center gap-2 justify-end">
            {isFiltered && (
              <>
                <Button
                  variant="destructive"
                  size="sm"
                  onClick={() => {
                    table.resetColumnFilters();
                    table.setGlobalFilter('');
                    setReset(true);
                  }}
                  className="h-8 gap-1.5 px-2.5"
                >
                  <X className="h-3.5 w-3.5" />
                  Clear Filters
                </Button>
                <Separator orientation="vertical" className="h-6" />
              </>
            )}
            <div className="flex items-center gap-1.5">
              <DataTableViewOptions columns={table.getAllColumns()} />
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

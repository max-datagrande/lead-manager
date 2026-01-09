import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Filter, Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';
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
  const [globalSearch, setGlobalSearch] = useState('');
  // Extraer valores iniciales de fecha de los filtros existentes
  const currentFilters = table.getState().columnFilters;
  const fromDateFilter = currentFilters.find((filter) => filter.id === 'from_date');
  const toDateFilter = currentFilters.find((filter) => filter.id === 'to_date');

  const initialDateFrom = fromDateFilter ? new Date(fromDateFilter.value) : undefined;
  const initialDateTo = toDateFilter ? new Date(toDateFilter.value) : undefined;

  const handleSearch = (event) => {
    setGlobalSearch(event.target.value);
  };
  useEffect(() => {
    let timeout = setTimeout(() => {
      table.setGlobalFilter(globalSearch);
    }, 100);
    return () => clearTimeout(timeout);
  }, [globalSearch]);

  return (
    <div className="w-full space-y-4">
      {/* Main toolbar row */}
      <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        {/* Left section: Search */}
        <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:items-end sm:gap-4">
          <div className="flex w-full flex-col gap-1.5">
            <Label htmlFor="globalSearch" className="flex items-center gap-1.5 text-sm font-medium text-muted-foreground">
              <Search className="h-4 w-4" />
              Global Search
            </Label>
            <Input
              placeholder={searchPlaceholder}
              value={globalSearch}
              onChange={handleSearch}
              className="w-full md:max-w-md md:min-w-[280px]"
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
            initialDateFrom={initialDateFrom}
            initialDateTo={initialDateTo}
            onUpdate={({ range: { from, to } }) => {
              const currentFilters = table.getState().columnFilters;
              const otherFilters = currentFilters.filter((filter) => filter.id !== 'from_date' && filter.id !== 'to_date');
              const hasValidValues = from && to;
              table.setColumnFilters(
                !hasValidValues
                  ? otherFilters
                  : [...otherFilters, { id: 'from_date', value: from.toISOString() }, { id: 'to_date', value: to.toISOString() }],
              );
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
          <div className="flex items-center justify-end gap-2">
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

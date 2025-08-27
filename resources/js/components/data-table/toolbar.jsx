import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Input } from '@/components/ui/input';
import { X } from 'lucide-react';
import { DataTableFacetedFilter } from './faceted-filter';
import { DataTableViewOptions } from './view-options';

export function DataTableToolbar({ table, searchPlaceholder = 'Filter...', filters = [], resetTrigger, setResetTrigger }) {
  const isFiltered = table.getState().columnFilters.length > 0 || table.getState().globalFilter;
  return (
    <div className="flex w-full flex-col gap-2">
      <div className="flex w-full flex-1 flex-col-reverse items-start gap-2 sm:flex-row sm:items-center">
        {/* Global Search */}
        <Input
          placeholder={searchPlaceholder}
          value={table.getState().globalFilter ?? ''}
          onChange={(event) => table.setGlobalFilter(event.target.value)}
          className="w-full max-w-sm"
        />
        <div className="ml-auto flex gap-2">
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
          <DataTableViewOptions columns={table.getAllColumns()} />
        </div>
      </div>
      <div className="flex w-full gap-2 my-3">
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
              setResetTrigger(true);
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

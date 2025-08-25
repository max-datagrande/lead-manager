import { useDebouncedFunction } from '@/hooks/use-debounce';
import { getSortState, serializeSort } from '@/utils/table';
import { router, usePage } from '@inertiajs/react';
import { createContext, useRef, useState } from 'react';
import { route } from 'ziggy-js';

export const VisitorsContext = createContext(null);

export function VisitorsProvider({ children }) {
  const { state } = usePage().props;
  const filters = state.filters ?? [];
  const [currentRow, setCurrentRow] = useState(null);
  const [globalFilter, setGlobalFilter] = useState(state.search ?? '');
  const [sorting, setSorting] = useState(state.sort ? getSortState(state.sort) : []);
  const [columnFilters, setColumnFilters] = useState(filters);
  const isFirstRender = useRef(true);

  const setFilter = (id, value) => {
    setColumnFilters((prev) => {
      const others = prev.filter((f) => f.id !== id);
      return value ? [...others, { id, value }] : others;
    });
  };

  const handleClearFilters = () => {
    setColumnFilters([]);
  };

  const getVisitors = useDebouncedFunction((newData) => {
    if (isFirstRender.current) {
      isFirstRender.current = false;
      return;
    }
    const data = {
      search: globalFilter || undefined,
      sort: serializeSort(sorting),
      filters: JSON.stringify(columnFilters || []),
      ...newData,
    };
    const url = route('visitors.index');
    const options = { only: ['rows', 'meta', 'state'], replace: true, preserveState: true, preserveScroll: true };
    router.get(url, data, options);
  }, 300);

  const contextValue = {
    getVisitors,
    setFilter,
    handleClearFilters,
    columnFilters,
    setColumnFilters,
    sorting,
    setSorting,
    isFirstRender,
    globalFilter,
    setGlobalFilter,
    open,
    currentRow,
    setCurrentRow,
  };

  return <VisitorsContext.Provider value={contextValue}>{children}</VisitorsContext.Provider>;
}

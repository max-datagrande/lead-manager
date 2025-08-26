import { useDebouncedFunction } from '@/hooks/use-debounce';
import { getSortState, serializeSort } from '@/utils/table';
import { router, usePage } from '@inertiajs/react';
import { createContext, useCallback, useRef, useState } from 'react';
import { route } from 'ziggy-js';

export const VisitorsContext = createContext(null);

export function VisitorsProvider({ children }) {
  const { state } = usePage().props;
  const filters = state.filters ?? [];
  const [currentRow, setCurrentRow] = useState(null);
  const [resetTrigger, setResetTrigger] = useState(false);
  const [globalFilter, setGlobalFilter] = useState(state.search ?? '');
  const [sorting, setSorting] = useState(state.sort ? getSortState(state.sort) : []);
  const [columnFilters, setColumnFilters] = useState(filters);
  const [isLoading, setIsLoading] = useState(false);
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

  const getVisitors = useDebouncedFunction(
    useCallback((newData) => {
      if (isFirstRender.current) {
        isFirstRender.current = false;
        return;
      }
      setIsLoading(true);
      const data = {
        search: globalFilter || undefined,
        sort: serializeSort(sorting),
        filters: JSON.stringify(columnFilters || []),
        ...newData,
      };
      const url = route('visitors.index');
      const options = {
        only: ['rows', 'meta', 'state'],
        replace: true,
        preserveState: true,
        preserveScroll: true,
        onFinish: () => setIsLoading(false),
      };
      router.get(url, data, options);
    }, [sorting, columnFilters, globalFilter]),
    200,
  );

  return (
    <VisitorsContext.Provider
      value={{
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
        currentRow,
        setCurrentRow,
        resetTrigger,
        setResetTrigger,
        isLoading,
      }}
    >
      {children}
    </VisitorsContext.Provider>
  );
}

import { useDebouncedFunction } from '@/hooks/use-debounce';
import { getSortState, serializeSort } from '@/utils/table';
import { router } from '@inertiajs/react';
import { createContext, useCallback, useRef, useState } from 'react';
import { route } from 'ziggy-js';

export const VisitorsContext = createContext(null);

export function VisitorsProvider({ children, initialState }) {
  const inistialSorting = typeof initialState?.sort === 'string' ? getSortState(initialState.sort) : [];
  const filters = initialState.filters ?? [];
  const [currentRow, setCurrentRow] = useState(null);
  const [resetTrigger, setResetTrigger] = useState(false);
  const [globalFilter, setGlobalFilter] = useState(initialState.search ?? '');
  const [sorting, setSorting] = useState(inistialSorting);
  const [columnFilters, setColumnFilters] = useState(filters);
  const [isLoading, setIsLoading] = useState(false);
  const [pagination, setPagination] = useState({
    pageIndex: (initialState.page ?? 1) - 1,
    pageSize: initialState.per_page ?? 10,
  });
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
    useCallback(
      (newData) => {
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
      },
      [sorting, columnFilters, globalFilter],
    ),
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
        pagination,
        setPagination,
      }}
    >
      {children}
    </VisitorsContext.Provider>
  );
}

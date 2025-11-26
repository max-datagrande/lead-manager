import { useDebouncedFunction } from '@/hooks/use-debounce';
import { getSortState, serializeSort } from '@/utils/table';
import { router, usePage } from '@inertiajs/react';
import { createContext, useCallback, useRef, useState } from 'react';
import { route } from 'ziggy-js';

export const OfferwallConversionsContext = createContext(null);

export const OfferwallConversionsProvider = ({ children, initialState }) => {
  const { props } = usePage();
  const { state = {}, filters = {} } = props; // filters from backend for column filters

  const isFirstRender = useRef(true);
  const [isLoading, setIsLoading] = useState(false);

  const inistialSorting = typeof initialState?.sort !== 'function' ? 'created_at:desc' : getSortState(initialState?.sort);

  const [globalFilter, setGlobalFilter] = useState(state?.search ?? '');
  const [sorting, setSorting] = useState(inistialSorting);
  const [columnFilters, setColumnFilters] = useState(filters?.columnFilters ?? []); // Assuming backend sends columnFilters

  const updateConversions = useCallback(
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
        ...newData, // For pagination or other explicit data
      };

      const url = route('offerwall.conversions');
      const options = {
        only: ['rows', 'meta', 'state', 'filters'], // Request filters back from backend
        replace: true,
        preserveState: true,
        preserveScroll: true,
        onFinish: () => setIsLoading(false),
      };
      router.get(url, data, options);
    },
    [sorting, columnFilters, globalFilter],
  );

  const getConversions = useDebouncedFunction(updateConversions, 200);

  const handleClearFilters = useCallback(() => {
    setGlobalFilter('');
    setSorting([]);
    setColumnFilters([]);
    // Trigger a fetch after clearing filters
    getConversions({ page: 1 });
  }, [getConversions]);

  const value = {
    getConversions,
    handleClearFilters,
    columnFilters,
    setColumnFilters,
    sorting,
    setSorting,
    globalFilter,
    setGlobalFilter,
    isLoading,
  };

  return <OfferwallConversionsContext.Provider value={value}>{children}</OfferwallConversionsContext.Provider>;
};

import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { getSortState, serializeSort } from '@/utils/table';

interface ServerTableOptions {
  routeName: string;
  initialState: any;
  defaultPageSize?: number;
  includeInReload?: string[];
}

interface ServerTableState {
  sorting: any[];
  setSorting: (sorting: any[]) => void;
  columnFilters: any[];
  setColumnFilters: (filters: any[]) => void;
  globalFilter: string;
  setGlobalFilter: (filter: string) => void;
  pagination: { pageIndex: number; pageSize: number };
  setPagination: (pagination: { pageIndex: number; pageSize: number }) => void;
  isLoading: boolean;
  fetchData: (overrides?: any) => void;
  resetPagination: () => void;
}

export function useServerTable({ routeName, initialState, defaultPageSize = 10, includeInReload }: ServerTableOptions): ServerTableState {
  const isFirstRender = useRef(true);

  // Estado inicial
  const initialSorting = typeof initialState?.sort === 'string' ? getSortState(initialState.sort) : [];
  const filters = initialState.filters ?? [];

  // Estados
  const [sorting, setSorting] = useState(initialSorting);
  const [columnFilters, setColumnFilters] = useState(filters);
  const [globalFilter, setGlobalFilter] = useState(initialState.search ?? '');
  const [isLoading, setIsLoading] = useState(false);
  const [pagination, setPagination] = useState({
    pageIndex: (initialState.page ?? 1) - 1,
    pageSize: initialState.per_page ?? defaultPageSize,
  });

  // Resetear paginación
  const resetPagination = () => {
    setPagination((prev) => ({ ...prev, pageIndex: 0 }));
  };

  // Fetch data
  const fetchData = (overrides: any = {}) => {
    if (isFirstRender.current) {
      isFirstRender.current = false;
      return;
    }

    setIsLoading(true);

    const payload = {
      search: globalFilter || undefined,
      sort: serializeSort(sorting),
      filters: JSON.stringify(columnFilters || []),
      page: pagination.pageIndex + 1,
      per_page: pagination.pageSize,
      ...overrides,
    };

    const options = {
      only: ['rows', 'meta', 'state', ...includeInReload],
      replace: true,
      preserveState: true,
      preserveScroll: true,
      onFinish: () => setIsLoading(false),
    };

    router.get(route(routeName), payload, options);
  };

  // Efecto principal: cuando cambian filtros o sorting, resetear página y fetch
  useEffect(() => {
    if (pagination.pageIndex > 0) {
      resetPagination();
      return;
    }
    fetchData();
  }, [sorting, columnFilters, globalFilter]);

  // Efecto: cuando cambia paginación, fetch
  useEffect(() => {
    fetchData();
  }, [pagination.pageIndex, pagination.pageSize]);

  return {
    sorting,
    setSorting,
    columnFilters,
    setColumnFilters,
    globalFilter,
    setGlobalFilter,
    pagination,
    setPagination,
    isLoading,
    fetchData,
    resetPagination,
  };
}

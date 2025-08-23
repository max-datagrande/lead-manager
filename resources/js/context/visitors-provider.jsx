import { useModal } from '@/hooks/use-modal';
import { getSortState, serializeSort } from '@/utils/table';
import { router, usePage } from '@inertiajs/react';
import { createContext, useRef, useState, useContext } from 'react';
import { route } from 'ziggy-js';

const VisitorsContext = createContext(null);

export function VisitorsProvider({ children }) {
  const { open } = useModal();
  const [currentRow, setCurrentRow] = useState(null);
  const { state } = usePage().props;
  const filters = state.filters ?? [];
  const [globalFilter, setGlobalFilter] = useState(state.search ?? '');
  const [sorting, setSorting] = useState(state.sort ? getSortState(state.sort) : []);
  const [columnFilters, setColumnFilters] = useState(filters);
  const firstRender = useRef(true);

  const setFilter = (id, value) => {
    setColumnFilters((prev) => {
      const others = prev.filter((f) => f.id !== id);
      return value ? [...others, { id, value }] : others;
    });
  };
  // Función para limpiar todos los filtros
  const handleClearFilters = () => {
    setColumnFilters([]);
  };
  const getVisitors = (newData) => {
    console.log('Ejecutando');
    if (firstRender.current) {
      firstRender.current = false;
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
  };
  const contextValue = {
    getVisitors,
    setFilter,
    handleClearFilters,
    columnFilters,
    setColumnFilters,
    sorting,
    setSorting,
    firstRender,
    globalFilter,
    setGlobalFilter,
    open,
    currentRow,
    setCurrentRow,
  };
  return <VisitorsContext value={contextValue}>{children}</VisitorsContext>;
}

export const useVisitors = () => {
  const visitorsContext = useContext(VisitorsContext);

  if (!visitorsContext) {
    throw new Error('useVisitors has to be used within <VisitorsContext>');
  }
  return visitorsContext;
};

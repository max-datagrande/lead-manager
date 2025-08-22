import PageHeader from '@/components/page-header';
import { TableVisitors } from '@/components/visitors';
import AppLayout from '@/layouts/app-layout';
import { getSortState, serializeSort } from '@/utils/table';
import { Head, router, usePage } from '@inertiajs/react';
import { createContext, useRef, useState } from 'react';
import { route } from 'ziggy-js';

const breadcrumbs = [
  {
    title: 'Visitors',
    href: route('visitors.index'),
  },
];

// Exportar el contexto
export const VisitorsContext = createContext({});
/**
 * Index Page Component
 *
 * @description Página principal para mostrar visitantes con tabla paginada
 */
const Index = () => {
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
        firstRender,
        globalFilter,
        setGlobalFilter,
      }}
    >
      <Head title="Visitors" />
      <div className="slide-in-up relative flex-1 space-y-6 overflow-auto p-6 md:p-8">
        <PageHeader title="Visitors" description="Manage visitors from our landing pages." />
        <TableVisitors />
      </div>
    </VisitorsContext.Provider>
  );
};

Index.layout = (page) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

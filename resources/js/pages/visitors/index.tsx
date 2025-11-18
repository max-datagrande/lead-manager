import PageHeader from '@/components/page-header';
import { ServerTable } from '@/components/data-table/server-table';
import { useServerTable } from '@/hooks/use-server-table';
import { visitorColumns } from '@/components/visitors/list-columns';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, DatatablePageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { route } from 'ziggy-js';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Visitors',
    href: route('visitors.index'),
  },
];
/**
 * Index Page Component
 *
 * @description Página principal para mostrar visitantes con tabla paginada
 */
type Visitor = {
  id: string;
  fingerprint: string;
  visit_date: string;
  visit_count: number;
  ip_address: string;
  device_type: string;
  browser: string;
  os: string;
  country_code: string;
  state: string;
  city: string;
  utm_source: string;
  utm_medium: string;
  host: string;
  path_visited: string;
  referrer: string;
  is_bot: boolean;
  created_at: string;
  updated_at: string;
}

interface IndexProps extends DatatablePageProps<Visitor> {
  data: {
    hosts: string[];
    states: string[];
  };
}

const Index = ({ rows, meta, state, data }: IndexProps) => {
  const table = useServerTable({
    routeName: 'visitors.index',
    initialState: state,
    defaultPageSize: 10
  });

  const { hosts = [], states = [] } = data;

  return (
    <>
      <Head title="Visitors" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Visitors" description="Manage visitors from our landing pages." />
        <ServerTable
          data={rows.data}
          columns={visitorColumns}
          meta={meta}
          isLoading={table.isLoading}
          pagination={table.pagination}
          setPagination={table.setPagination}
          sorting={table.sorting}
          setSorting={table.setSorting}
          columnFilters={table.columnFilters}
          setColumnFilters={table.setColumnFilters}
          globalFilter={table.globalFilter}
          setGlobalFilter={table.setGlobalFilter}
          toolbarConfig={{
            searchPlaceholder: 'Search visitors...',
            filters: [
              {
                columnId: 'host',
                title: 'Host',
                options: hosts,
              },
              {
                columnId: 'state',
                title: 'State',
                options: states,
              },
            ],
            dateRange: { column: 'created_at', label: 'Created At' },
          }}
        />
      </div>
    </>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

import { ServerTable } from '@/components/data-table/server-table';
import PageHeader from '@/components/page-header';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';
import { DatatablePageProps, type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { columns } from '@/components/logs/mixes/table'

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Logs',
    href: "/",
  },
  {
    title: 'Offerwall Mixes',
    href: route('logs.offerwall-mixes.index'),
  },
];

// Define interfaces for props
interface OfferwallMixLog {
  id: number;
  offerwall_mix: { name: string };
  origin: string;
  successful_integrations: number;
  failed_integrations: number;
  total_integrations: number;
  total_offers_aggregated: number;
  total_duration_ms: number;
  created_at: string;
}

interface PaginatedLogs {
  data: OfferwallMixLog[];
  links: any[]; // Adjust based on your pagination link structure
  meta: any; // Adjust based on your pagination meta structure
}

interface IndexProps extends PaginatedLogs{
  rows: PaginatedLogs;
  state: {
    sort: string;
    filters: { [key: string]: string };
  }
}

const Index = ({ rows , meta, data, state }: IndexProps ) => {
  const table = useServerTable({
      routeName: 'logs.offerwall-mixes.index',
      initialState: state,
      defaultPageSize: 10,
    });

  return (
    <>
      <Head title="Offerwall Mix Logs" />
      <div className="flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Offerwall Mix Logs" description="Review and debug Offerwall Mix executions.">
          {/* Actions can go here, e.g., filters */}
        </PageHeader>
        <div className="-mx-4 overflow-x-auto px-4 py-4 sm:-mx-8 sm:px-8">
          <ServerTable
            data={rows.data}
            columns={columns}
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
              filters: [],
              dateRange: { column: 'created_at', label: 'Created At' },
            }}
            />
        </div>
      </div>
    </>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;

export default Index;

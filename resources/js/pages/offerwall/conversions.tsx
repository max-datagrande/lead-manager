import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { route } from 'ziggy-js';
import PageHeader from '@/components/page-header';
import { columns } from '@/components/offerwall-conversions/list-columns';
import { OfferwallConversionsProvider } from '@/context/offerwall/conversion-provider';
import { OfferwallConversionsWidgets, TableConversions, OfferwallConversionsActions } from '@/components/offerwall-conversions';
import { ServerTable } from '@/components/data-table/server-table';
import { useServerTable } from '@/hooks/use-server-table';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Offerwalls', href: route('offerwall.index') },
  { title: 'Conversions', href: route('offerwall.conversions') },
];

interface IndexProps {
  rows: any; // The paginator object
  totalPayout: number;
  integrations: Array<{ value: string; label: string }>; // Added
  companies: Array<{ value: string; label: string }>;    // Added
  state: { sort?: string; search?: string };
}

const Index = ({ rows, totalPayout, integrations, companies, state }: IndexProps) => {
  const table = useServerTable({
      routeName: 'offerwall.conversions',
      initialState: state,
      defaultPageSize: 10,
  })

  return (
    <OfferwallConversionsProvider initialState={state}>
      <Head title="Offerwall Conversions" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Offerwall Conversions" description="Review offerwall conversions.">
          <OfferwallConversionsActions />
        </PageHeader>
        <OfferwallConversionsWidgets totalPayout={totalPayout} />
        <ServerTable
          data={rows.data}
          columns={columns}
          meta={{last_page : 5}}
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

            ],
            dateRange: { column: 'created_at', label: 'Created At' },
          }}
        />
      </div>
    </OfferwallConversionsProvider>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

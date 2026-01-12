import { ServerTable } from '@/components/data-table/server-table';
import { OfferwallConversionsActions, OfferwallConversionsWidgets } from '@/components/offerwall-conversions';
import { columns } from '@/components/offerwall-conversions/list-columns';
import PageHeader from '@/components/page-header';
import { OfferwallConversionsProvider } from '@/context/offerwall/conversion-provider';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';
import { DatatablePageProps, type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { router } from '@inertiajs/react';
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Offerwalls', href: route('offerwall.index') },
  { title: 'Conversions', href: route('offerwall.conversions') },
];

type Conversions = {
  id: number;
  integration_id: number;
  company_id: number;
  amount: number;
  fingerprint: string;
  click_id: string;
  utm_source: string;
  utm_medium: string;
  created_at: string;
  updated_at: string;
};
interface IndexProps extends DatatablePageProps<Conversions> {
  totalPayout: number;
  data: {
    companies: Array<{ value: string; label: string }>;
    integrations: Array<{ value: string; label: string }>;
    paths: Array<{ value: string; label: string }>;
    hosts: Array<{ value: string; label: string }>;
    cptypes: Array<{ value: string; label: string }>;
  };
}

const Index = ({ rows, state, meta, data, totalPayout }: IndexProps) => {
  const table = useServerTable({
    routeName: 'offerwall.conversions',
    initialState: state,
    defaultPageSize: 10,
    includeInReload: ['totalPayout'],
  });
  const { isLoading } = table;

  const handleExport = () => {
    /* table.setGlobalFilter('export', true);
    table.reload(); */
  };

  const handleRefresh = () => {
    router.reload();
  };

  return (
    <OfferwallConversionsProvider initialState={state}>
      <Head title="Offerwall Conversions" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Offerwall Conversions" description="Review offerwall conversions.">
          <OfferwallConversionsActions actions={{ export: handleExport, refresh: handleRefresh }} />
        </PageHeader>
        <OfferwallConversionsWidgets totalPayout={totalPayout} isLoading={isLoading} />
        <ServerTable
          data={rows.data}
          columns={columns}
          meta={meta}
          isLoading={isLoading}
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
                columnId: 'integration',
                title: 'Integration',
                options: data.integrations,
              },
              {
                columnId: 'company',
                title: 'Company',
                options: data.companies,
              },
              {
                columnId: 'pathname',
                title: 'Pathname',
                options: data.paths,
              },
              {
                columnId: 'host',
                title: 'Host',
                options: data.hosts,
              },
              {
                columnId: 'cptype',
                title: 'CPType',
                options: data.cptypes,
              }
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

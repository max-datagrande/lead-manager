import { ServerTable } from '@/components/data-table/server-table';
import { OfferwallConversionsActions, OfferwallConversionsWidgets } from '@/components/offerwall-conversions';
import { columns } from '@/components/offerwall-conversions/list-columns';
import PageHeader from '@/components/page-header';
import { OfferwallConversionsProvider } from '@/context/offerwall/conversion-provider';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';
import { DatatablePageProps, type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
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
  placement_id: string;
  state: string;
  zip_code: string;
  company: string; // Add company name for frontend
  buyer: string; // Add buyer company name for frontend
};
interface IndexProps extends DatatablePageProps<Conversions> {
  totalPayout: number;
  data: {
    companies: Array<{ value: string; label: string }>;
    integrations: Array<{ value: string; label: string }>;
    paths: Array<{ value: string; label: string }>;
    hosts: Array<{ value: string; label: string }>;
    cptypes: Array<{ value: string; label: string }>;
    placements: Array<{ value: string; label: string }>;
    states: Array<{ value: string; label: string }>;
    buyerCompanies: Array<{ value: string; label: string }>; // Add buyerCompanies for faceted filter
  };
}

const Index = ({ rows, state, meta, data, totalPayout }: IndexProps) => {
  const table = useServerTable({
    routeName: 'offerwall.conversions',
    initialState: state,
    defaultPageSize: 10,
    includeInReload: ['totalPayout', 'totalConversions'],
  });
  const totalConversions = meta.total;
  const { isLoading } = table;

  const handleExport = () => {
    // Correct way: Access state directly from the hook's return object
    const { columnFilters, globalFilter, sorting } = table;

    const params = new URLSearchParams();

    // Handle global filter (search)
    if (globalFilter) {
      params.append('search', globalFilter);
    }

    // Handle sorting
    if (sorting.length > 0) {
      // Assuming single column sort
      params.append('sort', `${sorting[0].id}:${sorting[0].desc ? 'desc' : 'asc'}`);
    }

    // Handle column filters
    const filterParams: { id: string; value: unknown }[] = [];
    columnFilters.forEach((filter) => {
      if (filter.id && filter.value) {
        filterParams.push({ id: filter.id, value: filter.value });
      }
    });

    if (filterParams.length > 0) {
      params.append('filters', JSON.stringify(filterParams));
    }

    // Detect OS to set the correct CSV delimiter for Excel
    const os = navigator.platform.toUpperCase().indexOf('WIN') > -1 ? 'windows' : 'default';
    params.append('os', os);

    const url = route('offerwall.conversions.report') + '?' + params.toString();

    // Using the dynamic link method for robustness
    const link = document.createElement('a');
    link.href = url;
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
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
        <OfferwallConversionsWidgets totalPayout={totalPayout} totalConversions={totalConversions} isLoading={isLoading} />
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
              },
              {
                columnId: 'placement_id',
                title: 'Placement ID',
                options: data.placements,
              },
              {
                columnId: 'state',
                title: 'State',
                options: data.states,
              },
              {
                columnId: 'buyer', // New Buyer filter
                title: 'Buyer',
                options: data.buyerCompanies,
              },
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

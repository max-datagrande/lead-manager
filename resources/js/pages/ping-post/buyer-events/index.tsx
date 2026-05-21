import { ServerTable } from '@/components/data-table/server-table';
import PageHeader from '@/components/page-header';
import { indexBreadcrumbs } from '@/components/ping-post/buyer-events/breadcrumbs';
import { buyerEventColumns } from '@/components/ping-post/buyer-events/list-columns';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';
import { DatatablePageProps } from '@/types';
import type { BuyerEventRow } from '@/types/buyer-events';
import { Head, router } from '@inertiajs/react';
import { Download, RefreshCw } from 'lucide-react';

type FilterOption = { value: string; label: string };

interface Props extends DatatablePageProps<BuyerEventRow> {
  data: {
    stageOptions: FilterOption[];
    eventTypeOptions: FilterOption[];
    reasonOptions: FilterOption[];
    workflows: { id: number; name: string }[];
    integrations: { id: number; name: string }[];
    companies: { id: number; name: string }[];
  };
}

const BuyerEventsIndex = ({ rows, state, meta, data }: Props) => {
  const table = useServerTable({
    routeName: 'ping-post.buyer-events.index',
    initialState: state,
    defaultPageSize: 50,
  });

  const handleExport = () => {
    const { columnFilters, globalFilter, sorting } = table;
    const params = new URLSearchParams();

    if (globalFilter) params.append('search', globalFilter);
    if (sorting.length > 0) params.append('sort', `${sorting[0].id}:${sorting[0].desc ? 'desc' : 'asc'}`);

    const filterParams = columnFilters.filter((f) => f.id && f.value);
    if (filterParams.length > 0) params.append('filters', JSON.stringify(filterParams));

    const os = navigator.platform.toUpperCase().includes('WIN') ? 'windows' : 'default';
    params.append('os', os);

    const link = document.createElement('a');
    link.href = route('ping-post.buyer-events.export') + '?' + params.toString();
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  return (
    <>
      <Head title="Buyer Events" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Buyer Events" description="Unified view of buyer activity across pre-dispatch, ping and post stages.">
          <div className="flex items-center gap-2">
            <Tooltip>
              <TooltipTrigger asChild>
                <Button variant="outline" size="sm" onClick={() => router.reload()}>
                  <RefreshCw className="mr-1.5 h-4 w-4" />
                  Refresh
                </Button>
              </TooltipTrigger>
              <TooltipContent>Refresh data</TooltipContent>
            </Tooltip>
            <Tooltip>
              <TooltipTrigger asChild>
                <Button variant="outline" size="sm" onClick={handleExport}>
                  <Download className="mr-1.5 h-4 w-4" />
                  Export CSV
                </Button>
              </TooltipTrigger>
              <TooltipContent>Export current filtered data as CSV</TooltipContent>
            </Tooltip>
          </div>
        </PageHeader>
        <ServerTable
          data={rows.data}
          columns={buyerEventColumns}
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
            searchPlaceholder: 'Search dispatch UUID or fingerprint...',
            filters: [
              {
                columnId: 'stage',
                title: 'Stage',
                options: data?.stageOptions ?? [],
              },
              {
                columnId: 'event_type',
                title: 'Event Type',
                options: data?.eventTypeOptions ?? [],
              },
              {
                columnId: 'reason',
                title: 'Reason',
                options: data?.reasonOptions ?? [],
              },
              {
                columnId: 'integration_id',
                title: 'Buyer',
                options: (data?.integrations ?? []).map((i) => ({ value: String(i.id), label: i.name })),
              },
              {
                columnId: 'company_id',
                title: 'Company',
                options: (data?.companies ?? []).map((c) => ({ value: String(c.id), label: c.name })),
              },
              {
                columnId: 'workflow_id',
                title: 'Workflow',
                options: (data?.workflows ?? []).map((w) => ({ value: String(w.id), label: w.name })),
              },
            ],
            dateRange: { column: 'created_at', label: 'Created At' },
          }}
        />
      </div>
    </>
  );
};

BuyerEventsIndex.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={indexBreadcrumbs} />;
export default BuyerEventsIndex;

import { ServerTable } from '@/components/data-table/server-table';
import PageHeader from '@/components/page-header';
import { indexBreadcrumbs } from '@/components/ping-post/dispatches/breadcrumbs';
import { dispatchColumns } from '@/components/ping-post/dispatches/list-columns';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';
import { DatatablePageProps } from '@/types';
import type { LeadDispatch } from '@/types/ping-post';
import { Head, router } from '@inertiajs/react';
import { Download, RefreshCw } from 'lucide-react';
import { useState } from 'react';

interface WorkflowPostback {
  id: number;
  uuid: string;
  name: string;
  base_url: string;
}

type FilterOption = { value: string; label: string };

interface Props extends DatatablePageProps<LeadDispatch> {
  data: {
    statusOptions: FilterOption[];
    strategyOptions: FilterOption[];
    workflows: { id: number; name: string }[];
    integrations: { id: number; name: string }[];
    companies: { id: number; name: string }[];
    utmSources: FilterOption[];
  };
  dispatches_with_executions: string[];
  workflow_postbacks: Record<number, WorkflowPostback[]>;
}

const DispatchesIndex = ({ rows, state, meta, data, dispatches_with_executions, workflow_postbacks }: Props) => {
  const [firedDispatches, setFiredDispatches] = useState<string[]>(dispatches_with_executions);

  const table = useServerTable({
    routeName: 'ping-post.dispatches.index',
    initialState: state,
    defaultPageSize: 50,
    includeInReload: ['dispatches_with_executions', 'workflow_postbacks'],
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
    link.href = route('ping-post.dispatches.report') + '?' + params.toString();
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  return (
    <>
      <Head title="Dispatch Logs" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Dispatch Logs" description="View lead dispatch activity and results.">
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
          columns={dispatchColumns}
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
            searchPlaceholder: 'Search fingerprint, UUID, UTM...',
            filters: [
              {
                columnId: 'workflow_id',
                title: 'Workflow',
                options: (data?.workflows ?? []).map((w) => ({ value: String(w.id), label: w.name })),
              },
              {
                columnId: 'status',
                title: 'Status',
                options: data?.statusOptions ?? [],
              },
              {
                columnId: 'strategy_used',
                title: 'Strategy',
                options: data?.strategyOptions ?? [],
              },
              {
                columnId: 'company_id',
                title: 'Company',
                options: (data?.companies ?? []).map((c) => ({ value: String(c.id), label: c.name })),
              },
              {
                columnId: 'winner_integration_id',
                title: 'Integration',
                options: (data?.integrations ?? []).map((i) => ({ value: String(i.id), label: i.name })),
              },
              {
                columnId: 'utm_source',
                title: 'UTM Source',
                options: data?.utmSources ?? [],
              },
            ],
            dateRange: { column: 'created_at', label: 'Created At' },
          }}
          contextFunctions={{
            workflowPostbacks: workflow_postbacks,
            firedDispatches,
            markAsFired: (uuid: string) => setFiredDispatches((prev) => [...prev, uuid]),
          }}
        />
      </div>
    </>
  );
};

DispatchesIndex.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={indexBreadcrumbs} />;
export default DispatchesIndex;

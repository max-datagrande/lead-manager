import { ServerTable } from '@/components/data-table/server-table';
import PageHeader from '@/components/page-header';
import { indexBreadcrumbs } from '@/components/ping-post/dispatches/breadcrumbs';
import { dispatchColumns } from '@/components/ping-post/dispatches/list-columns';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';
import { DatatablePageProps } from '@/types';
import type { LeadDispatch } from '@/types/ping-post';
import { Head } from '@inertiajs/react';
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

  return (
    <>
      <Head title="Dispatch Logs" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Dispatch Logs" description="View lead dispatch activity and results." />
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

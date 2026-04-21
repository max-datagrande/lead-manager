import { ServerTable } from '@/components/data-table/server-table';
import { createLogColumns, LogDetailDrawer, TechnicalRequestModal } from '@/components/lead-quality/logs';
import PageHeader from '@/components/page-header';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, PageLink } from '@/types';
import type { ValidationLogRow, ValidationLogStatusOption } from '@/types/models/lead-quality';
import { Head } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Lead Quality', href: route('lead-quality.index') },
  { title: 'Validation Logs', href: route('lead-quality.validation-logs.index') },
];

interface Props {
  rows: {
    data: ValidationLogRow[];
    current_page: number;
    last_page: number;
    links: PageLink[];
    per_page: number;
    total: number;
  };
  meta: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
    from: number;
    to: number;
  };
  state: {
    search?: string;
    sort?: string;
    filters?: Array<{ id: string; value: unknown }>;
    page?: number;
    per_page?: number;
  };
  data: {
    status_options: ValidationLogStatusOption[];
    rules: Array<{ id: number; name: string }>;
    providers: Array<{ id: number; name: string }>;
    buyers: Array<{ id: number; name: string }>;
  };
}

const Index = ({ rows, meta, state, data }: Props) => {
  const [detailLogId, setDetailLogId] = useState<number | null>(null);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [technicalLogId, setTechnicalLogId] = useState<number | null>(null);
  const [modalOpen, setModalOpen] = useState(false);

  const openDetail = (id: number) => {
    setDetailLogId(id);
    setDrawerOpen(true);
  };

  const openTechnical = (id: number) => {
    setTechnicalLogId(id);
    setModalOpen(true);
  };

  const columns = useMemo(() => createLogColumns({ onOpenDetail: openDetail }), []);

  const table = useServerTable({
    routeName: 'lead-quality.validation-logs.index',
    initialState: state,
    defaultPageSize: 25,
  });

  return (
    <>
      <Head title="Lead Quality — Validation Logs" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader
          title="Validation Logs"
          description="Functional history of every validation attempt. Click a row to inspect details and raw provider traffic."
        />
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
            searchPlaceholder: 'Search fingerprint, challenge ref, message…',
            filters: [
              {
                columnId: 'status',
                title: 'Status',
                options: data.status_options,
              },
              {
                columnId: 'validation_rule_id',
                title: 'Rule',
                options: data.rules.map((r) => ({ value: String(r.id), label: r.name })),
              },
              {
                columnId: 'integration_id',
                title: 'Buyer',
                options: data.buyers.map((b) => ({ value: String(b.id), label: b.name })),
              },
              {
                columnId: 'provider_id',
                title: 'Provider',
                options: data.providers.map((p) => ({ value: String(p.id), label: p.name })),
              },
            ],
            dateRange: { column: 'created_at', label: 'Created At' },
          }}
        />
      </div>

      <LogDetailDrawer logId={detailLogId} open={drawerOpen} onOpenChange={setDrawerOpen} onOpenTechnical={openTechnical} />
      <TechnicalRequestModal logId={technicalLogId} open={modalOpen} onOpenChange={setModalOpen} />
    </>
  );
};

Index.layout = (page: ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;

export default Index;

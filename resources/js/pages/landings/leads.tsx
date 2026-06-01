import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { ServerTable } from '@/components/data-table/server-table';
import PageHeader from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type DatatablePageProps } from '@/types';
import type { ColumnDescriptor, LeadRow, LeadsViewerData } from '@/types/models/landing-page-leads';
import { Head, Link } from '@inertiajs/react';
import { Info, SquareArrowOutUpRight } from 'lucide-react';
import { useMemo, type ReactNode } from 'react';
import { route } from 'ziggy-js';

interface LeadsPageProps extends DatatablePageProps<LeadRow> {
  data: LeadsViewerData;
}

function buildLeadColumns(descriptors: ColumnDescriptor[]) {
  const cols: any[] = [
    {
      accessorKey: 'created_at',
      header: ({ column }: any) => <DataTableColumnHeader column={column} title="Created At" />,
      cell: ({ row }: any) => {
        const value = row.original.created_at;
        if (!value) return <span className="text-muted-foreground">—</span>;
        return <span className="text-sm">{new Date(value).toLocaleString()}</span>;
      },
      enableSorting: true,
    },
    {
      id: 'version',
      header: 'Version',
      cell: ({ row }: any) => {
        const version = row.original.version;
        if (!version) {
          return <span className="text-muted-foreground">—</span>;
        }
        return (
          <Badge variant="secondary" className="font-mono text-xs">
            {version.path}
          </Badge>
        );
      },
      enableSorting: false,
    },
  ];

  for (const descriptor of descriptors) {
    if (descriptor.source === 'meta' && descriptor.reference === 'id') {
      cols.unshift({
        accessorKey: 'id',
        header: ({ column }: any) => <DataTableColumnHeader column={column} title="ID" />,
        cell: ({ row }: any) => <span className="text-sm font-medium">{row.original.id}</span>,
        enableSorting: true,
      });
      continue;
    }
    if (descriptor.source === 'meta' && descriptor.reference === 'created_at') {
      continue;
    }

    cols.push({
      id: descriptor.key,
      header: descriptor.label,
      cell: ({ row }: any) => {
        const value = row.original.values?.[descriptor.key];
        if (value === null || value === undefined || value === '') {
          return <span className="text-muted-foreground">—</span>;
        }
        return <span className="text-sm">{String(value)}</span>;
      },
      enableSorting: false,
    });
  }

  return cols;
}

const Leads = ({ rows, meta, state, data }: LeadsPageProps) => {
  const { landing_page, descriptors, versions, using_defaults } = data;

  const columns = useMemo(() => buildLeadColumns(descriptors), [descriptors]);

  const versionOptions = useMemo(() => versions.map((v) => ({ value: String(v.id), label: v.path || v.name || `Version #${v.id}` })), [versions]);

  const table = useServerTable({
    routeName: 'landing_pages.leads',
    initialState: state,
    defaultPageSize: 25,
  });

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Landing Pages', href: route('landing_pages.index') },
    { title: landing_page.name, href: '#' },
    { title: 'Leads', href: '#' },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={`Leads — ${landing_page.name}`} />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title={`${landing_page.name} — Leads`} description={landing_page.url}>
          <Button variant="outline" size="sm" asChild>
            <a href={landing_page.url} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2">
              Visit landing
              <SquareArrowOutUpRight className="h-4 w-4" />
            </a>
          </Button>
          <Button variant="ghost" size="sm" asChild>
            <Link href={route('landing_pages.index')}>Back</Link>
          </Button>
        </PageHeader>

        {using_defaults && (
          <Alert>
            <Info className="h-4 w-4" />
            <AlertTitle>This landing has no columns configured</AlertTitle>
            <AlertDescription>
              You're seeing a default baseline set. Edit this landing from the Landing Pages list to choose which fields and traffic columns you want
              to display here.
            </AlertDescription>
          </Alert>
        )}

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
            searchPlaceholder: 'Search…',
            filters:
              versionOptions.length > 0
                ? [
                    {
                      columnId: 'version',
                      title: 'Version',
                      options: versionOptions,
                    },
                  ]
                : [],
            dateRange: { column: 'created_at', label: 'Created At' },
          }}
        />
      </div>
    </AppLayout>
  );
};

Leads.layout = (page: ReactNode) => page;

export default Leads;

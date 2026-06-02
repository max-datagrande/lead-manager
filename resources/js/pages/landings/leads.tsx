import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { ServerTable } from '@/components/data-table/server-table';
import { FormattedDateTime } from '@/components/formatted-date-time';
import PageHeader from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type DatatablePageProps } from '@/types';
import type { ColumnDescriptor, LeadRow, LeadsViewerData } from '@/types/models/landing-page-leads';
import { Head } from '@inertiajs/react';
import { Info, Monitor, Smartphone, SquareArrowOutUpRight } from 'lucide-react';
import { useMemo, type ReactNode } from 'react';
import { route } from 'ziggy-js';

interface LeadsPageProps extends DatatablePageProps<LeadRow> {
  data: LeadsViewerData;
}

const DEVICE_ICONS: Record<string, { icon: typeof Monitor; className: string; label: string }> = {
  mobile: { icon: Smartphone, className: 'text-emerald-500', label: 'Mobile' },
  desktop: { icon: Monitor, className: 'text-sky-500', label: 'Desktop' },
};

function DeviceTypeCell({ value }: { value: string | null }) {
  if (!value) return <span className="text-muted-foreground">—</span>;
  const config = DEVICE_ICONS[value.toLowerCase()];
  if (!config) {
    return <span className="text-sm capitalize">{value}</span>;
  }
  const Icon = config.icon;
  return (
    <span className="flex items-center gap-1.5" title={config.label}>
      <Icon className={`h-4 w-4 ${config.className}`} />
      <span className="text-sm">{config.label}</span>
    </span>
  );
}

function trafficColumnId(reference: string) {
  return 'latest_' + reference;
}

function buildLeadColumns(descriptors: ColumnDescriptor[]) {
  const fingerprintDescriptor = descriptors.find((d) => d.source === 'meta' && d.reference === 'fingerprint');
  const createdAtDescriptor = descriptors.find((d) => d.source === 'meta' && d.reference === 'created_at');
  const dataDescriptors = descriptors.filter((d) => d.source !== 'meta');

  const cols: any[] = [];

  if (fingerprintDescriptor) {
    cols.push({
      id: 'fingerprint',
      accessorKey: 'fingerprint',
      header: 'Fingerprint',
      cell: ({ row }: any) => (
        // CSS-only truncation: the full string stays in the DOM so the user can
        // select + copy it; the ellipsis is purely visual.
        <span className="block max-w-[150px] truncate font-mono text-xs" title={row.original.fingerprint}>
          {row.original.fingerprint}
        </span>
      ),
      enableSorting: false,
    });
  }

  for (const descriptor of dataDescriptors) {
    const isTraffic = descriptor.source === 'traffic';
    const columnId = isTraffic ? trafficColumnId(descriptor.reference) : descriptor.key;
    const isDeviceType = isTraffic && descriptor.reference === 'device_type';

    cols.push({
      id: columnId,
      // accessorFn is required for `column.getCanSort()` to return true — TanStack
      // refuses to mark a column as sortable when it has no accessor, even if
      // enableSorting is true. The value lives nested in `row.values[key]`.
      accessorFn: (row: LeadRow) => row.values?.[descriptor.key] ?? null,
      header: ({ column }: any) => <DataTableColumnHeader column={column} title={descriptor.label} />,
      cell: ({ row }: any) => {
        const value = row.original.values?.[descriptor.key];
        if (isDeviceType) return <DeviceTypeCell value={value} />;
        if (value === null || value === undefined || value === '') {
          return <span className="text-muted-foreground">—</span>;
        }
        return <span className="text-sm">{String(value)}</span>;
      },
      enableSorting: isTraffic, // field-source columns aren't projected as virtual cols, so they stay non-sortable
    });
  }

  cols.push({
    id: 'version',
    header: 'Version',
    cell: ({ row }: any) => {
      const version = row.original.version;
      if (!version) return <span className="text-muted-foreground">—</span>;
      return (
        <Badge variant="secondary" className="font-mono text-xs">
          {version.path}
        </Badge>
      );
    },
    enableSorting: false,
  });

  if (createdAtDescriptor) {
    cols.push({
      id: 'created_at',
      accessorKey: 'created_at',
      header: ({ column }: any) => <DataTableColumnHeader column={column} title="Created At" />,
      cell: ({ row }: any) => <FormattedDateTime date={row.original.created_at} />,
      enableSorting: true,
    });
  }

  return cols;
}

const Leads = ({ rows, meta, state, data }: LeadsPageProps) => {
  const { landing_page, descriptors, versions, using_defaults, filter_options } = data;

  const columns = useMemo(() => buildLeadColumns(descriptors), [descriptors]);

  const versionOptions = useMemo(() => versions.map((v) => ({ value: String(v.id), label: v.path || v.name || `Version #${v.id}` })), [versions]);

  const table = useServerTable({
    routeName: 'landing_pages.leads',
    routeParams: { landing_page: landing_page.id },
    initialState: state,
    defaultPageSize: 25,
  });

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Landing Pages', href: route('landing_pages.index') },
    { title: landing_page.name, href: '#' },
    { title: 'Leads', href: '#' },
  ];

  const toolbarFilters = [
    ...(versionOptions.length > 0 ? [{ columnId: 'version', title: 'Version', options: versionOptions }] : []),
    { columnId: 'device_type', title: 'Device', options: filter_options.device_type },
    { columnId: 'state', title: 'State', options: filter_options.state },
    { columnId: 'os', title: 'OS', options: filter_options.os },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={`Leads — ${landing_page.name}`} />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title={`${landing_page.name} — Leads`} description={landing_page.url}>
          <Button asChild>
            <a href={landing_page.url} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2">
              Visit landing
              <SquareArrowOutUpRight className="h-4 w-4" />
            </a>
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
            filters: toolbarFilters,
            dateRange: { column: 'created_at', label: 'Created At' },
          }}
        />
      </div>
    </AppLayout>
  );
};

Leads.layout = (page: ReactNode) => page;

export default Leads;

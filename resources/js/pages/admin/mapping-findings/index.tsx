import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { EyeOff, RotateCcw, SquarePen } from 'lucide-react';
import { route } from 'ziggy-js';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mapping Findings', href: route('admin.mapping-findings.index') }];

type Finding = {
  id: number;
  status: 'open' | 'resolved' | 'ignored';
  first_detected_at: string | null;
  last_seen_at: string | null;
  resolved_at: string | null;
  integration: { id: number; name: string } | null;
  field: { id: number; name: string; label: string | null; possible_values: string[] | null } | null;
};

interface IndexProps {
  rows: Finding[];
  filters: { status: string };
}

const STATUS_TABS = [
  { value: 'open', label: 'Open' },
  { value: 'ignored', label: 'Ignored' },
  { value: 'resolved', label: 'Resolved' },
  { value: 'all', label: 'All' },
];

const statusVariant = (status: Finding['status']): 'default' | 'secondary' | 'outline' =>
  status === 'open' ? 'default' : status === 'ignored' ? 'secondary' : 'outline';

const formatDate = (value: string | null) => (value ? new Date(value).toLocaleString() : '—');

const Index = ({ rows, filters }: IndexProps) => {
  const onStatusChange = (status: string) => {
    router.get(route('admin.mapping-findings.index'), { status }, { preserveScroll: true, preserveState: true });
  };

  const setStatus = (finding: Finding, status: 'open' | 'ignored') => {
    router.patch(route('admin.mapping-findings.update', finding.id), { status }, { preserveScroll: true });
  };

  return (
    <>
      <Head title="Mapping Findings" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader
          title="Mapping Findings"
          description="Fields with possible values used by active integrations that have no value mapping configured. Detected by the 30-min scan."
        />

        <Tabs value={filters.status} onValueChange={onStatusChange}>
          <TabsList>
            {STATUS_TABS.map((tab) => (
              <TabsTrigger key={tab.value} value={tab.value}>
                {tab.label}
              </TabsTrigger>
            ))}
          </TabsList>
        </Tabs>

        <div className="rounded-md border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Integration</TableHead>
                <TableHead>Field</TableHead>
                <TableHead>Possible values</TableHead>
                <TableHead>First detected</TableHead>
                <TableHead>Last seen</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="py-8 text-center text-sm text-muted-foreground">
                    No findings for this filter.
                  </TableCell>
                </TableRow>
              ) : (
                rows.map((finding) => {
                  const possibleValues = (finding.field?.possible_values ?? []).filter(Boolean);
                  return (
                    <TableRow key={finding.id}>
                      <TableCell className="font-medium">{finding.integration?.name ?? '—'}</TableCell>
                      <TableCell>
                        <span className="font-mono text-xs">{finding.field?.name ?? '—'}</span>
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-wrap gap-1">
                          {possibleValues.slice(0, 6).map((v) => (
                            <Badge key={v} variant="outline" className="font-mono text-xs font-normal">
                              {v}
                            </Badge>
                          ))}
                          {possibleValues.length > 6 && <span className="text-xs text-muted-foreground">+{possibleValues.length - 6}</span>}
                        </div>
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground">{formatDate(finding.first_detected_at)}</TableCell>
                      <TableCell className="text-xs text-muted-foreground">{formatDate(finding.last_seen_at)}</TableCell>
                      <TableCell>
                        <Badge variant={statusVariant(finding.status)} className="capitalize">
                          {finding.status}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center justify-end gap-1">
                          {finding.integration && (
                            <Button asChild variant="ghost" size="sm" className="h-7 gap-1.5 text-xs">
                              <Link href={route('integrations.edit', finding.integration.id)}>
                                <SquarePen className="size-3.5" />
                                Configure
                              </Link>
                            </Button>
                          )}
                          {finding.status === 'ignored' ? (
                            <Button variant="ghost" size="sm" className="h-7 gap-1.5 text-xs" onClick={() => setStatus(finding, 'open')}>
                              <RotateCcw className="size-3.5" />
                              Reopen
                            </Button>
                          ) : (
                            <Button variant="ghost" size="sm" className="h-7 gap-1.5 text-xs" onClick={() => setStatus(finding, 'ignored')}>
                              <EyeOff className="size-3.5" />
                              Ignore
                            </Button>
                          )}
                        </div>
                      </TableCell>
                    </TableRow>
                  );
                })
              )}
            </TableBody>
          </Table>
        </div>
      </div>
    </>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

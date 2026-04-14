import PageHeader from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useModal } from '@/hooks/use-modal';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { route } from 'ziggy-js';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'System', href: '#' },
  { title: 'Cache', href: route('system.cache.index') },
];

type CacheEntry = {
  key: string;
  label: string;
  description: string;
  ttl: number | null;
  group: string;
  source: string;
  exists: boolean;
  pattern?: boolean;
  count?: number;
};

interface CachePageProps {
  entries: CacheEntry[];
}

const formatTtl = (ttl: number | null): string => {
  if (ttl === null) return 'Forever';
  if (ttl < 60) return `${ttl}s`;
  if (ttl < 3600) return `${Math.round(ttl / 60)}m`;
  if (ttl < 86400) return `${Math.round(ttl / 3600)}h`;
  return `${Math.round(ttl / 86400)}d`;
};

const CachePage = ({ entries }: CachePageProps) => {
  const modal = useModal();

  const grouped = entries.reduce<Record<string, CacheEntry[]>>((acc, entry) => {
    (acc[entry.group] ??= []).push(entry);
    return acc;
  }, {});

  const handlePurge = async (entry: CacheEntry) => {
    const confirmed = await modal.confirm({
      title: `Purge ${entry.label}?`,
      description: `This will remove the cached data. It will be regenerated on next access.`,
      confirmText: 'Purge',
      destructive: true,
    });

    if (confirmed) {
      router.post(route('system.cache.flush'), { key: entry.key }, { preserveScroll: true });
    }
  };

  return (
    <>
      <Head title="Cache Management" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Cache" description="View and manage application caches." />

        {Object.entries(grouped).map(([group, items]) => (
          <div key={group} className="space-y-3">
            <h3 className="text-lg font-semibold">{group}</h3>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              {items.map((entry) => (
                <Card key={entry.key}>
                  <CardHeader className="flex-row items-start justify-between space-y-0 pb-2">
                    <div className="min-w-0 flex-1">
                      <CardTitle className="text-sm font-medium">{entry.label}</CardTitle>
                      <CardDescription className="mt-1 text-xs">{entry.description}</CardDescription>
                    </div>
                    <Badge variant={entry.exists ? 'success' : 'secondary'} className="ml-2 shrink-0">
                      {entry.exists ? 'Cached' : 'Empty'}
                    </Badge>
                  </CardHeader>
                  <CardContent>
                    <div className="flex items-center justify-between">
                      <div className="space-y-1 text-xs text-muted-foreground">
                        <p>TTL: {formatTtl(entry.ttl)}</p>
                        <p>Source: {entry.source}</p>
                        {entry.pattern && <p className="italic">Pattern-based ({entry.count ?? 0} keys)</p>}
                      </div>
                      <Button variant={!entry.exists ? "secondary" : "destructive"} size="sm" onClick={() => handlePurge(entry)} disabled={!entry.exists}>
                        <Trash2 className="h-3.5 w-3.5" />
                        Purge
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          </div>
        ))}
      </div>
    </>
  );
};

CachePage.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default CachePage;

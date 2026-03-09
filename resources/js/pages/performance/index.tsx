import PageHeader from '@/components/page-header';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { Activity, Gauge, TrendingDown, TrendingUp } from 'lucide-react';
import { useMemo } from 'react';
import { CartesianGrid, Legend, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/' },
  { title: 'Performance', href: route('performance.index') },
];

type DailyMetric = {
  id: string;
  host: string;
  recorded_date: string;
  request_count: number;
  total_ms: number;
  avg_ms: number;
  min_ms: number;
  max_ms: number;
};

type PeriodStats = {
  avg_ms: number | null;
  min_ms: number | null;
  max_ms: number | null;
  total_requests: number | null;
};

type PageProps = {
  metrics: DailyMetric[];
  stats: PeriodStats | null;
  hosts: string[];
  filters: {
    from: string;
    to: string;
    host: string | null;
  };
};

// Color palette for multi-host chart lines
const HOST_COLORS = [
  'hsl(var(--primary))',
  'hsl(220, 70%, 50%)',
  'hsl(160, 60%, 45%)',
  'hsl(30, 80%, 55%)',
  'hsl(280, 60%, 55%)',
  'hsl(350, 65%, 50%)',
  'hsl(190, 70%, 45%)',
  'hsl(80, 55%, 45%)',
];

function StatCard({ title, value, icon: Icon, suffix = '' }: { title: string; value: number | null; icon: any; suffix?: string }) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium">{title}</CardTitle>
        <Icon className="h-4 w-4 text-muted-foreground" />
      </CardHeader>
      <CardContent>
        {value !== null ? (
          <div className="text-2xl font-bold">
            {Math.round(value)}
            {suffix}
          </div>
        ) : (
          <Skeleton className="h-8 w-20" />
        )}
      </CardContent>
    </Card>
  );
}

function PerformanceIndex({ metrics, stats, hosts, filters }: PageProps) {
  // Group metrics by date for the multi-host chart
  const chartData = useMemo(() => {
    const dateMap: Record<string, Record<string, number>> = {};

    for (const m of metrics) {
      const date = m.recorded_date.split('T')[0];
      if (!dateMap[date]) {
        dateMap[date] = { date: date as any };
      }
      dateMap[date][m.host] = Number(m.avg_ms);
    }

    return Object.values(dateMap).sort((a, b) => ((a as any).date > (b as any).date ? 1 : -1));
  }, [metrics]);

  // Unique hosts in current data
  const activeHosts = useMemo(() => {
    const set = new Set(metrics.map((m) => m.host));
    return Array.from(set);
  }, [metrics]);

  // Per-host aggregated stats for the cards grid
  const hostCards = useMemo(() => {
    const map: Record<string, { totalMs: number; count: number; min: number; max: number; data: { date: string; avg_ms: number }[] }> = {};

    for (const m of metrics) {
      if (!map[m.host]) {
        map[m.host] = { totalMs: 0, count: 0, min: Infinity, max: 0, data: [] };
      }
      const h = map[m.host];
      h.totalMs += m.total_ms;
      h.count += m.request_count;
      h.min = Math.min(h.min, m.min_ms);
      h.max = Math.max(h.max, m.max_ms);
      h.data.push({ date: m.recorded_date.split('T')[0], avg_ms: Number(m.avg_ms) });
    }

    return Object.entries(map).map(([host, data]) => ({
      host,
      avg: data.count > 0 ? Math.round(data.totalMs / data.count) : 0,
      min: data.min === Infinity ? 0 : data.min,
      max: data.max,
      requests: data.count,
      chartData: data.data.sort((a, b) => (a.date > b.date ? 1 : -1)),
    }));
  }, [metrics]);

  const handleDateUpdate = ({ range }: { range: { from?: Date; to?: Date } }) => {
    const params: Record<string, string> = {};
    if (range.from) {
      params.from = format(range.from, 'yyyy-MM-dd');
    }
    if (range.to) {
      params.to = format(range.to, 'yyyy-MM-dd');
    }
    if (filters.host) {
      params.host = filters.host;
    }
    router.get(route('performance.index'), params, { preserveState: true });
  };

  const handleHostChange = (value: string) => {
    const params: Record<string, string> = {
      from: filters.from,
      to: filters.to,
    };
    if (value !== 'all') {
      params.host = value;
    }
    router.get(route('performance.index'), params, { preserveState: true });
  };

  const tooltipStyle = {
    backgroundColor: 'hsl(var(--popover))',
    border: '1px solid hsl(var(--border))',
    borderRadius: '6px',
    fontSize: '12px',
    color: 'hsl(var(--popover-foreground))',
  };

  return (
    <>
      <Head title="Performance Metrics" />
      <div className="flex flex-col gap-6 p-4">
        <PageHeader title="Performance Metrics" description="SDK fingerprint load time analytics per host">
          <div className="flex items-center gap-3">
            <Select value={filters.host ?? 'all'} onValueChange={handleHostChange}>
              <SelectTrigger className="w-[200px]">
                <SelectValue placeholder="All hosts" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All hosts</SelectItem>
                {hosts.map((h) => (
                  <SelectItem key={h} value={h}>
                    {h}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <DateRangePicker initialDateFrom={filters.from} initialDateTo={filters.to} onUpdate={handleDateUpdate} showCompare={false} />
          </div>
        </PageHeader>

        {/* Stats cards */}
        <div className="grid gap-4 md:grid-cols-4">
          <StatCard title="Average" value={stats?.avg_ms ?? null} icon={Gauge} suffix="ms" />
          <StatCard title="Min" value={stats?.min_ms ?? null} icon={TrendingDown} suffix="ms" />
          <StatCard title="Max" value={stats?.max_ms ?? null} icon={TrendingUp} suffix="ms" />
          <StatCard title="Total Requests" value={stats?.total_requests ?? null} icon={Activity} />
        </div>

        {/* Multi-host line chart */}
        <Card>
          <CardHeader>
            <CardTitle>Load Time by Host</CardTitle>
            <CardDescription>Daily average load time (ms) per host</CardDescription>
          </CardHeader>
          <CardContent>
            {chartData.length === 0 ? (
              <p className="py-8 text-center text-sm text-muted-foreground">No data for the selected period</p>
            ) : (
              <div className="h-[350px]">
                <ResponsiveContainer width="100%" height="100%">
                  <LineChart data={chartData}>
                    <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                    <XAxis
                      dataKey="date"
                      tick={{ fontSize: 12 }}
                      className="fill-muted-foreground"
                      tickFormatter={(v) => {
                        const d = new Date(v);
                        return `${d.getMonth() + 1}/${d.getDate()}`;
                      }}
                    />
                    <YAxis tick={{ fontSize: 12 }} className="fill-muted-foreground" unit="ms" />
                    <Tooltip contentStyle={tooltipStyle} formatter={(value: number) => [`${Math.round(value)}ms`]} />
                    <Legend />
                    {activeHosts.map((host, i) => (
                      <Line
                        key={host}
                        type="monotone"
                        dataKey={host}
                        stroke={HOST_COLORS[i % HOST_COLORS.length]}
                        strokeWidth={2}
                        dot={false}
                        connectNulls
                      />
                    ))}
                  </LineChart>
                </ResponsiveContainer>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Per-host cards grid */}
        {hostCards.length > 0 && (
          <>
            <h3 className="text-lg font-semibold">Per Host Breakdown</h3>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              {hostCards.map((hc, i) => (
                <Card key={hc.host}>
                  <CardHeader>
                    <CardTitle className="truncate text-sm font-medium">{hc.host}</CardTitle>
                    <CardDescription>{hc.requests.toLocaleString()} requests</CardDescription>
                  </CardHeader>
                  <CardContent>
                    <div className="mb-3 grid grid-cols-2 gap-2 text-center md:grid-cols-3 lg:grid-cols-4">
                      <div>
                        <p className="text-lg font-bold">{hc.avg}ms</p>
                        <p className="text-xs text-muted-foreground">Avg</p>
                      </div>
                      <div>
                        <p className="text-lg font-bold">{hc.min}ms</p>
                        <p className="text-xs text-muted-foreground">Min</p>
                      </div>
                      <div>
                        <p className="text-lg font-bold">{hc.max}ms</p>
                        <p className="text-xs text-muted-foreground">Max</p>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          </>
        )}
      </div>
    </>
  );
}

PerformanceIndex.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default PerformanceIndex;

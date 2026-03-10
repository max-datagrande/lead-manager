import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Timer } from 'lucide-react';
import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export type DailySummary = {
  recorded_date: string;
  total_requests: number;
  avg_ms: number;
};

type Props = {
  data?: DailySummary[];
  className?: string;
};

const tooltipStyle = {
  backgroundColor: 'hsl(var(--popover))',
  border: '1px solid hsl(var(--border))',
  borderRadius: '6px',
  fontSize: '12px',
  color: 'hsl(var(--popover-foreground))',
};

export function DashboardLoadTimeCard({ data, className }: Props) {
  const isLoading = data === undefined;
  const currentAvg = data && data.length > 0 ? Number(data[data.length - 1].avg_ms) : null;
  const overallAvg = data && data.length > 0 ? Math.round(data.reduce((sum, d) => sum + Number(d.avg_ms), 0) / data.length) : null;
  const totalRequests = data && data.length > 0 ? data.reduce((sum, d) => sum + Number(d.total_requests), 0) : 0;

  return (
    <Link href={route('performance.index')} className="flex cursor-pointer md:col-span-2 md:row-span-2 lg:col-span-3">
      <Card className={cn('w-full justify-between transition-colors hover:bg-accent/50', className)}>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Timer className="h-4 w-4 text-muted-foreground" />
            SDK Load Time
          </CardTitle>
          <CardDescription>Avg. fingerprint registration</CardDescription>
        </CardHeader>
        <CardContent className="flex-1">
          {isLoading ? (
            <div className="space-y-3">
              <Skeleton className="h-8 w-24" />
              <Skeleton className="h-50 w-full" />
            </div>
          ) : data.length === 0 ? (
            <p className="text-sm text-muted-foreground">No data yet</p>
          ) : (
            <>
              <div className="flex items-baseline gap-3">
                <p className="text-3xl font-bold">{overallAvg}ms</p>
                <span className="text-sm text-muted-foreground">
                  {totalRequests.toLocaleString()} req{totalRequests !== 1 && 's'}
                </span>
              </div>
              <p className="mt-1 text-xs text-muted-foreground">Latest: {currentAvg !== null ? `${Math.round(currentAvg)}ms` : 'N/A'}</p>
              <div className="mt-4 h-70">
                <ResponsiveContainer width="100%" height="100%">
                  <AreaChart data={data}>
                    <defs>
                      <linearGradient id="loadTimeGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="hsl(var(--primary))" stopOpacity={0.3} />
                        <stop offset="100%" stopColor="hsl(var(--primary))" stopOpacity={0.05} />
                      </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" className="stroke-border" vertical={false} />
                    <XAxis
                      dataKey="recorded_date"
                      tick={{ fontSize: 11 }}
                      className="fill-muted-foreground"
                      tickFormatter={(v) => {
                        const d = new Date(v);
                        return `${d.getMonth() + 1}/${d.getDate()}`;
                      }}
                    />
                    <YAxis tick={{ fontSize: 11 }} className="fill-muted-foreground" unit="ms" width={50} />
                    <Tooltip
                      contentStyle={tooltipStyle}
                      formatter={(value: number) => [`${Math.round(value)}ms`, 'Avg']}
                      labelFormatter={(label: string) => label}
                    />
                    <Area
                      type="monotone"
                      dataKey="avg_ms"
                      stroke="hsl(var(--primary))"
                      fill="url(#loadTimeGradient)"
                      strokeWidth={2}
                      dot={{ r: 4, fill: 'hsl(var(--primary))' }}
                      activeDot={{ r: 6 }}
                    />
                  </AreaChart>
                </ResponsiveContainer>
              </div>
            </>
          )}
        </CardContent>
      </Card>
    </Link>
  );
}

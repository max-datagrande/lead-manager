import { Button } from '@/components/ui/button'
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart'
import { Skeleton } from '@/components/ui/skeleton'
import { router } from '@inertiajs/react'
import { RefreshCw, Server } from 'lucide-react'
import { Area, AreaChart } from 'recharts'

export type VpsMetrics = {
  current_cpu: number
  current_ram: number
  disk_bytes: number
  sparkline: { time: number; cpu: number }[]
}

type Props = {
  data?: VpsMetrics | null
}

const chartConfig = {
  cpu: {
    label: 'CPU',
    color: 'hsl(var(--chart-1))',
  },
} satisfies ChartConfig

function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 GB'
  return `${(bytes / 1024 ** 3).toFixed(1)} GB`
}

export function VpsMetricsCard({ data }: Props) {
  const isLoading = data === undefined

  const handleRefresh = () => {
    router.post(
      route('vps.refresh'),
      {},
      {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['vpsMetrics'] }),
      },
    )
  }

  return (
    <Card className="col-span-1 justify-between">
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Server className="h-4 w-4 text-muted-foreground" />
          VPS 652988
        </CardTitle>
        <CardDescription>CPU · RAM · Disk</CardDescription>
        <CardAction>
          <Button variant="ghost" size="icon" className="h-7 w-7" onClick={handleRefresh}>
            <RefreshCw className="h-3.5 w-3.5" />
          </Button>
        </CardAction>
      </CardHeader>

      <CardContent>
        {isLoading ? (
          <div className="space-y-3">
            <Skeleton className="h-25 w-full" />
            <Skeleton className="h-6 w-full" />
          </div>
        ) : data === null ? (
          <p className="text-sm text-muted-foreground">No data available</p>
        ) : (
          <>
            {data.sparkline.length > 0 && (
              <ChartContainer config={chartConfig} className="h-25 w-full">
                <AreaChart data={data.sparkline}>
                  <defs>
                    <linearGradient id="cpuFill" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor="var(--color-cpu)" stopOpacity={0.3} />
                      <stop offset="100%" stopColor="var(--color-cpu)" stopOpacity={0.05} />
                    </linearGradient>
                  </defs>
                  <ChartTooltip
                    content={
                      <ChartTooltipContent
                        formatter={(value) => [`${Number(value).toFixed(1)}%`, 'CPU']}
                        hideLabel
                      />
                    }
                  />
                  <Area
                    type="step"
                    dataKey="cpu"
                    stroke="var(--color-cpu)"
                    fill="url(#cpuFill)"
                    strokeWidth={1.5}
                    dot={false}
                    isAnimationActive={false}
                  />
                </AreaChart>
              </ChartContainer>
            )}

            <div className="mt-3 grid grid-cols-3 gap-2 text-center">
              <div>
                <p className="text-xl font-bold">{data.current_cpu.toFixed(1)}%</p>
                <p className="text-xs text-muted-foreground">CPU</p>
              </div>
              <div>
                <p className="text-xl font-bold">{formatBytes(data.current_ram)}</p>
                <p className="text-xs text-muted-foreground">RAM</p>
              </div>
              <div>
                <p className="text-xl font-bold">{formatBytes(data.disk_bytes)}</p>
                <p className="text-xs text-muted-foreground">Disk</p>
              </div>
            </div>
          </>
        )}
      </CardContent>
    </Card>
  )
}

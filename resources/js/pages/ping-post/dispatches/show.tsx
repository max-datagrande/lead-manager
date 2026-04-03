import { showBreadcrumbs } from '@/components/ping-post/dispatches/breadcrumbs'
import { DispatchTimeline } from '@/components/ping-post/dispatches/dispatch-timeline'
import { StatusBadge } from '@/components/ping-post/status-badge'
import PageHeader from '@/components/page-header'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/layouts/app-layout'
import type { LeadDispatch } from '@/types/ping-post'
import { Head } from '@inertiajs/react'
import { useMemo } from 'react'

interface Field {
  id: number
  name: string
  label: string
}

interface Props {
  dispatch: LeadDispatch
  fields?: Field[]
}

const DispatchesShow = ({ dispatch, fields = [] }: Props) => {
  const snapshotRows = useMemo(() => {
    if (!dispatch.lead_snapshot || !fields.length) return []
    const fieldMap = new Map(fields.map((f) => [String(f.id), f]))
    return Object.entries(dispatch.lead_snapshot).map(([fieldId, value]) => {
      const field = fieldMap.get(fieldId)
      return { label: field?.label ?? `Field #${fieldId}`, name: field?.name ?? fieldId, value }
    })
  }, [dispatch.lead_snapshot, fields])

  return (
    <>
      <Head title={`Dispatch #${dispatch.id}`} />
      <div className="relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader
          title={`Dispatch #${dispatch.id}`}
          description={`${dispatch.workflow?.name ?? 'Unknown workflow'} · ${dispatch.strategy_used}`}
        >
          <StatusBadge status={dispatch.status} variant="dispatch" className="text-sm" />
        </PageHeader>

        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          {/* Meta + Snapshot */}
          <div className="space-y-6">
          <Card className='overflow-hidden'>
            <CardHeader className='bg-muted'>
              <CardTitle>Details</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm pt-3">
              <div className="flex justify-between">
                <span className="text-muted-foreground">UUID</span>
                <span className="font-mono text-xs">{dispatch.dispatch_uuid}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Fingerprint</span>
                <span className="font-mono text-xs">{dispatch.fingerprint.slice(0, 12)}…</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Strategy</span>
                <Badge variant="outline" className="text-xs">{dispatch.strategy_used}</Badge>
              </div>
              {dispatch.final_price && (
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Final Price</span>
                  <span className="font-medium text-green-600">${Number(dispatch.final_price).toFixed(2)}</span>
                </div>
              )}
              {dispatch.winner_integration && (
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Winner</span>
                  <span>{dispatch.winner_integration.name}</span>
                </div>
              )}
              {dispatch.total_duration_ms && (
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Duration</span>
                  <span>{dispatch.total_duration_ms}ms</span>
                </div>
              )}
              {dispatch.fallback_activated && (
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Fallback</span>
                  <Badge variant="outline" className="border-amber-500 text-amber-600 text-xs">Activated</Badge>
                </div>
              )}
              {dispatch.started_at && (
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Started</span>
                  <span className="text-xs">{new Date(dispatch.started_at).toLocaleString()}</span>
                </div>
              )}
              {dispatch.completed_at && (
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Completed</span>
                  <span className="text-xs">{new Date(dispatch.completed_at).toLocaleString()}</span>
                </div>
              )}
            </CardContent>
          </Card>

          {snapshotRows.length > 0 && (
            <Card className='overflow-hidden'>
              <CardHeader className='bg-muted'>
                <CardTitle>Lead Snapshot</CardTitle>
                {dispatch.started_at && (
                  <p className="text-xs text-muted-foreground">
                    Captured at {new Date(dispatch.started_at).toLocaleString()}
                  </p>
                )}
              </CardHeader>
              <CardContent className="space-y-3 max-h-[450px] overflow-y-auto pt-3">
                {snapshotRows.map((row) => (
                  <div key={row.name} className="border-b pb-2 last:border-0 last:pb-0">
                    <p className="text-xs text-muted-foreground/50">{row.name}</p>
                    <p className="text- sm text-muted-foreground">{row.label}</p>
                    <p className="text-base truncate">{row.value || '—'}</p>
                  </div>
                ))}
              </CardContent>
            </Card>
          )}
          </div>

          {/* Timeline */}
          <div className="lg:col-span-2">
            <DispatchTimeline dispatch={dispatch} />
          </div>
        </div>
      </div>
    </>
  )
}

DispatchesShow.layout = (page: React.ReactNode & { props: { dispatch: LeadDispatch } }) =>
  <AppLayout children={page} breadcrumbs={showBreadcrumbs(page.props.dispatch)} />
export default DispatchesShow

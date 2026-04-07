import PageHeader from '@/components/page-header'
import { timelineBreadcrumbs } from '@/components/ping-post/dispatches/breadcrumbs'
import { StatusBadge } from '@/components/ping-post/status-badge'
import { TimelineLogList } from '@/components/ping-post/dispatches/timeline-log-list'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/layouts/app-layout'
import type { DispatchTimelineLog, LeadDispatch } from '@/types/ping-post'
import { Head, Link } from '@inertiajs/react'
import { ArrowLeft, ScrollText } from 'lucide-react'
import { route } from 'ziggy-js'

interface Props {
  dispatch: LeadDispatch
  timelineLogs: DispatchTimelineLog[]
}

const TimelinePage = ({ dispatch, timelineLogs }: Props) => {
  const workflowName = dispatch.workflow?.name ?? `Workflow #${dispatch.workflow_id}`
  const strategyLabel = dispatch.strategy_used?.replace('_', ' ') ?? ''

  return (
    <>
      <Head title={`Timeline · ${dispatch.dispatch_uuid.slice(0, 8)}`} />
      <div className="relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader
          title="Timeline Log"
          description={`${workflowName} · ${strategyLabel}`}
          smallText={dispatch.dispatch_uuid}
        >
          <div className="flex items-center gap-3">
            <StatusBadge status={dispatch.status} variant="dispatch" className="text-sm" />
            <Button variant="outline" size="sm" asChild>
              <Link href={route('ping-post.dispatches.show', dispatch.id)}>
                <ArrowLeft className="mr-2 h-4 w-4" />
                Back to Dispatch
              </Link>
            </Button>
          </div>
        </PageHeader>

        <Card>
          <CardHeader className="pb-4">
            <CardTitle className="flex items-center gap-2 text-base">
              <ScrollText className="h-4 w-4 text-muted-foreground" />
              Events ({timelineLogs.length})
            </CardTitle>
          </CardHeader>
          <CardContent>
            <TimelineLogList logs={timelineLogs} />
          </CardContent>
        </Card>
      </div>
    </>
  )
}

TimelinePage.layout = (page: React.ReactNode & { props: Props }) => {
  const breadcrumbs = timelineBreadcrumbs(page.props.dispatch)
  return <AppLayout children={page} breadcrumbs={breadcrumbs} />
}

export default TimelinePage

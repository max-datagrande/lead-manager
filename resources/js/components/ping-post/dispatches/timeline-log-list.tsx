import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { cn } from '@/lib/utils'
import type { DispatchTimelineLog } from '@/types/ping-post'
import { ChevronRight } from 'lucide-react'
import { useState } from 'react'
import { ResultDetailButton } from './result-detail-modal'
import { getDotColor, TimelineEventBadge } from './timeline-event-badge'

function formatRelativeMs(ms: number): string {
  if (ms < 1000) return `+${ms}ms`
  return `+${(ms / 1000).toFixed(2)}s`
}

function parseTimestamp(ts: string): number {
  // Handle microsecond precision: "2026-04-07 16:13:00.123456"
  return new Date(ts.replace(' ', 'T') + 'Z').getTime()
}

interface EntryProps {
  log: DispatchTimelineLog
  relativeMs: number
}

function TimelineLogEntry({ log, relativeMs }: EntryProps) {
  const [expanded, setExpanded] = useState(false)
  const hasContext = log.context !== null && Object.keys(log.context).length > 0
  const pingResultId = log.context?.ping_result_id as number | undefined
  const postResultId = log.context?.post_result_id as number | undefined

  return (
    <div className="relative pb-6 pl-8 last:pb-0">
      {/* Dot on the line */}
      <div className={cn('absolute left-0 top-1 h-3 w-3 rounded-full ring-2 ring-background', getDotColor(log.event))} />

      <div className="flex flex-wrap items-start gap-2">
        {/* Relative timestamp */}
        <span className="w-16 shrink-0 font-mono text-xs text-muted-foreground" title={log.logged_at}>
          {formatRelativeMs(relativeMs)}
        </span>

        {/* Event badge */}
        <TimelineEventBadge event={log.event} />

        {/* Message */}
        <span className="flex-1 text-sm">{log.message}</span>

        {/* Result detail buttons */}
        {pingResultId && <ResultDetailButton type="ping" resultId={pingResultId} />}
        {postResultId && <ResultDetailButton type="post" resultId={postResultId} />}

        {/* Context expand toggle */}
        {hasContext && (
          <Collapsible open={expanded} onOpenChange={setExpanded}>
            <CollapsibleTrigger className="flex items-center gap-1 rounded px-1.5 py-0.5 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground">
              <ChevronRight className={cn('h-3 w-3 transition-transform', expanded && 'rotate-90')} />
              context
            </CollapsibleTrigger>
            <CollapsibleContent className="mt-2 w-full">
              <pre className="max-h-48 overflow-auto rounded-md bg-muted p-3 font-mono text-xs text-muted-foreground">
                {JSON.stringify(log.context, null, 2)}
              </pre>
            </CollapsibleContent>
          </Collapsible>
        )}
      </div>
    </div>
  )
}

interface Props {
  logs: DispatchTimelineLog[]
}

export function TimelineLogList({ logs }: Props) {
  if (logs.length === 0) {
    return (
      <p className="py-12 text-center text-sm text-muted-foreground">
        No timeline events recorded for this dispatch.
      </p>
    )
  }

  const baseTime = parseTimestamp(logs[0].logged_at)

  return (
    <div className="relative ml-1.5">
      {/* Vertical connecting line */}
      <div className="absolute left-[5px] top-1 bottom-0 w-px bg-border" />

      {logs.map((log) => {
        const relativeMs = parseTimestamp(log.logged_at) - baseTime
        return <TimelineLogEntry key={log.id} log={log} relativeMs={relativeMs} />
      })}
    </div>
  )
}

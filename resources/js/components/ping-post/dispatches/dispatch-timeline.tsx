import { StatusBadge } from '@/components/ping-post/status-badge'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import type { LeadDispatch, PingResult, PostResult } from '@/types/ping-post'
import { ChevronDown, Trophy } from 'lucide-react'

interface PayloadViewProps {
  label: string
  data: Record<string, unknown> | null
}

function PayloadView({ label, data }: PayloadViewProps) {
  if (!data) return null
  return (
    <div className="space-y-1">
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      <pre className="max-h-32 overflow-auto rounded bg-muted p-2 text-xs">
        {JSON.stringify(data, null, 2)}
      </pre>
    </div>
  )
}

function PingResultCard({ ping }: { ping: PingResult }) {
  return (
    <div className="rounded border bg-card p-3 text-sm">
      <div className="flex items-center justify-between">
        <span className="font-medium">Ping</span>
        <div className="flex items-center gap-2">
          {ping.bid_price && <span className="font-medium text-green-600">${Number(ping.bid_price).toFixed(2)}</span>}
          <StatusBadge status={ping.status} variant="ping" />
          {ping.http_status_code && <span className="text-xs text-muted-foreground">HTTP {ping.http_status_code}</span>}
          {ping.duration_ms && <span className="text-xs text-muted-foreground">{ping.duration_ms}ms</span>}
        </div>
      </div>
      {ping.skip_reason && (
        <p className="mt-1 text-xs text-muted-foreground">{ping.skip_reason}</p>
      )}
      {ping.request_url && (
        <p className="mt-1 truncate text-xs text-muted-foreground">{ping.request_url}</p>
      )}
    </div>
  )
}

function PostResultCard({ post }: { post: PostResult }) {
  return (
    <div className="rounded border bg-card p-3 text-sm">
      <div className="flex items-center justify-between">
        <span className="font-medium">Post</span>
        <div className="flex items-center gap-2">
          {post.price_final && <span className="font-medium text-green-600">${Number(post.price_final).toFixed(2)}</span>}
          <StatusBadge status={post.status} variant="post" />
          {post.http_status_code && <span className="text-xs text-muted-foreground">HTTP {post.http_status_code}</span>}
          {post.duration_ms && <span className="text-xs text-muted-foreground">{post.duration_ms}ms</span>}
        </div>
      </div>
      {post.rejection_reason && (
        <p className="mt-1 text-xs text-muted-foreground">{post.rejection_reason}</p>
      )}
      {post.request_url && (
        <p className="mt-1 truncate text-xs text-muted-foreground">{post.request_url}</p>
      )}
      {post.postback_expires_at && post.status === 'pending_postback' && (
        <p className="mt-1 text-xs text-muted-foreground">
          Postback expires: {new Date(post.postback_expires_at).toLocaleString()}
        </p>
      )}
    </div>
  )
}

interface BuyerTimelineItemProps {
  buyerName: string
  integrationId: number
  isWinner: boolean
  pingResult?: PingResult | null
  postResult?: PostResult | null
}

function BuyerTimelineItem({ buyerName, integrationId, isWinner, pingResult, postResult }: BuyerTimelineItemProps) {
  return (
    <Collapsible defaultOpen={isWinner}>
      <CollapsibleTrigger className="flex w-full items-center justify-between rounded-md border bg-card px-4 py-3 text-sm hover:bg-accent">
        <div className="flex items-center gap-3">
          {isWinner && <Trophy className="h-4 w-4 text-yellow-500" />}
          <span className="font-medium">{buyerName}</span>
        </div>
        <div className="flex items-center gap-2">
          {pingResult && <StatusBadge status={pingResult.status} variant="ping" />}
          {postResult && <StatusBadge status={postResult.status} variant="post" />}
          <ChevronDown className="h-4 w-4 text-muted-foreground" />
        </div>
      </CollapsibleTrigger>
      <CollapsibleContent>
        <div className="mt-2 space-y-2 pl-4">
          {pingResult && <PingResultCard ping={pingResult} />}
          {postResult && <PostResultCard post={postResult} />}
        </div>
      </CollapsibleContent>
    </Collapsible>
  )
}

interface Props {
  dispatch: LeadDispatch
}

export function DispatchTimeline({ dispatch }: Props) {
  const pingResults = dispatch.pingResults ?? []
  const postResults = dispatch.postResults ?? []

  // Group post results by integration_id
  const postByIntegration = new Map<number, PostResult>()
  postResults.forEach((pr) => postByIntegration.set(pr.integration_id, pr))

  // Build a unified list of buyers that participated
  const buyerIds = new Set<number>([
    ...pingResults.map((pr) => pr.integration_id),
    ...postResults.map((pr) => pr.integration_id),
  ])

  const getBuyerName = (id: number) => {
    const ping = pingResults.find((p) => p.integration_id === id)
    const post = postResults.find((p) => p.integration_id === id)
    return ping?.integration?.name ?? post?.integration?.name ?? `Buyer #${id}`
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-3">
          Timeline
          <div className="flex items-center gap-2">
            <StatusBadge status={dispatch.status} variant="dispatch" />
            {dispatch.final_price && (
              <Badge variant="outline" className="border-green-500 text-green-600">
                ${Number(dispatch.final_price).toFixed(2)}
              </Badge>
            )}
            {dispatch.total_duration_ms && (
              <span className="text-sm font-normal text-muted-foreground">{dispatch.total_duration_ms}ms</span>
            )}
          </div>
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        {buyerIds.size === 0 && (
          <p className="text-sm text-muted-foreground">No buyer interactions recorded.</p>
        )}
        {[...buyerIds].map((id) => (
          <BuyerTimelineItem
            key={id}
            buyerName={getBuyerName(id)}
            integrationId={id}
            isWinner={dispatch.winner_integration_id === id}
            pingResult={pingResults.find((p) => p.integration_id === id) ?? null}
            postResult={postByIntegration.get(id) ?? null}
          />
        ))}

        {dispatch.error_message && (
          <div className="rounded border border-destructive bg-destructive/10 p-3 text-sm text-destructive">
            {dispatch.error_message}
          </div>
        )}
      </CardContent>
    </Card>
  )
}

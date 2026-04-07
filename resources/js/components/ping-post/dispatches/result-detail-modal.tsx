import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Separator } from '@/components/ui/separator'
import axios from 'axios'
import { ExternalLink, Loader2 } from 'lucide-react'
import { useState } from 'react'
import { route } from 'ziggy-js'

interface ResultData {
  id: number
  status: string
  http_status_code: number | null
  duration_ms: number | null
  bid_price?: number | null
  price_offered?: number | null
  price_final?: number | null
  rejection_reason?: string | null
  skip_reason?: string | null
  request_url: string | null
  request_payload: Record<string, any> | null
  request_headers: Record<string, any> | null
  response_body: Record<string, any> | null
  integration?: { name: string } | null
}

interface Props {
  type: 'ping' | 'post'
  resultId: number
}

export function ResultDetailButton({ type, resultId }: Props) {
  const [open, setOpen] = useState(false)
  const [loading, setLoading] = useState(false)
  const [data, setData] = useState<ResultData | null>(null)

  const handleOpen = async () => {
    setOpen(true)
    if (data) return
    setLoading(true)
    try {
      const url = route('ping-post.dispatches.result-detail', { type, id: resultId })
      const res = await axios.get(url)
      setData(res.data)
    } catch (e) {
      console.error('Failed to load result detail', e)
    } finally {
      setLoading(false)
    }
  }

  const label = type === 'ping' ? 'Ping' : 'Post'
  const price = data?.bid_price ?? data?.price_offered ?? data?.price_final

  return (
    <>
      <Button
        type="button"
        variant="ghost"
        size="sm"
        className="h-6 gap-1 px-1.5 text-xs text-muted-foreground hover:text-foreground"
        onClick={handleOpen}
      >
        <ExternalLink className="h-3 w-3" />
        {label} #{resultId}
      </Button>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="flex max-h-[85vh] max-w-3xl flex-col overflow-hidden">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              {label} Result #{resultId}
              {data?.integration?.name && (
                <span className="text-sm font-normal text-muted-foreground">· {data.integration.name}</span>
              )}
            </DialogTitle>
          </DialogHeader>

          {loading ? (
            <div className="flex items-center justify-center py-12">
              <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
            </div>
          ) : data ? (
            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto">
              {/* Summary row */}
              <div className="flex flex-wrap items-center gap-3 text-sm">
                <Badge variant="outline">{data.status}</Badge>
                {data.http_status_code && <span className="text-muted-foreground">HTTP {data.http_status_code}</span>}
                {data.duration_ms != null && <span className="text-muted-foreground">{data.duration_ms}ms</span>}
                {price != null && <span className="font-medium text-green-600">${Number(price).toFixed(2)}</span>}
              </div>

              {(data.rejection_reason || data.skip_reason) && (
                <p className="text-sm text-destructive">{data.rejection_reason ?? data.skip_reason}</p>
              )}

              <Separator />

              {/* Request */}
              <Section title="Request URL" content={data.request_url} mono />
              <Section title="Request Headers" json={data.request_headers} />
              <Section title="Request Payload" json={data.request_payload} />

              <Separator />

              {/* Response */}
              <Section title="Response Body" json={data.response_body} />
            </div>
          ) : (
            <p className="py-8 text-center text-sm text-muted-foreground">Failed to load result data.</p>
          )}
        </DialogContent>
      </Dialog>
    </>
  )
}

function Section({ title, content, json, mono }: { title: string; content?: string | null; json?: Record<string, any> | null; mono?: boolean }) {
  const display = json ? JSON.stringify(json, null, 2) : content
  if (!display) return null

  return (
    <div>
      <p className="mb-1 text-xs font-medium text-muted-foreground">{title}</p>
      <pre className={`max-h-56 overflow-auto rounded-md bg-muted p-3 text-xs ${mono ? 'break-all' : ''}`}>
        {display}
      </pre>
    </div>
  )
}

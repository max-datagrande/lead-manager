import { type DispatchLog, type PostbackExecution } from '@/components/postbacks/executions'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader } from '@/components/ui/card'
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { AlertCircle, CheckCircle, Clock, Globe } from 'lucide-react'
import { useEffect, useState } from 'react'

interface DispatchLogsViewerProps {
  execution: PostbackExecution
}

function statusBadge(statusCode: number | null, errorMessage: string | null) {
  if (statusCode !== null && statusCode >= 200 && statusCode < 300) {
    return (
      <Badge variant="outline" className="border-green-200 bg-green-100 text-green-800">
        <CheckCircle className="mr-1 h-3 w-3" />
        {statusCode} OK
      </Badge>
    )
  }
  if (statusCode !== null && statusCode >= 400) {
    return (
      <Badge variant="destructive" className="border-red-200 bg-red-100 text-red-800">
        <AlertCircle className="mr-1 h-3 w-3" />
        {statusCode}
      </Badge>
    )
  }
  if (errorMessage) {
    return (
      <Badge variant="destructive" className="border-red-200 bg-red-100 text-red-800">
        <AlertCircle className="mr-1 h-3 w-3" />
        Network Error
      </Badge>
    )
  }
  return (
    <Badge variant="secondary">
      <Clock className="mr-1 h-3 w-3" />
      {statusCode ?? 'Pending'}
    </Badge>
  )
}

export function DispatchLogsViewer({ execution }: DispatchLogsViewerProps) {
  const [logs, setLogs] = useState<DispatchLog[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const fetchLogs = async () => {
      try {
        setLoading(true)
        const res = await fetch(`/postbacks/executions/${execution.id}/dispatch-logs`)
        const json = await res.json()
        if (json.success) {
          setLogs(json.data)
        } else {
          setError('Error loading dispatch logs')
        }
      } catch {
        setError('Connection error')
      } finally {
        setLoading(false)
      }
    }
    fetchLogs()
  }, [execution.id])

  const title = `Dispatch Logs — ${execution.execution_uuid.slice(0, 8)}…`

  if (loading) {
    return (
      <>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription className="sr-only">HTTP dispatch attempt logs for this execution.</DialogDescription>
        </DialogHeader>
        <div className="flex items-center justify-center p-8 gap-2 text-muted-foreground">
          <Clock className="h-4 w-4 animate-spin" />
          <span>Loading logs...</span>
        </div>
      </>
    )
  }

  if (error) {
    return (
      <>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription className="sr-only">HTTP dispatch attempt logs for this execution.</DialogDescription>
        </DialogHeader>
        <div className="flex items-center justify-center p-8 gap-2 text-destructive">
          <AlertCircle className="h-4 w-4" />
          <span>{error}</span>
        </div>
      </>
    )
  }

  if (logs.length === 0) {
    return (
      <>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription className="sr-only">HTTP dispatch attempt logs for this execution.</DialogDescription>
        </DialogHeader>
        <div className="flex flex-col items-center justify-center p-8 gap-2 text-muted-foreground">
          <Globe className="h-8 w-8" />
          <p>No dispatch logs yet for this execution.</p>
        </div>
      </>
    )
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle>{title}</DialogTitle>
        <DialogDescription>
          {execution.outbound_url && (
            <span className="font-mono text-xs break-all">{execution.outbound_url}</span>
          )}
        </DialogDescription>
      </DialogHeader>

      <Tabs defaultValue={logs[0].id.toString()} className="w-full">
        <TabsList className="grid w-full" style={{ gridTemplateColumns: `repeat(${Math.min(logs.length, 5)}, 1fr)` }}>
          {logs.map((log, i) => (
            <TabsTrigger key={log.id} value={log.id.toString()} className="flex items-center gap-1.5">
              <span
                className={`h-2 w-2 rounded-full ${
                  log.response_status_code !== null && log.response_status_code >= 200 && log.response_status_code < 300
                    ? 'bg-green-500'
                    : log.error_message
                      ? 'bg-red-500'
                      : 'bg-yellow-500'
                }`}
              />
              Attempt {i + 1}
            </TabsTrigger>
          ))}
        </TabsList>

        {logs.map((log) => (
          <TabsContent key={log.id} value={log.id.toString()}>
            <Card>
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between gap-4">
                  <div className="space-y-1">
                    <div className="flex items-center gap-2 text-sm font-medium">
                      <span className="font-mono text-blue-600">{log.request_method}</span>
                      <span className="break-all font-mono text-xs text-muted-foreground">{log.request_url}</span>
                    </div>
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                      <span>{log.response_time_ms ?? 0}ms</span>
                      {log.created_at && (
                        <>
                          <span>·</span>
                          <span>{new Date(log.created_at).toLocaleString()}</span>
                        </>
                      )}
                    </div>
                  </div>
                  {statusBadge(log.response_status_code, log.error_message)}
                </div>
              </CardHeader>
              <CardContent className="space-y-3">
                {log.error_message && (
                  <div className="rounded-md border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-950">
                    <div className="mb-1 flex items-center gap-2 text-sm font-medium text-red-700 dark:text-red-400">
                      <AlertCircle className="h-4 w-4" />
                      Error
                    </div>
                    <p className="text-sm text-red-600 dark:text-red-400">{log.error_message}</p>
                  </div>
                )}
                {log.response_body && (
                  <div className="space-y-1">
                    <p className="text-xs font-medium text-muted-foreground uppercase tracking-wide">Response Body</p>
                    <pre className="rounded-md bg-muted p-3 text-xs font-mono overflow-auto max-h-60 whitespace-pre-wrap break-all">
                      {log.response_body}
                    </pre>
                  </div>
                )}
              </CardContent>
            </Card>
          </TabsContent>
        ))}
      </Tabs>
    </>
  )
}

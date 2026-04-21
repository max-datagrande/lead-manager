import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { ExternalRequestRow } from '@/types/models/lead-quality';
import { formatDateTime } from '@/utils/table';
import { AlertCircle, CheckCircle2, Clock } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Props {
  logId: number | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

function prettyJson(value: unknown): string {
  try {
    return JSON.stringify(value ?? {}, null, 2);
  } catch {
    return String(value);
  }
}

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  success: 'default',
  failed: 'destructive',
  timeout: 'outline',
  exception: 'destructive',
};

export function TechnicalRequestModal({ logId, open, onOpenChange }: Props) {
  const [requests, setRequests] = useState<ExternalRequestRow[] | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!open || !logId) return;
    let cancelled = false;
    setLoading(true);
    setError(null);

    fetch(route('lead-quality.validation-logs.technical', logId), {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then((res) => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json() as Promise<{ requests: ExternalRequestRow[] }>;
      })
      .then((json) => {
        if (!cancelled) setRequests(json.requests);
      })
      .catch((e) => {
        if (!cancelled) setError(e instanceof Error ? e.message : 'Unexpected error.');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [logId, open]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-3xl">
        <DialogHeader>
          <DialogTitle>Technical requests</DialogTitle>
          <DialogDescription>Raw HTTP interactions between this validation log and the external provider.</DialogDescription>
        </DialogHeader>

        <div className="max-h-[70vh] overflow-y-auto pr-3">
          {loading && (
            <div className="flex items-center gap-2 p-4 text-sm text-muted-foreground">
              <Clock className="h-4 w-4 animate-spin" />
              Loading requests…
            </div>
          )}
          {error && (
            <div className="flex items-center gap-2 rounded-md border border-rose-300 bg-rose-50 p-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-200">
              <AlertCircle className="h-4 w-4" />
              {error}
            </div>
          )}

          {!loading && !error && requests && requests.length === 0 && (
            <div className="rounded-md border bg-muted/20 p-6 text-center text-sm text-muted-foreground">
              No technical requests recorded for this log yet.
            </div>
          )}

          {!loading && requests && requests.length > 0 && (
            <div className="space-y-4">
              {requests.map((req) => (
                <div key={req.id} className="rounded-md border">
                  <div className="flex flex-wrap items-center justify-between gap-2 border-b bg-muted/30 px-3 py-2">
                    <div className="flex items-center gap-2">
                      <Badge variant={STATUS_VARIANT[req.status] ?? 'outline'} className="gap-1">
                        {req.status === 'success' ? <CheckCircle2 className="h-3 w-3" /> : <AlertCircle className="h-3 w-3" />}
                        {req.status}
                      </Badge>
                      <code className="text-xs">
                        {req.request_method} {req.response_status_code ?? '—'}
                      </code>
                      <span className="text-xs text-muted-foreground">{req.operation ?? '—'}</span>
                    </div>
                    <div className="text-xs text-muted-foreground">
                      {req.duration_ms !== null && <span className="mr-3">{req.duration_ms} ms</span>}
                      {req.requested_at && formatDateTime(req.requested_at)}
                    </div>
                  </div>

                  <div className="px-3 py-2 text-xs">
                    <div className="mb-1 font-medium text-muted-foreground">URL</div>
                    <code className="block rounded bg-muted px-2 py-1 break-all">{req.request_url}</code>
                  </div>

                  {req.error_message && (
                    <div className="border-t px-3 py-2 text-xs">
                      <div className="mb-1 font-medium text-rose-700 dark:text-rose-300">Error</div>
                      <code className="block rounded bg-rose-50 px-2 py-1 text-rose-900 dark:bg-rose-950/40 dark:text-rose-200">
                        {req.error_message}
                      </code>
                    </div>
                  )}

                  <div className="grid gap-3 border-t p-3 text-xs md:grid-cols-2">
                    <div>
                      <div className="mb-1 font-medium text-muted-foreground">Request body</div>
                      <pre className="max-h-48 overflow-auto rounded bg-muted p-2 font-mono">{prettyJson(req.request_body)}</pre>
                    </div>
                    <div>
                      <div className="mb-1 font-medium text-muted-foreground">Response body</div>
                      <pre className="max-h-48 overflow-auto rounded bg-muted p-2 font-mono">{prettyJson(req.response_body)}</pre>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}

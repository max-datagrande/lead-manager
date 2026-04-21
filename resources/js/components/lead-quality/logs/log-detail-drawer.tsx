import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import type { ValidationLogDetail } from '@/types/models/lead-quality';
import { formatDateTime } from '@/utils/table';
import { AlertCircle, FileSearch } from 'lucide-react';
import { useEffect, useState } from 'react';
import { StatusBadge } from './status-badge';

interface Props {
  logId: number | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onOpenTechnical: (logId: number) => void;
}

function Field({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="grid grid-cols-[140px_minmax(0,1fr)] items-start gap-3 py-1.5">
      <div className="pt-0.5 text-xs font-medium text-muted-foreground">{label}</div>
      <div className="min-w-0 text-sm wrap-break-word">{value ?? <span className="text-muted-foreground">—</span>}</div>
    </div>
  );
}

export function LogDetailDrawer({ logId, open, onOpenChange, onOpenTechnical }: Props) {
  const [log, setLog] = useState<ValidationLogDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!open || !logId) return;
    let cancelled = false;
    setLoading(true);
    setError(null);

    fetch(route('lead-quality.validation-logs.show', logId), {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then((res) => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json() as Promise<{ log: ValidationLogDetail }>;
      })
      .then((json) => {
        if (!cancelled) setLog(json.log);
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
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-xl lg:max-w-[50vw]">
        <SheetHeader>
          <SheetTitle className="flex items-center gap-2">
            Validation log
            {log && <StatusBadge status={log.status} />}
          </SheetTitle>
          <SheetDescription>Functional view of this challenge attempt.</SheetDescription>
        </SheetHeader>

        {loading && <div className="py-6 text-sm text-muted-foreground">Loading…</div>}

        {error && (
          <div className="mt-4 flex items-center gap-2 rounded-md border border-rose-300 bg-rose-50 p-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-200">
            <AlertCircle className="h-4 w-4" />
            {error}
          </div>
        )}

        {log && !loading && (
          <div className="mt-4 space-y-6">
            <section>
              <h3 className="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Outcome</h3>
              <Field label="Result" value={log.result ?? <span className="text-muted-foreground">—</span>} />
              <Field label="Attempts" value={log.attempts_count} />
              <Field label="Message" value={log.message} />
              <Field
                label="Challenge ref"
                value={
                  log.challenge_reference ? (
                    <code className="block rounded bg-muted px-1.5 py-0.5 text-xs wrap-anywhere">{log.challenge_reference}</code>
                  ) : null
                }
              />
            </section>

            <section>
              <h3 className="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Timing</h3>
              <Field label="Created" value={formatDateTime(log.created_at)} />
              <Field label="Started" value={log.started_at ? formatDateTime(log.started_at) : null} />
              <Field label="Resolved" value={log.resolved_at ? formatDateTime(log.resolved_at) : null} />
              <Field label="Expires" value={log.expires_at ? formatDateTime(log.expires_at) : null} />
            </section>

            <section>
              <h3 className="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Context</h3>
              <Field
                label="Fingerprint"
                value={log.fingerprint ? <code className="block rounded bg-muted px-1.5 py-0.5 text-xs wrap-anywhere">{log.fingerprint}</code> : null}
              />
              <Field
                label="Rule"
                value={
                  log.rule_detail ? (
                    <div className="flex items-center gap-1.5">
                      <span>{log.rule_detail.name}</span>
                      {log.rule_detail.validation_type && (
                        <Badge variant="outline" className="text-xs">
                          {log.rule_detail.validation_type}
                        </Badge>
                      )}
                    </div>
                  ) : null
                }
              />
              <Field
                label="Provider"
                value={
                  log.provider_detail ? (
                    <div className="flex items-center gap-1.5">
                      <span>{log.provider_detail.name}</span>
                      {log.provider_detail.type && (
                        <Badge variant="outline" className="text-xs">
                          {log.provider_detail.type}
                        </Badge>
                      )}
                    </div>
                  ) : null
                }
              />
              <Field label="Buyer" value={log.buyer?.name} />
              <Field
                label="Dispatch"
                value={
                  log.lead_dispatch ? (
                    <div className="flex items-center gap-1.5">
                      <code className="text-xs">#{log.lead_dispatch.id}</code>
                      {log.lead_dispatch.status && (
                        <Badge variant="outline" className="text-xs">
                          {log.lead_dispatch.status}
                        </Badge>
                      )}
                    </div>
                  ) : null
                }
              />
            </section>

            {log.context && Object.keys(log.context).length > 0 && (
              <section>
                <h3 className="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Extra context</h3>
                <pre className="max-h-60 overflow-auto rounded bg-muted p-3 text-xs">{JSON.stringify(log.context, null, 2)}</pre>
              </section>
            )}

            <div>
              <Button type="button" variant="outline" onClick={() => onOpenTechnical(log.id)} className="gap-2">
                <FileSearch className="h-4 w-4" />
                View technical requests
              </Button>
            </div>
          </div>
        )}
      </SheetContent>
    </Sheet>
  );
}

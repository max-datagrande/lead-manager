import PageHeader from '@/components/page-header';
import { AttemptTabs } from '@/components/ping-post/dispatches/attempt-tabs';
import { showBreadcrumbs } from '@/components/ping-post/dispatches/breadcrumbs';
import { BuyerOutcomes } from '@/components/ping-post/dispatches/buyer-outcomes';
import { DispatchTimeline } from '@/components/ping-post/dispatches/dispatch-timeline';
import { StatusBadge } from '@/components/ping-post/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useModal } from '@/hooks/use-modal';
import AppLayout from '@/layouts/app-layout';
import type { DispatchAttemptSummary, LeadDispatch } from '@/types/ping-post';
import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import { Play, RefreshCw, ScrollText } from 'lucide-react';
import { useMemo } from 'react';
import { toast } from 'sonner';
import { route } from 'ziggy-js';

interface Field {
  id: number;
  name: string;
  label: string;
}

interface Props {
  dispatch: LeadDispatch;
  fields?: Field[];
  allAttempts?: DispatchAttemptSummary[];
}

const TERMINAL_STATUSES = ['sold', 'not_sold', 'error', 'timeout'] as const;

const DispatchesShow = ({ dispatch, fields = [], allAttempts = [] }: Props) => {
  const modal = useModal();

  const isTerminal = TERMINAL_STATUSES.includes(dispatch.status as (typeof TERMINAL_STATUSES)[number]);
  const hasRunningRetry = allAttempts.some((a) => a.status === 'running' && a.id !== dispatch.id);
  const isPendingValidation = dispatch.status === 'pending_validation';

  const snapshotRows = useMemo(() => {
    if (!dispatch.lead_snapshot || !fields.length) return [];
    const fieldMap = new Map(fields.map((f) => [String(f.id), f]));
    return Object.entries(dispatch.lead_snapshot).map(([fieldId, value]) => {
      const field = fieldMap.get(fieldId);
      return { label: field?.label ?? `Field #${fieldId}`, name: field?.name ?? fieldId, value };
    });
  }, [dispatch.lead_snapshot, fields]);

  const handleRetry = async () => {
    const confirmed = await modal.warnConfirm({
      title: 'Retry Dispatch',
      description: 'This will re-execute the entire workflow for this lead. Existing logs will be preserved.',
      consequences: [
        'The workflow will be queued and executed again with all its buyers',
        'A new dispatch attempt will be created (Attempt #' + (dispatch.attempt + 1) + ')',
        'All existing logs, buyer events, and timeline will remain intact',
      ],
      confirmText: 'Retry Dispatch',
      cancelText: 'Cancel',
      confirmCode: 'RETRY',
    });

    if (confirmed) {
      router.post(route('ping-post.dispatches.retry', dispatch.id));
    }
  };

  const handleForceRun = async () => {
    const confirmed = await modal.warnConfirm({
      title: 'Force Run Dispatch',
      description: 'This will skip the pending SMS/OTP validation and resume the dispatch immediately.',
      consequences: [
        'The lead quality challenge will NOT be verified — the lead proceeds as if it passed',
        'The dispatch will be queued and executed against the workflow buyers right away',
        'Your name will be recorded in the timeline as the user who forced the run',
      ],
      confirmText: 'Run Now',
      cancelText: 'Cancel',
      confirmCode: 'RUN',
    });

    if (!confirmed) return;

    try {
      const { data } = await axios.post(route('ping-post.dispatches.force-run', dispatch.id));
      toast.success(data.message ?? 'Dispatch resumed.');
      router.visit(route('ping-post.dispatches.show', dispatch.id), {
        preserveScroll: true,
        preserveState: false,
      });
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? 'Failed to force-run the dispatch.');
    }
  };

  return (
    <>
      <Head title={`Dispatch #${dispatch.id}`} />
      <div className="relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title={`Dispatch #${dispatch.id}`} description={`${dispatch.workflow?.name ?? 'Unknown workflow'} · ${dispatch.strategy_used}`}>
          <div className="flex items-center gap-3">
            <StatusBadge status={dispatch.status} variant="dispatch" className="text-sm" />
            {dispatch.attempt > 1 && (
              <Badge variant="outline" className="text-xs">
                Attempt #{dispatch.attempt}
              </Badge>
            )}
            <Button variant="outline" size="sm" asChild>
              <Link href={route('ping-post.dispatches.timeline', dispatch.id)}>
                <ScrollText className="mr-2 h-4 w-4" />
                Timeline Log
              </Link>
            </Button>
            {isTerminal && !hasRunningRetry && (
              <Button
                variant="outline"
                size="sm"
                onClick={handleRetry}
                className="border-amber-300 text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-400 dark:hover:bg-amber-950/50"
              >
                <RefreshCw className="mr-2 h-4 w-4" />
                Retry
              </Button>
            )}
            {isPendingValidation && (
              <Button
                variant="outline"
                size="sm"
                onClick={handleForceRun}
                className="border-amber-300 text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-400 dark:hover:bg-amber-950/50"
              >
                <Play className="mr-2 h-4 w-4" />
                Run
              </Button>
            )}
          </div>
        </PageHeader>

        <AttemptTabs attempts={allAttempts} currentDispatchId={dispatch.id} buildHref={(id) => route('ping-post.dispatches.show', id)} />

        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          {/* Meta + Snapshot */}
          <div className="space-y-6">
            <Card className="overflow-hidden">
              <CardHeader className="bg-muted">
                <CardTitle>Details</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3 pt-3 text-sm">
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
                  <Badge variant="outline" className="text-xs">
                    {dispatch.strategy_used}
                  </Badge>
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
                    <Badge variant="outline" className="border-amber-500 text-xs text-amber-600">
                      Activated
                    </Badge>
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
              <Card className="overflow-hidden">
                <CardHeader className="bg-muted">
                  <CardTitle>Lead Snapshot</CardTitle>
                  {dispatch.started_at && (
                    <p className="text-xs text-muted-foreground">Captured at {new Date(dispatch.started_at).toLocaleString()}</p>
                  )}
                </CardHeader>
                <CardContent className="max-h-[450px] space-y-3 overflow-y-auto pt-3">
                  {snapshotRows.map((row) => (
                    <div key={row.name} className="border-b pb-2 last:border-0 last:pb-0">
                      <p className="text-xs text-muted-foreground/50">{row.name}</p>
                      <p className="text- sm text-muted-foreground">{row.label}</p>
                      <p className="truncate text-base">{row.value || '—'}</p>
                    </div>
                  ))}
                </CardContent>
              </Card>
            )}
          </div>

          {/* Buyer Outcomes */}
          <div className="lg:col-span-3">
            <BuyerOutcomes dispatch={dispatch} />
          </div>

          {/* Timeline */}
          <div className="lg:col-span-2">
            <DispatchTimeline dispatch={dispatch} />
          </div>
        </div>
      </div>
    </>
  );
};

DispatchesShow.layout = (page: React.ReactNode & { props: { dispatch: LeadDispatch } }) => (
  <AppLayout children={page} breadcrumbs={showBreadcrumbs(page.props.dispatch)} />
);
export default DispatchesShow;

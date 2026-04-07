import { StatusBadge } from '@/components/ping-post/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { DispatchAttemptSummary } from '@/types/ping-post';
import { Link } from '@inertiajs/react';

interface Props {
  attempts: DispatchAttemptSummary[];
  currentDispatchId: number;
  buildHref: (attemptId: number) => string;
}

export function AttemptTabs({ attempts, currentDispatchId, buildHref }: Props) {
  if (attempts.length <= 1) return null;

  return (
    <Card className="overflow-hidden">
      <CardHeader className="bg-muted py-3">
        <CardTitle className="text-sm">Dispatch Attempts</CardTitle>
      </CardHeader>
      <CardContent className="flex flex-wrap gap-2 pt-3">
        {attempts.map((attempt) => {
          const isCurrent = attempt.id === currentDispatchId;
          return (
            <Button key={attempt.id} variant={isCurrent ? 'default' : 'outline'} size="sm" asChild={!isCurrent} disabled={isCurrent}>
              {isCurrent ? (
                <span className="flex items-center gap-2">
                  Attempt #{attempt.attempt}
                  <StatusBadge status={attempt.status} variant="dispatch" className="text-xs" />
                </span>
              ) : (
                <Link href={buildHref(attempt.id)} className="flex items-center gap-2">
                  Attempt #{attempt.attempt}
                  <StatusBadge status={attempt.status} variant="dispatch" className="text-xs" />
                </Link>
              )}
            </Button>
          );
        })}
      </CardContent>
    </Card>
  );
}

import { StatusBadge } from '@/components/ping-post/status-badge';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { LeadDispatch, PingResult, PostResult } from '@/types/ping-post';
import { ShieldOff, Trophy } from 'lucide-react';

const reasonColors: Record<string, string> = {
  ineligible: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
  cap_exceeded: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
  duplicate: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
  inactive: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
  no_config: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
  price_below_threshold: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
};

const formatReason = (s: string) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

function getBuyerOutcome(
  integrationId: number,
  pingResults: PingResult[],
  postResults: PostResult[],
  winnerId: number | null,
): { label: string; variant: 'ping' | 'post' | 'dispatch'; price?: number } {
  const post = postResults.find((p) => p.integration_id === integrationId);
  const ping = pingResults.find((p) => p.integration_id === integrationId);

  if (integrationId === winnerId && post) {
    return { label: 'sold', variant: 'dispatch', price: post.price_final ?? post.price_offered ?? undefined };
  }
  if (post) {
    return { label: post.status, variant: 'post' };
  }
  if (ping) {
    return { label: ping.status, variant: 'ping' };
  }
  return { label: 'unknown', variant: 'dispatch' };
}

interface Props {
  dispatch: LeadDispatch;
}

export function BuyerOutcomes({ dispatch }: Props) {
  const pingResults = dispatch.ping_results ?? [];
  const postResults = dispatch.post_results ?? [];
  const buyerEvents = dispatch.buyer_events ?? [];

  const participatedIds = new Set<number>([...pingResults.map((pr) => pr.integration_id), ...postResults.map((pr) => pr.integration_id)]);

  const filteredEvents = buyerEvents.filter((e) => !participatedIds.has(e.integration_id));

  if (participatedIds.size === 0 && filteredEvents.length === 0) {
    return null;
  }

  const getBuyerName = (id: number) => {
    const ping = pingResults.find((p) => p.integration_id === id);
    const post = postResults.find((p) => p.integration_id === id);
    return ping?.integration?.name ?? post?.integration?.name ?? `Buyer #${id}`;
  };

  return (
    <Card className="overflow-hidden">
      <CardHeader className="bg-muted">
        <CardTitle>Buyer Outcomes</CardTitle>
      </CardHeader>
      <CardContent className="space-y-2 pt-3">
        {/* Participated buyers */}
        {[...participatedIds].map((id) => {
          const outcome = getBuyerOutcome(id, pingResults, postResults, dispatch.winner_integration_id);
          return (
            <div key={`outcome-${id}`} className="flex items-center gap-3 rounded-md border px-4 py-2.5 text-sm">
              {dispatch.winner_integration_id === id && <Trophy className="h-4 w-4 text-yellow-500" />}
              <span className="font-medium">{getBuyerName(id)}</span>
              <StatusBadge status={outcome.label} variant={outcome.variant} />
              {outcome.price != null && <span className="font-medium text-green-600">${Number(outcome.price).toFixed(2)}</span>}
            </div>
          );
        })}

        {/* Filtered/skipped buyers */}
        {filteredEvents.map((event) => {
          const color = reasonColors[event.reason] ?? 'bg-gray-100 text-gray-600';
          return (
            <div key={`event-${event.id}`} className="flex items-center gap-3 rounded-md border border-dashed px-4 py-2.5 text-sm opacity-70">
              <ShieldOff className="h-4 w-4 text-muted-foreground" />
              <span className="font-medium">{event.integration?.name ?? `Buyer #${event.integration_id}`}</span>
              <Badge variant="outline" className={`border-0 font-medium ${color}`}>
                {formatReason(event.reason)}
              </Badge>
              {event.detail && <span className="text-xs text-muted-foreground">{event.detail}</span>}
            </div>
          );
        })}
      </CardContent>
    </Card>
  );
}

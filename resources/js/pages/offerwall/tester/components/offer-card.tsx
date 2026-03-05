import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import type { Offer } from '../constants';

interface OfferCardProps {
  offer: Offer;
  index: number;
}

function getCpcColor(cpc: number): string {
  if (cpc >= 2) return 'text-green-600 dark:text-green-400';
  if (cpc >= 1) return 'text-yellow-600 dark:text-yellow-400';
  if (cpc > 0) return 'text-orange-600 dark:text-orange-400';
  return 'text-muted-foreground';
}

export default function OfferCard({ offer, index }: OfferCardProps) {
  const cpcValue = typeof offer.cpc === 'string' ? parseFloat(offer.cpc) : (offer.cpc ?? 0);
  const title = (offer.title || offer.name || offer.display_name || `Offer #${index + 1}`) as string;
  const description = (offer.description || offer.desc) as string | undefined;

  return (
    <Card
      className="gap-0 overflow-hidden transition-shadow animate-in fade-in slide-in-from-bottom-2 hover:shadow-md"
      style={{ animationDelay: `${index * 75}ms`, animationFillMode: 'both' }}
    >
      <CardHeader className="gap-0">
        <div className="flex items-start justify-between gap-4">
          <Badge variant="secondary" className="shrink-0">
            #{offer.pos ?? index + 1}
          </Badge>
          <span className={`text-2xl font-bold tabular-nums ${getCpcColor(cpcValue)}`}>${cpcValue.toFixed(2)}</span>
        </div>
      </CardHeader>
      <CardContent className="p-4">
        <h4 className="line-clamp-2 text-lg leading-tight font-semibold">{title}</h4>
        {description && <p className="mt-2 line-clamp-2 text-sm text-muted-foreground">{description}</p>}
        {offer.click_url && (
          <p className="mt-2 truncate text-xs text-muted-foreground" title={offer.click_url as string}>
            {offer.click_url as string}
          </p>
        )}
      </CardContent>
    </Card>
  );
}

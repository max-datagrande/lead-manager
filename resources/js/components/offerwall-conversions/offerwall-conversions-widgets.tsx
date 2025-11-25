import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { DollarSign } from 'lucide-react';

interface OfferwallConversionsWidgetsProps {
  totalPayout: number;
  isLoading: boolean;
}

export function OfferwallConversionsWidgets({ totalPayout, isLoading = false }: OfferwallConversionsWidgetsProps) {
  const formattedPayout = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(totalPayout);

  return (
    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-sm font-medium">Total Payout</CardTitle>
          <DollarSign className="h-4 w-4 text-muted-foreground" />
        </CardHeader>
        <CardContent>
          {isLoading ? <Skeleton className="h-8 w-[100px]" /> : <div className="text-2xl font-bold">{formattedPayout}</div>}
          <p className="text-xs text-muted-foreground">Total revenue from current search</p>
        </CardContent>
      </Card>
    </div>
  );
}

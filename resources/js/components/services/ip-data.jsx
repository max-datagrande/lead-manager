import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useToast } from '@/hooks/use-toast';
import { cn } from '@/lib/utils';
import { usePage } from '@inertiajs/react';
import { Activity } from 'lucide-react';
import { useEffect, useState } from 'react';

const CardStats = ({ className }) => {
  const { props } = usePage();
  const { token } = props.app.services.ipapi;
  const [isLoading, setIsLoading] = useState(true);
  const [remaininTokens, setRemainingTokens] = useState(null);
  const { addMessage } = useToast();

  const getData = async () => {
    setIsLoading(true);
    addMessage('Fetching IP API quota...', 'info');
    try {
      const response = await fetch(`https://ipapi.co/quota/?key=${token}`);
      if (!response.ok) {
        throw new Error('Failed to fetch IP API quota');
      }
      const data = await response.json();
      const availableTokens = data.available;
      setRemainingTokens(availableTokens);
    } catch (error) {
      addMessage('Failed to fetch IP API quota', 'error');
      console.error('Error fetching IP API quota:', error);
      setRemainingTokens(null);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    getData();
  }, []);

  return (
    <Card className={cn(className, 'justify-between')}>
      <CardHeader>
        <CardTitle>IP API</CardTitle>
        <CardDescription>Pending Requests</CardDescription>
        <CardAction>
          <Activity className="text-xl" />
        </CardAction>
      </CardHeader>
      <CardContent>
        <ContentStats remaininTokens={remaininTokens} isLoading={isLoading} />
      </CardContent>
    </Card>
  );
};
function ContentStats({ remaininTokens, isLoading }) {
  const TOTAL_TOKENS = 500000;
  if (isLoading) {
    return <div data-slot="skeleton" className="h-8 w-20 animate-pulse rounded-md bg-accent"></div>;
  }
  if (remaininTokens === null) {
    return <p className="text-xl font-medium">No data</p>;
  }
  const percentage = Math.round((remaininTokens / TOTAL_TOKENS) * 100);
  return (
    <>
      <p className="text-2xl font-medium">{remaininTokens.toLocaleString()}</p>
      <small className="text-sm text-muted-foreground">{percentage}% tokens remaining</small>
    </>
  );
}

export { CardStats };

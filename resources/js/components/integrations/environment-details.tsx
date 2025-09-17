import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { useToast } from '@/hooks/use-toast';
import { getCookie } from '@/utils/navigator';
import { PlayCircle } from 'lucide-react';
import { useState } from 'react';
import { route } from 'ziggy-js';
import { DescriptionList, DescriptionListItem } from './description-list-item';
import JsonViewer from '@/components/ui/json-viewer';
import { Badge } from '@/components/ui/badge';

export function EnvironmentDetails({ integrationId, env }) {
  const [testResult, setTestResult] = useState(null);
  const [isTesting, setIsTesting] = useState(false);
  const [error, setError] = useState(null);
  const { addMessage } = useToast();

  const handleTest = async (environmentId: string) => {
    setIsTesting(true);
    setTestResult(null);
    try {
      const endpoint = route('integrations.test', { integration: integrationId, environment: environmentId });
      console.log('Endpoint:', endpoint);
      const csrfToken = getCookie('XSRF-TOKEN');
      console.log('csrfToken:', csrfToken);
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
        },
      });
      const result = await response.json();
      if (!response.ok) {
        throw new Error(result.error || 'Test failed');
      }
      setTestResult(result);
    } catch (error) {
      setTestResult({ error: error.message });
      addMessage(error.message, 'error');
    } finally {
      setIsTesting(false);
    }
  };

  return (
    <Sheet>
      <Card className="gap-2">
        <CardHeader className="flex flex-row items-center justify-between">
          <div className="flex flex-col gap-2">
            <CardTitle className="capitalize">{env.environment}</CardTitle>
            <CardDescription>Configuration for {env.environment} environment.</CardDescription>
          </div>
          <CardAction className="self-center">
            <SheetTrigger asChild>
              <Button variant="black" size="sm" onClick={() => handleTest(env.id)} disabled={isTesting}>
                <PlayCircle className="h-4 w-4" />
                {isTesting ? 'Running...' : 'Test'}
              </Button>
            </SheetTrigger>
          </CardAction>
        </CardHeader>
        <CardContent>
          <DescriptionList>
            <DescriptionListItem term="URL">{env.url}</DescriptionListItem>
            <DescriptionListItem term="Method">{env.method}</DescriptionListItem>
          </DescriptionList>
        </CardContent>
      </Card>
      <SheetContent className="w-[400px] sm:w-[540px]">
        <SheetHeader>
          <SheetTitle>Test Result</SheetTitle>
          <SheetDescription>Result of the API request test.</SheetDescription>
        </SheetHeader>
        <div className="grid flex-1 auto-rows-min gap-6 px-4">
          {isTesting && <p>Loading...</p>}
          {testResult && (
            <JsonViewer
            data={JSON.stringify(testResult, null, 2)}
            title={
              <div className="flex items-center gap-2">
                <span>Response Data</span>
              </div>
            }
          />
          )}
        </div>
        <SheetFooter>
          <SheetClose asChild>
            <Button variant="outline">Close</Button>
          </SheetClose>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}

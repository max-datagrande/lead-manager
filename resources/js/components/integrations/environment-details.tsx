import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import JsonViewer from '@/components/ui/json-viewer';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { useToast } from '@/hooks/use-toast';
import { getCookie } from '@/utils/navigator';
import { PlayCircle } from 'lucide-react';
import { useState } from 'react';
import { route } from 'ziggy-js';
import { DescriptionList, DescriptionListItem } from './description-list-item';
import { Clock } from 'lucide-react';

export function EnvironmentDetails({ integrationId, env }) {
  const [testResult, setTestResult] = useState(null);
  const [isTesting, setIsTesting] = useState(false);
  const { addMessage } = useToast();

  const handleTest = async (environmentId: string) => {
    setIsTesting(true);
    setTestResult(null);
    try {
      const endpoint = route('integrations.test', { integration: integrationId, environment: environmentId });
      const csrfToken = getCookie('XSRF-TOKEN');
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'x-xsrf-token': csrfToken,
          'x-requested-with': 'XMLHttpRequest',
          accept: 'application/json',
        },
      });

      if (!response.ok) {
        const statusCode = response.status;
        const statusText = (response.statusText || '').toLowerCase().length > 0 ? response.statusText : null;
        const contentType = response.headers.get('content-type');
        const isJson = contentType.includes('application/json');
        const result = isJson ? await response.json() : { error: 'Bad Response: ' + statusCode, body: await response.text() };
        const shortMessage = result.error ?? result.message ?? statusText ?? 'Bad Response: ' + statusCode;
        throw new Error(shortMessage, { cause: result });
      }
      const result = await response.json().catch((error) => {
        return { error: error.message };
      });
      setTestResult(result);
    } catch (error) {
      const cause = error.cause ?? { error: error.message };
      addMessage(error.message, 'error');
      setTestResult(cause);
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
      <SheetContent className="w-[400px] sm:w-[540px] gap-0">
        <SheetHeader>
          <SheetTitle>Test Result</SheetTitle>
          <SheetDescription>Result of the API request test.</SheetDescription>
        </SheetHeader>
        <div className="flex-1 gap-6 px-4 flex flex-col overflow-auto">
          {isTesting && (
            <div className="flex items-center justify-center p-8">
              <div className="flex items-center gap-2">
                <Clock className="h-4 w-4 animate-spin" />
                <span>Loading test result...</span>
              </div>
            </div>
          )}
          {testResult && (
            <JsonViewer
              data={JSON.stringify(testResult, null, 2)}
              className='flex-1'
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

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { PlayCircle } from 'lucide-react';
import { useState } from 'react';
import { route } from 'ziggy-js';
import { DescriptionListItem } from './description-list-item';

export function EnvironmentDetails({ integrationId, env }) {
  const [testResult, setTestResult] = useState(null);
  const [isTesting, setIsTesting] = useState(false);

  const handleTest = async (environmentId) => {
    setIsTesting(true);
    setTestResult(null);
    try {
      const response = await fetch(route('integrations.test', { integration: integrationId, environment: environmentId }), {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        },
      });
      const result = await response.json();
      if (!response.ok) {
        throw new Error(result.error || 'Test failed');
      }
      setTestResult(result);
    } catch (error) {
      setTestResult({ error: error.message });
    }
    setIsTesting(false);
  };

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <div>
          <CardTitle className="capitalize">{env.environment}</CardTitle>
          <CardDescription>Configuration for {env.environment} environment.</CardDescription>
        </div>
        <Sheet>
          <SheetTrigger asChild>
            <Button variant="outline" size="sm" onClick={() => handleTest(env.id)} disabled={isTesting}>
              <PlayCircle className="mr-2 h-4 w-4" />
              {isTesting ? 'Running...' : 'Run Test'}
            </Button>
          </SheetTrigger>
          <SheetContent className="w-[400px] sm:w-[540px]">
            <SheetHeader>
              <SheetTitle>Test Result</SheetTitle>
              <SheetDescription>Result of the API request test.</SheetDescription>
            </SheetHeader>
            <div className="py-4">
              {isTesting && <p>Loading...</p>}
              {testResult && (
                <pre className="mt-2 w-full overflow-auto rounded-md bg-slate-950 p-4 text-white">
                  <code className="text-sm">{JSON.stringify(testResult, null, 2)}</code>
                </pre>
              )}
            </div>
          </SheetContent>
        </Sheet>
      </CardHeader>
      <CardContent>
        <dl>
          <DescriptionListItem term="URL">{env.url}</DescriptionListItem>
          <DescriptionListItem term="Method">{env.method}</DescriptionListItem>
          {/* Add more details if necessary */}
        </dl>
      </CardContent>
    </Card>
  );
}

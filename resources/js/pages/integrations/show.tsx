import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { PlayCircle } from 'lucide-react';
import { useState } from 'react';
import { route } from 'ziggy-js';
import { IntegrationDB } from '@/types/integrations';

interface Props {
  integration: IntegrationDB;
}

const DescriptionListItem = ({ term, children }) => (
  <div className="grid grid-cols-3 gap-4 py-2">
    <dt className="text-sm font-medium text-gray-500">{term}</dt>
    <dd className="col-span-2 text-sm text-gray-900">{children}</dd>
  </div>
);

const ShowIntegration = ({ integration }) => {
  const [testResult, setTestResult] = useState(null);
  const [isTesting, setIsTesting] = useState(false);

  const handleTest = async (environmentId) => {
    setIsTesting(true);
    setTestResult(null);
    try {
      const response = await fetch(route('integrations.test', { integration: integration.id, environment: environmentId }), {
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

  const EnvironmentDetails = ({ env }) => (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <div>
          <CardTitle className="capitalize">{env.environment}</CardTitle>
          <CardDescription>Live, production-ready configuration.</CardDescription>
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
          {/* Agrega más detalles si es necesario */}
        </dl>
      </CardContent>
    </Card>
  );

  return (
    <>
      <Head title={integration.name} />
      <div className="relative flex-1 space-y-6 overflow-auto p-6 md:p-8">
        <PageHeader title={integration.name} description={`Details for the "${integration.name}" integration.`}>
          <Link href={route('integrations.edit', integration.id)}>
            <Button>Edit</Button>
          </Link>
        </PageHeader>

        <div className="space-y-6">
          {integration.environments.map((env) => (
            <EnvironmentDetails key={env.id} env={env} />
          ))}
        </div>
      </div>
    </>
  );
};

ShowIntegration.layout = (page: React.ReactNode & { props: Props }) => {
  const { integration } = page.props;
  const breadcrumbs = [
    {
      title: 'Integrations',
      href: route('integrations.index'),
    },
    {
      title: integration.name,
      href: route('integrations.show', integration.id),
    },
  ];
  return <AppLayout children={page} breadcrumbs={breadcrumbs} />;
};

export default ShowIntegration;

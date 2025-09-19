import { IntegrationForm, createBreadcrumbs } from '@/components/integrations';
import PageHeader from '@/components/page-header';
import { IntegrationsProvider } from '@/context/integrations-provider.jsx';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

const CreateIntegrationPage = () => (
  <IntegrationsProvider integration={null}>
    <Head title="Create Integration" />
    <div className="relative flex-1 space-y-6 overflow-auto p-6 md:p-8">
      <PageHeader title="Create Integration" description="Set up a new integration and its environments." />
      <IntegrationForm />
    </div>
  </IntegrationsProvider>
);

CreateIntegrationPage.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={createBreadcrumbs} />;

export default CreateIntegrationPage;

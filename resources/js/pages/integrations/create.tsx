import { IntegrationForm, createBreadcrumbs } from '@/components/integrations/index';
import PageHeader from '@/components/page-header';
import { IntegrationsProvider } from '@/context/integrations-provider.jsx';
import AppLayout from '@/layouts/app-layout';
import { IntegrationDB } from '@/types/integrations';
import { Head } from '@inertiajs/react';
interface Props {
  integration: IntegrationDB;
}
const CreateIntegrationPage = ({ integration = null, companies, fields }: { integration: IntegrationDB; companies: any[]; fields: any[] }) => (
  <IntegrationsProvider integration={integration}>
    <Head title="Create Integration" />
    <div className="relative flex-1 space-y-6 overflow-auto p-6 md:p-8">
      <PageHeader title="Create Integration" description="Set up a new integration and its environments." />
      <IntegrationForm companies={companies} fields={fields} />
    </div>
  </IntegrationsProvider>
);

CreateIntegrationPage.layout = (page: React.ReactNode & { props: Props }) => <AppLayout children={page} breadcrumbs={createBreadcrumbs} />;

export default CreateIntegrationPage;

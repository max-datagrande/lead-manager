import { IntegrationForm, editBreadcrumbs } from '@/components/integrations/index';
import PageHeader from '@/components/page-header';
import { IntegrationsProvider } from '@/context/integrations-provider.jsx';
import AppLayout from '@/layouts/app-layout';
import { IntegrationDB } from '@/types/integrations';
import { Head } from '@inertiajs/react';
interface Props {
  integration: IntegrationDB;
}
const EditIntegrationPage = ({ integration }: { integration: IntegrationDB }) => (
  <IntegrationsProvider integration={integration}>
    <Head title={`Edit ${integration.name}`} />
    <div className="relative flex-1 space-y-6 overflow-auto p-6 md:p-8">
      <PageHeader title="Edit Integration" description={`Editing ${integration.name}.`} />
      <IntegrationForm />
    </div>
  </IntegrationsProvider>
);

EditIntegrationPage.layout = (page: React.ReactNode & { props: Props }) => {
  const breadcrumbs = editBreadcrumbs(page.props.integration);
  return <AppLayout children={page} breadcrumbs={breadcrumbs} />;
};

export default EditIntegrationPage;

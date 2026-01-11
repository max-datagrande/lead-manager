import { EnvironmentDetails } from '@/components/integrations';
import { showBreadcrumbs } from '@/components/integrations/breadcrumbs'; // Assuming a new breadcrumbs file
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { IntegrationDB } from '@/types/integrations';
import { Head, Link } from '@inertiajs/react';
interface Props {
  integration: IntegrationDB;
}
const ShowIntegration = ({ integration }: Props) => {
  return (
    <>
      <Head title={`Integration | ${integration.id}`} />
      <div className="relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader
          smallText={`Integration ID: ${integration.id} - Type: ${integration.type}`}
          title={integration.name}
        >
          <Link href={route('integrations.edit', integration.id)}>
            <Button>Edit</Button>
          </Link>
        </PageHeader>
        <div className="space-y-6">
          {integration.environments.map((env) => (
            <EnvironmentDetails key={env.id} integrationId={integration.id} env={env} />
          ))}
        </div>
      </div>
    </>
  );
};

ShowIntegration.layout = (page: React.ReactNode & { props: Props }) => {
  const { integration } = page.props;
  const breadcrumbs = showBreadcrumbs(integration);
  return <AppLayout children={page} breadcrumbs={breadcrumbs} />;
};

export default ShowIntegration;

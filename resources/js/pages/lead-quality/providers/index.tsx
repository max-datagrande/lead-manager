import { ProvidersActions, TableProviders } from '@/components/lead-quality/providers';
import PageHeader from '@/components/page-header';
import { LeadQualityProvidersProvider } from '@/context/lead-quality-providers-provider';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ProviderRow, ProviderStatusOption, ProviderTypeOption } from '@/types/models/lead-quality';
import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Lead Quality', href: route('lead-quality.index') },
  { title: 'Providers', href: route('lead-quality.providers.index') },
];

interface Props {
  providers: ProviderRow[];
  provider_types: ProviderTypeOption[];
  statuses: ProviderStatusOption[];
}

const Index = ({ providers }: Props) => {
  return (
    <LeadQualityProvidersProvider>
      <Head title="Lead Quality — Providers" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Providers" description="External services used by Lead Quality validation rules (Twilio Verify, IPQS, etc.).">
          <ProvidersActions />
        </PageHeader>
        <TableProviders entries={providers} />
      </div>
    </LeadQualityProvidersProvider>
  );
};

Index.layout = (page: ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;

export default Index;

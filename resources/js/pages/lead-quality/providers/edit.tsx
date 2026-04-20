import { ProviderForm, type ProviderFormData } from '@/components/lead-quality/providers';
import PageHeader from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EnvironmentOption, ProviderDetail, ProviderStatusOption, ProviderTypeOption } from '@/types/models/lead-quality';
import { Head, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Lead Quality', href: route('lead-quality.providers.index') },
  { title: 'Providers', href: route('lead-quality.providers.index') },
  { title: 'Edit', href: '#' },
];

interface Props {
  provider: ProviderDetail;
  provider_types: ProviderTypeOption[];
  statuses: ProviderStatusOption[];
  environments: EnvironmentOption[];
}

const Edit = ({ provider, provider_types, statuses, environments }: Props) => {
  const { data, setData, put, processing, errors } = useForm<ProviderFormData>({
    name: provider.name,
    type: provider.type,
    status: provider.status,
    is_enabled: provider.is_enabled,
    environment: provider.environment,
    // On edit, start with empty creds so blank fields don't overwrite existing values.
    // The backend merges incoming with existing. Settings not editable from admin UI in V1.
    credentials: {},
    notes: provider.notes ?? '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(route('lead-quality.providers.update', provider.id));
  };

  return (
    <>
      <Head title={`Edit — ${provider.name}`} />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title={provider.name} description="Update credentials, status, and settings." />
        <ProviderForm
          data={data}
          setData={setData as (key: string, value: unknown) => void}
          errors={errors as Record<string, string>}
          processing={processing}
          providerTypes={provider_types}
          statuses={statuses}
          environments={environments}
          onSubmit={handleSubmit}
          isEdit
          providerId={provider.id}
        />
      </div>
    </>
  );
};

Edit.layout = (page: ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;

export default Edit;

import { ProviderForm, type ProviderFormData } from '@/components/lead-quality/providers';
import PageHeader from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { EnvironmentOption, ProviderStatusOption, ProviderTypeOption } from '@/types/models/lead-quality';
import { Head, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Lead Quality', href: route('lead-quality.index') },
  { title: 'Providers', href: route('lead-quality.providers.index') },
  { title: 'New', href: route('lead-quality.providers.create') },
];

interface Props {
  provider_types: ProviderTypeOption[];
  statuses: ProviderStatusOption[];
  environments: EnvironmentOption[];
}

const Create = ({ provider_types, statuses, environments }: Props) => {
  const { data, setData, post, processing, errors } = useForm<ProviderFormData>({
    name: '',
    type: provider_types.find((t) => t.is_implemented)?.value ?? '',
    status: 'inactive',
    is_enabled: false,
    environment: 'sandbox',
    credentials: {},
    friendly_name: '',
    notes: '',
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post(route('lead-quality.providers.store'));
  };

  return (
    <>
      <Head title="New provider" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="New provider" description="Register an external service used by validation rules." />
        <ProviderForm
          data={data}
          setData={setData as (key: string, value: unknown) => void}
          errors={errors as Record<string, string>}
          processing={processing}
          providerTypes={provider_types}
          statuses={statuses}
          environments={environments}
          onSubmit={handleSubmit}
        />
      </div>
    </>
  );
};

Create.layout = (page: ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;

export default Create;

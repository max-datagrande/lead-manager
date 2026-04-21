import { RuleForm, type RuleFormData } from '@/components/lead-quality/rules';
import PageHeader from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { BuyerOption, ProviderOption, RuleStatusOption, ValidationTypeOption } from '@/types/models/lead-quality';
import { Head, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Lead Quality', href: route('lead-quality.index') },
  { title: 'Validation Rules', href: route('lead-quality.validation-rules.index') },
  { title: 'New', href: route('lead-quality.validation-rules.create') },
];

interface Props {
  validation_types: ValidationTypeOption[];
  statuses: RuleStatusOption[];
  providers: ProviderOption[];
  buyers: BuyerOption[];
}

const Create = ({ validation_types, statuses, providers, buyers }: Props) => {
  const firstUsableProvider = providers.find((p) => p.is_usable)?.id ?? '';

  const { data, setData, post, processing, errors } = useForm<RuleFormData>({
    name: '',
    validation_type: validation_types[0]?.value ?? '',
    provider_id: firstUsableProvider,
    status: 'draft',
    is_enabled: false,
    description: '',
    settings: {
      channel: 'sms',
      otp_length: 6,
      ttl: 600,
      max_attempts: 3,
      validity_window: 15,
    },
    priority: 100,
    buyer_ids: [],
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post(route('lead-quality.validation-rules.store'));
  };

  return (
    <>
      <Head title="New validation rule" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="New validation rule" description="Define what validation should happen and which buyers require it." />
        <RuleForm
          data={data}
          setData={setData as (key: string, value: unknown) => void}
          errors={errors as Record<string, string>}
          processing={processing}
          validationTypes={validation_types}
          statuses={statuses}
          providers={providers}
          buyers={buyers}
          onSubmit={handleSubmit}
        />
      </div>
    </>
  );
};

Create.layout = (page: ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;

export default Create;

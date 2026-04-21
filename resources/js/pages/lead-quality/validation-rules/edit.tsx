import { RuleForm, type RuleFormData } from '@/components/lead-quality/rules';
import type { RuleSettings } from '@/components/lead-quality/rules/rule-settings';
import PageHeader from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { BuyerOption, ProviderOption, RuleDetail, RuleStatusOption, ValidationTypeOption } from '@/types/models/lead-quality';
import { Head, useForm } from '@inertiajs/react';
import type { ReactNode } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Lead Quality', href: route('lead-quality.index') },
  { title: 'Validation Rules', href: route('lead-quality.validation-rules.index') },
  { title: 'Edit', href: '#' },
];

interface Props {
  rule: RuleDetail;
  validation_types: ValidationTypeOption[];
  statuses: RuleStatusOption[];
  providers: ProviderOption[];
  buyers: BuyerOption[];
}

const Edit = ({ rule, validation_types, statuses, providers, buyers }: Props) => {
  const { data, setData, put, processing, errors } = useForm<RuleFormData>({
    name: rule.name,
    validation_type: rule.validation_type,
    provider_id: rule.provider_id,
    status: rule.status,
    is_enabled: rule.is_enabled,
    description: rule.description ?? '',
    settings: (rule.settings ?? {}) as RuleSettings,
    priority: rule.priority,
    buyer_ids: rule.buyer_ids ?? [],
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put(route('lead-quality.validation-rules.update', rule.id));
  };

  return (
    <>
      <Head title={`Edit — ${rule.name}`} />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title={rule.name} description="Update the rule's parameters and buyer assignments." />
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
          isEdit
        />
      </div>
    </>
  );
};

Edit.layout = (page: ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;

export default Edit;

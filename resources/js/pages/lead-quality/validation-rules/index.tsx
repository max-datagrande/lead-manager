import { RulesActions, TableRules } from '@/components/lead-quality/rules';
import PageHeader from '@/components/page-header';
import { LeadQualityRulesProvider } from '@/context/lead-quality-rules-provider';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { RuleRow, RuleStatusOption, ValidationTypeOption } from '@/types/models/lead-quality';
import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Lead Quality', href: route('lead-quality.validation-rules.index') },
  { title: 'Validation Rules', href: route('lead-quality.validation-rules.index') },
];

interface Props {
  rules: RuleRow[];
  validation_types: ValidationTypeOption[];
  statuses: RuleStatusOption[];
}

const Index = ({ rules }: Props) => {
  return (
    <LeadQualityRulesProvider>
      <Head title="Lead Quality — Validation Rules" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Validation Rules" description="Centralize validation requirements and associate them with one or many buyers.">
          <RulesActions />
        </PageHeader>
        <TableRules entries={rules} />
      </div>
    </LeadQualityRulesProvider>
  );
};

Index.layout = (page: ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;

export default Index;

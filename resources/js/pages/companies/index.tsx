import { CompaniesActions, TableCompanies } from '@/components/companies/index';
import PageHeader from '@/components/page-header';
import { CompaniesProvider } from '@/context/companies-provider';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { route } from 'ziggy-js';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Companies',
    href: route('companies.index'),
  },
];

type Company = {
  id: number;
  name: string;
  contact_email: string;
  contact_phone: string;
  contact_name: string;
  active: boolean;
  user_id: number;
  updated_user_id: number;
  created_at: string;
  updated_at: string;
};
interface IndexProps {
  rows: Company[];
  state: {
    sort: string;
  };
}

const Index = ({ rows }: IndexProps) => {
  return (
    <CompaniesProvider>
      <Head title="Companies" />
      <div className="slide-in-up relative flex-1 space-y-6 overflow-auto p-6 md:p-8">
        <PageHeader title="Companies" description="Manage companies.">
          <CompaniesActions />
        </PageHeader>
        <TableCompanies entries={rows} />
      </div>
    </CompaniesProvider>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

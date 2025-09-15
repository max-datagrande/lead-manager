import PageHeader from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { route } from 'ziggy-js';
import {TableFields} from '@/components/fields/index';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Fields',
    href: route('fields.index'),
  },
];

type Field = {
  id: number;
  name: string;
  label: string;
  validation_rules: string;
  user_id: number;
  updated_user_id: number;
  created_at: string;
  updated_at: string;
}
interface IndexProps {
  rows: Field[];
  state: {
    sort: string;
  };
}

const Index = ({ rows, state }: IndexProps) => {
  return (
    <>
      <Head title="Fields" />
      <div className="slide-in-up relative flex-1 space-y-6 overflow-auto p-6 md:p-8">
        <PageHeader title="Fields" description="Manage fields to our forms." />
        <TableFields fields={rows} state={state} />
      </div>
    </>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

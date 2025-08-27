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
/* {
  "id": 27,
  "name": "vehicle_model",
  "label": "Vehicle Model",
  "validation_rules": null,
  "user_id": 2,
  "updated_user_id": 2,
  "created_at": "2025-07-27T22:08:00.000000Z",
  "updated_at": "2025-07-27T22:08:00.000000Z"
} */
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
    search: string;
    sort: string;
  };
}

const Index = ({ rows }: IndexProps) => {
  return (
    <>
      <Head title="Fields" />
      <div className="slide-in-up relative flex-1 space-y-6 overflow-auto p-6 md:p-8">
        <PageHeader title="Fields" description="Manage fields to our forms." />
        <TableFields fields={rows} />
      </div>
    </>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

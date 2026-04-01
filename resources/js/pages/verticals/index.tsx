import { VerticalsActions, TableVerticals } from '@/components/verticals';
import PageHeader from '@/components/page-header';
import { VerticalsProvider } from '@/context/verticals-provider';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { route } from 'ziggy-js';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Verticals',
    href: route('verticals.index'),
  },
];

type Vertical = {
  id: number;
  name: string;
  description: string | null;
  active: boolean;
  user_id: number;
  updated_user_id: number | null;
  created_at: string;
  updated_at: string;
};

interface IndexProps {
  verticals: Vertical[];
}

const Index = ({ verticals }: IndexProps) => {
  return (
    <VerticalsProvider>
      <Head title="Verticals" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Verticals" description="Manage verticals.">
          <VerticalsActions />
        </PageHeader>
        <TableVerticals entries={verticals} />
      </div>
    </VerticalsProvider>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;
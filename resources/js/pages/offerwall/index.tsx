import { OfferwallActions, TableOfferwalls } from '@/components/offerwall';
import PageHeader from '@/components/page-header';
import { OfferwallProvider } from '@/context/offerwall/provider';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { route } from 'ziggy-js';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Offerwalls',
    href: route('offerwall.index'),
  },
];

interface IndexProps {
  rows: []; //This will be the offerwall mixes
  state: {
    sort: string;
  };
}

const Index = ({ rows, state }: IndexProps) => {
  return (
    <OfferwallProvider initialState={state}>
      <Head title="Offerwalls" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Offerwalls" description="Manage offerwall mixes.">
          <OfferwallActions />
        </PageHeader>
        <TableOfferwalls entries={rows} />
      </div>
    </OfferwallProvider>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

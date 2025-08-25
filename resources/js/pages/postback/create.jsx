import PageHeader from '@/components/page-header';
import { TableVisitors } from '@/components/visitors';
import { VisitorsProvider } from '@/context/visitors-provider';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { route } from 'ziggy-js';

const breadcrumbs = [
  {
    title: 'Visitors',
    href: route('visitors.index'),
  },
];
/**
 * Index Page Component
 *
 * @description Página principal para mostrar visitantes con tabla paginada
 */
const Index = () => {
  return (
    <VisitorsProvider>
      <Head title="" />
      <div className="slide-in-up relative flex-1 space-y-6 overflow-auto p-6 md:p-8">
        <PageHeader title="Visitors" description="Manage visitors from our landing pages." />
        <TableVisitors visitors={rows.data} />
      </div>
    </VisitorsProvider>
  );
};

Index.layout = (page) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

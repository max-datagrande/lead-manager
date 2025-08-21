import PageHeader from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { Head, usePage } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { TableVisitors, NoData } from '@/components/visitors';

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
  const page = usePage();
  const { visitors } = page.props;

  return (
    <>
      <Head title="Visitors" />
      <div className="slide-in-up relative flex-1 space-y-6 overflow-auto p-6 md:p-8">
        <PageHeader title="Visitors" description="Manage visitors from our landing pages." />
        <TableVisitors data={visitors} />
      </div>
    </>
  );
};

Index.layout = (page) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

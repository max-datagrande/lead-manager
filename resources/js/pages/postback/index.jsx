import PageHeader from '@/components/page-header';
import { TablePostbacks } from '@/components/postback/table-postbacks';
import { PostbackProvider } from '@/context/postback-provider';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { route } from 'ziggy-js';

const breadcrumbs = [
  {
    title: 'Postbacks',
    href: route('postbacks.index'),
  },
];
/**
 * Index Page Component
 *
 * @description Página principal para mostrar visitantes con tabla paginada
 */
const Index = ({ rows }) => {
  return (
    <PostbackProvider>
      <Head title="Postbacks" />
      <div className="slide-in-up relative flex-1 space-y-6 overflow-auto p-6 md:p-8">
        <PageHeader title="Postbacks" description="Check the status of your postbacks." />
        <TablePostbacks postbacks={rows.data} />
      </div>
    </PostbackProvider>
  );
};

Index.layout = (page) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

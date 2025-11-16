import PageHeader from '@/components/page-header';
import { TableVisitors } from '@/components/visitors';
import { VisitorsProvider } from '@/context/visitors-provider';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, DatatablePageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { route } from 'ziggy-js';

const breadcrumbs: BreadcrumbItem[] = [
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
type Visitor = {
  id: string;
  fingerprint: string;
  visit_date: string;
  visit_count: number;
  ip_address: string;
  device_type: string;
  browser: string;
  os: string;
  country_code: string;
  state: string;
  city: string;
  utm_source: string;
  utm_medium: string;
  host: string;
  path_visited: string;
  referrer: string;
  is_bot: boolean;
  created_at: string;
  updated_at: string;
}

interface IndexProps extends DatatablePageProps<Visitor> {
  // No necesitas definir nada más, hereda todo de DatatablePageProps<Visitor>
}
const Index = ({ rows, meta, state, data }: IndexProps) => {
  return (
    <VisitorsProvider initialState={state}>
      <Head title="Visitors" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Visitors" description="Manage visitors from our landing pages." />
        <TableVisitors entries={rows.data} meta={meta} data={data} />
      </div>
    </VisitorsProvider>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

import PageHeader from '@/components/page-header';
import { TablePostbacks, type Postback } from '@/components/postback/index';
import { PostbackProvider } from '@/context/postback-provider';
import AppLayout from '@/layouts/app-layout';
import { PageLink } from '@/types';
import { Head } from '@inertiajs/react';
import { type ReactNode } from 'react';

const breadcrumbs = [
  {
    title: 'Postbacks',
    href: '/postbacks',
  },
];
interface IndexProps {
  rows: {
    data: Postback[];
    current_page: number;
    first_page_url: string;
    from: number;
    last_page: number;
    last_page_url: string;
    links: PageLink[];
    next_page_url: string;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number;
    total: number;
  };
  meta: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
    from: number;
    to: number;
  };
  state: {
    search?: string;
    sort?: string;
    filters?: any[];
    page?: number;
    per_page?: number;
  };
  data: {
    vendorFilterOptions: Array<{ value: string; label: string }>;
    statusFilterOptions: Array<{ label: string; value: string; iconName: string }>;
  };
}
const Index = ({ rows, meta, state, data }: IndexProps) => {
  return (
    <PostbackProvider initialState={state}>
      <Head title="Postbacks" />
      <div className="slide-in-up relative flex-1 space-y-6 overflow-auto p-6 md:p-8">
        <PageHeader title="Postbacks" description="Check the status of your postbacks." />
        <TablePostbacks entries={rows.data} meta={meta} data={data} />
      </div>
    </PostbackProvider>
  );
};

Index.layout = (page: ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

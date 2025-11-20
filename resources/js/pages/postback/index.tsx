import { ServerTable } from '@/components/data-table/server-table';
import PageHeader from '@/components/page-header';
import { type Postback } from '@/components/postback/index';
import { createPostbackColumns } from '@/components/postback/list-columns';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';
import { PageLink } from '@/types';
import { Head } from '@inertiajs/react';
import { type ReactNode } from 'react';
import { PostbackProvider } from '@/context/postback-provider';

const postbackColumns = createPostbackColumns();
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
  const table = useServerTable({
    routeName: 'postbacks.index',
    initialState: state,
    defaultPageSize: 10,
  });
  /* return (
    <PostbackProvider initialState={state}>
      <Head title="Postbacks" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Postbacks" description="Check the status of your postbacks." />
        <TablePostbacks entries={rows.data} meta={meta} data={data} />
      </div>
    </PostbackProvider>
  ); */
  return (
    <>
      <PostbackProvider initialState={state}>
        <Head title="Postbacks" />
        <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
          <PageHeader title="Postbacks" description="Check the status of your postbacks." />
          <ServerTable
            data={rows.data}
            columns={postbackColumns}
            meta={meta}
            isLoading={table.isLoading}
            pagination={table.pagination}
            setPagination={table.setPagination}
            sorting={table.sorting}
            setSorting={table.setSorting}
            columnFilters={table.columnFilters}
            setColumnFilters={table.setColumnFilters}
            globalFilter={table.globalFilter}
            setGlobalFilter={table.setGlobalFilter}
            toolbarConfig={{
              searchPlaceholder: 'Search visitors...',
              filters: [
                {
                  columnId: 'vendor',
                  title: 'Vendor',
                  options: data.vendorFilterOptions,
                },
                {
                  columnId: 'status',
                  title: 'Status',
                  options: data.statusFilterOptions,
                },
              ],
              dateRange: { column: 'created_at', label: 'Created At' },
            }}
          />
        </div>
      </PostbackProvider>
    </>
  );
};

Index.layout = (page: ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

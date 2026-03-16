import PageHeader from '@/components/page-header';
import { PostbacksActions, TablePostbacks } from '@/components/postbacks/index';
import { PostbacksProvider } from '@/context/postbacks-provider';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Postbacks', href: '/postbacks' }];

interface Platform {
  id: number;
  name: string;
}

interface Postback {
  id: number;
  name: string;
  platform_id: number;
  base_url: string;
  param_mappings: Record<string, string>;
  result_url: string | null;
  generated_url: string;
  fire_mode: string;
  is_active: boolean;
  platform?: Platform | null;
  created_at: string;
  updated_at: string;
}

interface Props {
  rows: Postback[];
}

const Index = ({ rows }: Props) => {
  return (
    <PostbacksProvider>
      <Head title="Postbacks" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Postbacks" description="Manage postback URLs for your platforms.">
          <PostbacksActions />
        </PageHeader>
        <TablePostbacks entries={rows} />
      </div>
    </PostbacksProvider>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

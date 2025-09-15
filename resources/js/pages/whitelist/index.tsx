import PageHeader from '@/components/page-header';
import { TableWhitelist } from '@/components/whitelist';
import { WhitelistActions } from '@/components/whitelist/whitelist-actions';
import { WhitelistProvider } from '@/context/whitelist-provider';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { route } from 'ziggy-js';
const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Whitelist',
    href: route('whitelist.index'),
  },
];

/**
 * Whitelist Index Page Component
 *
 * @description Página principal para administrar entradas de whitelist (dominios e IPs)
 */
type WhitelistEntry = {
  id: number;
  type: 'domain' | 'ip';
  name: string;
  value: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
};

interface IndexProps {
  rows: {
    data: WhitelistEntry[];
  };
}

const Index = ({ rows }: IndexProps) => {
  return (
    <WhitelistProvider>
      <Head title="Whitelist Management" />
      <div className="slide-in-up relative flex-1 space-y-6 overflow-auto p-6 md:p-8">
        <PageHeader title="Whitelist Management" description="Manage allowed domains and IP addresses for API access control." className='flex flex-row justify-between gap-4 items-center'>
          <WhitelistActions />
        </PageHeader>
        <TableWhitelist entries={rows} />
      </div>
    </WhitelistProvider>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

import { MixLogsTable } from '@/components/logs/mixes/table';
import PageHeader from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { route } from 'ziggy-js';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Logs',
    href: "/",
  },
  {
    title: 'Offerwall Mixes',
    href: route('logs.offerwall-mixes.index'),
  },
];

// Define interfaces for props
interface OfferwallMixLog {
  id: number;
  offerwall_mix: { name: string };
  origin: string;
  successful_integrations: number;
  failed_integrations: number;
  total_integrations: number;
  total_offers_aggregated: number;
  total_duration_ms: number;
  created_at: string;
}

interface PaginatedLogs {
  data: OfferwallMixLog[];
  links: any[]; // Adjust based on your pagination link structure
  meta: any; // Adjust based on your pagination meta structure
}

interface IndexProps {
  rows: PaginatedLogs;
  filters: { [key: string]: string };
}

const Index = ({ rows, filters }: IndexProps) => {
  return (
    <>
      <Head title="Offerwall Mix Logs" />
      <div className="flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Offerwall Mix Logs" description="Review and debug Offerwall Mix executions.">
          {/* Actions can go here, e.g., filters */}
        </PageHeader>
        <div className="-mx-4 overflow-x-auto px-4 py-4 sm:-mx-8 sm:px-8">
          <MixLogsTable data={rows.data} />
        </div>
      </div>
    </>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;

export default Index;

import { indexBreadcrumbs, TableIntegrations } from '@/components/integrations';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { route } from 'ziggy-js';

interface Integration {
  id: number;
  company_id: number;
  name: string;
  created_at: string;
  updated_at: string;
  is_active: boolean;
  type: string;
  response_parser_config: Record<string, any>;
  request_mapping_config: Record<string, any>;
  status: string;
}
interface IndexProps {
  rows: Integration[];
  state: {
    filters: Record<string, string>;
    sort: string;
  };
}

const Index = ({ rows }: IndexProps) => {
  return (
    <>
      <Head title="Integrations" />
      <div className="relative flex-1 space-y-6 overflow-auto p-6 md:p-8">
        <PageHeader title="Integrations" description="Manage integrations and their environments.">
          <Link href={route('integrations.create')}>
            <Button className="flex items-center gap-2">
              <Plus className="h-4 w-4" />
              Add Integration
            </Button>
          </Link>
        </PageHeader>
        <TableIntegrations entries={rows} />
      </div>
    </>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={indexBreadcrumbs} />;
export default Index;

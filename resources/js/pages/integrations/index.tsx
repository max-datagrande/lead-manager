import { indexBreadcrumbs, TableIntegrations } from '@/components/integrations';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { useModal } from '@/hooks/use-modal';
import { useToast } from '@/hooks/use-toast';
import AppLayout from '@/layouts/app-layout';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { Plus, RefreshCw } from 'lucide-react';
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
      <div id="holasdf" className="relative flex-1 space-y-6 overflow-auto p-6 md:p-8">
        <PageHeader title="Integrations" description="Manage integrations and their environments.">
          <IntegrationPrimaryButtons />
        </PageHeader>
        <TableIntegrations entries={rows} />
      </div>
    </>
  );
};
const IntegrationPrimaryButtons = () => {
  const { addMessage: setNotify } = useToast();
  const { confirm } = useModal();
  const {
    props: { app },
  } = usePage<SharedData>();
  const isLocalEnv = app.env === 'local';

  const confirmSync = async () => {
    const confirmed = await confirm({
      title: 'Sync Integrations',
      description:
        'This action will sync all integrations from production to local. Are you sure you want to sync from production? This will TRUNCATE your local fields table!',
      confirmText: 'Sync',
      cancelText: 'Cancel',
      destructive: true,
    });

    if (confirmed) {
      setNotify('Syncing integrations', 'info');
      syncIntegrations();
    }
  };
  const syncIntegrations = async () => {
    const url = route('api.integrations.import');
    try {
      const response = await axios.post(url);
      const notify = response.data.message || 'Sync completed!';
      setNotify(notify, 'success');
      router.reload();
    } catch (error) {
      const errorMessage = error.response?.data?.error || 'An unknown error occurred.';
      console.log(error);
      setNotify(errorMessage, 'error');
    }
  };

  return (
    <div className="flex justify-end gap-2">
      {/* Create */}
      <Link href={route('integrations.create')}>
        <Button className="flex items-center gap-2">
          <Plus className="h-4 w-4" />
          Add Integration
        </Button>
      </Link>
      {isLocalEnv && (
        <>
          <Button onClick={confirmSync} variant="outline" className="flex items-center gap-2">
            <RefreshCw className="h-4 w-4" />
            Sync
          </Button>
        </>
      )}
    </div>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={indexBreadcrumbs} />;
export default Index;

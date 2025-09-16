import { indexBreadcrumbs, TableIntegrations } from '@/components/integrations';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { route } from 'ziggy-js';
interface IndexProps {
  rows: any[]; // Deberíamos definir un tipo estricto para Integration
  filters: {
    sort: string;
  };
}

const Index = ({ rows, filters }: IndexProps) => {
  return (
    <>
      <Head title="Integrations" />
      <div className="relative flex-1 space-y-6 overflow-auto p-6 md:p-8">
        <PageHeader
          title="Integrations"
          description="Manage integrations and their environments."
          className="flex flex-row items-center justify-between gap-4"
        >
          <Link href={route('integrations.create')}>
            <Button className="flex items-center gap-2">
              <Plus className="h-4 w-4" />
              Add Integration
            </Button>
          </Link>
        </PageHeader>
        <TableIntegrations entries={rows} filters={filters} />
      </div>
    </>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={indexBreadcrumbs} />;
export default Index;

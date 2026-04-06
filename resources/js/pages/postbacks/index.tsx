import PageHeader from '@/components/page-header';
import { PostbacksActions, TablePostbacks } from '@/components/postbacks/index';
import { PostbacksProvider } from '@/context/postbacks-provider';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Postbacks', href: '/postbacks' }];

interface Platform {
  id: number;
  name: string;
}

interface Postback {
  id: number;
  name: string;
  type: 'external' | 'internal';
  platform_id: number | null;
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

interface PostbackTypeOption {
  value: string;
  label: string;
}

interface Props {
  rows: Postback[];
  postback_types: PostbackTypeOption[];
  active_type: string;
}

const typeFilters = [
  { value: 'all', label: 'All' },
  { value: 'external', label: 'External' },
  { value: 'internal', label: 'Internal' },
];

const Index = ({ rows, active_type }: Props) => {
  const handleTypeFilter = (type: string) => {
    router.visit(route('postbacks.index', type === 'all' ? {} : { type }), {
      preserveState: true,
      preserveScroll: true,
    });
  };

  return (
    <PostbacksProvider>
      <Head title="Postbacks" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Postbacks" description="Manage postback URLs for your platforms.">
          <PostbacksActions />
        </PageHeader>
        <div className="flex gap-1">
          {typeFilters.map((f) => (
            <button
              key={f.value}
              onClick={() => handleTypeFilter(f.value)}
              className={`rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
                active_type === f.value
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-muted text-muted-foreground hover:bg-muted/80'
              }`}
            >
              {f.label}
            </button>
          ))}
        </div>
        <TablePostbacks entries={rows} />
      </div>
    </PostbacksProvider>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

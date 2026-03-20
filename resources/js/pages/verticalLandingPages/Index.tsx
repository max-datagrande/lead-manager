import { LandingPagesActions, TableLandingPages } from '@/components/vertical-landing-pages';
import PageHeader from '@/components/page-header';
import { VerticalLandingPagesProvider } from '@/context/vertical-landing-pages-provider';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { route } from 'ziggy-js';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Landing Pages',
    href: route('vertical_landing_pages.index'),
  },
];

type Vertical = { id: number; name: string };
type Company = { id: number; name: string };

type LandingPage = {
  id: number;
  name: string;
  url: string;
  is_external: boolean;
  vertical_id: number;
  company_id: number | null;
  active: boolean;
  vertical?: Vertical;
  company?: Company | null;
  created_at: string;
  updated_at: string;
};

interface IndexProps {
  landingPages: LandingPage[];
  verticals: Vertical[];
  companies: Company[];
}

const Index = ({ landingPages, verticals, companies }: IndexProps) => {
  return (
    <VerticalLandingPagesProvider verticals={verticals} companies={companies}>
      <Head title="Landing Pages" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Landing Pages" description="Manage vertical landing pages.">
          <LandingPagesActions />
        </PageHeader>
        <TableLandingPages entries={landingPages} />
      </div>
    </VerticalLandingPagesProvider>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;
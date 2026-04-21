import { LandingPagesVersionsActions } from '@/components/landing-versions';
import { TableLandingPagesVersions } from '@/components/landing-versions/table-landing-pages-version';
import PageHeader from '@/components/page-header';
import { LandingPagesVersionProvider } from '@/context/landing-version-provider';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { route } from 'ziggy-js';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Landing Pages',
    href: route('landing_pages.index'),
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

interface Versions {
  id: number;
  landing_page_id: number;
  name: string;
  description: string;
  url: string;
  status: boolean;
  created_at: string;
  updated_at: string;
  fullUrl: string;
}

interface IndexProps {
  landingPage: LandingPage;
  versions: Versions[];
}

const Index = ({ versions = [], landingPage }: IndexProps) => {
  return (
    <LandingPagesVersionProvider landingPage={landingPage}>
      <Head title={`${landingPage.name} Versions`} />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title={`${landingPage.name} Versions`} description="Manage vertical landing pages.">
          <LandingPagesVersionsActions />
        </PageHeader>
        <TableLandingPagesVersions versions={versions} />
      </div>
    </LandingPagesVersionProvider>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

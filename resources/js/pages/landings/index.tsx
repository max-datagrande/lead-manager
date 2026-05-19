import { LandingPagesActions, TableLandingPages } from '@/components/landing-pages';
import PageHeader from '@/components/page-header';
import { LandingPagesProvider } from '@/context/landing-provider';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import type { AvailableColumns, Company, LandingPage, Vertical } from '@/types/models/landing-page';
import { Head } from '@inertiajs/react';
import { route } from 'ziggy-js';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Landing Pages',
    href: route('landing_pages.index'),
  },
];

interface IndexProps {
  landingPages: LandingPage[];
  verticals: Vertical[];
  companies: Company[];
  available_columns: AvailableColumns;
}

const Index = ({ landingPages, verticals, companies, available_columns }: IndexProps) => {
  return (
    <LandingPagesProvider verticals={verticals} companies={companies} availableColumns={available_columns}>
      <Head title="Landing Pages" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Landing Pages" description="Manage vertical landing pages.">
          <LandingPagesActions />
        </PageHeader>
        <TableLandingPages entries={landingPages} />
      </div>
    </LandingPagesProvider>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

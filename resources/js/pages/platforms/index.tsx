import { PlatformsActions, TablePlatforms } from '@/components/platforms/index'
import PageHeader from '@/components/page-header'
import { PlatformsProvider } from '@/context/platforms-provider'
import AppLayout from '@/layouts/app-layout'
import type { BreadcrumbItem } from '@/types'
import { type IndexProps } from '@/types/models/platform'
import { Head } from '@inertiajs/react'
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Platforms', href: '/platforms' }]

const Index = ({ platforms, companies }: IndexProps) => {
  return (
    <PlatformsProvider companies={companies}>
      <Head title="Platforms" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Platforms" description="Manage client platforms and their available tokens.">
          <PlatformsActions />
        </PageHeader>
        <TablePlatforms entries={platforms} />
      </div>
    </PlatformsProvider>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />
export default Index

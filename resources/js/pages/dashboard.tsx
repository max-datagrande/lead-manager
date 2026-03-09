import { DashboardLoadTimeCard, type DailySummary } from '@/components/performance/dashboard-load-time-card';
import { CardStats as CardIpDataStats } from '@/components/services/ip-data';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Deferred, Head, usePage } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: '/',
  },
];

type DashboardProps = {
  performanceSummary?: DailySummary[];
};

const Dashboard = () => {
  const { performanceSummary } = usePage<DashboardProps>().props;

  return (
    <>
      <Head title="Dashboard" />
      <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        {/* Row 1: IP API (col 1) + Performance chart (col 2-3) */}
        <div className="grid auto-rows-min grid-rows-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
          <CardIpDataStats className="relative overflow-hidden" />
          <Deferred data="performanceSummary" fallback={<DashboardLoadTimeCard />}>
            <DashboardLoadTimeCard data={performanceSummary} />
          </Deferred>
          <div className="relative col-span-1">
            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
          </div>
        </div>
        {/* Row 3: Placeholder for future content */}
        <div className="relative min-h-[50vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
          <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
        </div>
      </div>
    </>
  );
};

Dashboard.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Dashboard;

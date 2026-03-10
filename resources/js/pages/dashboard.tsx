import { DashboardLoadTimeCard, type DailySummary } from '@/components/performance/dashboard-load-time-card';
import { CardStats as CardIpDataStats } from '@/components/services/ip-data';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { VpsMetricsCard, type VpsMetrics } from '@/components/vps/vps-metrics-card';
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
  vpsMetrics?: VpsMetrics | null;
};

const Dashboard = () => {
  const { performanceSummary, vpsMetrics } = usePage<DashboardProps>().props;

  return (
    <>
      <Head title="Dashboard" />
      <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div className="grid-rows-auto grid auto-rows-min gap-4 md:grid-cols-3 lg:grid-cols-4">
          {/* Row 1: IP API (col 1) + Performance chart (col 2-3) + placeholder (col 4) */}
          <CardIpDataStats className="relative overflow-hidden" />

          <Deferred data="performanceSummary" fallback={<DashboardLoadTimeCard />}>
            <DashboardLoadTimeCard data={performanceSummary} />
          </Deferred>
          {/* Row 2 col 1: VPS Metrics */}
          <div className="relative col-span-1">
            <Deferred data="vpsMetrics" fallback={<VpsMetricsCard />}>
              <VpsMetricsCard data={vpsMetrics} />
            </Deferred>
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

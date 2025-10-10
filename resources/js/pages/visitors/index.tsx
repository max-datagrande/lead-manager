import PageHeader from '@/components/page-header';
import { TableVisitors } from '@/components/visitors';
import { VisitorsProvider } from '@/context/visitors-provider';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { route } from 'ziggy-js';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Visitors',
    href: route('visitors.index'),
  },
];
/**
 * Index Page Component
 *
 * @description Página principal para mostrar visitantes con tabla paginada
 */
/* {
  "id": "25b6fa22-e564-4851-a28f-9e9b645dcdee",
  "fingerprint": "46a05aadb9d47cb5ffff60a66f3d6e86a95b612a12465540c79109230a0ff4db",
  "visit_date": "2025-08-26T00:00:00.000000Z",
  "visit_count": 2,
  "ip_address": "216.131.83.235",
  "device_type": "iPhone",
  "browser": "Safari",
  "os": "iOS",
  "country_code": "US",
  "state": "New York",
  "city": "New York City",
  "traffic_source": "direct",
  "traffic_medium": "direct",
  "host": "offer.top-carinsurance.test",
  "path_visited": "quotes",
  "referrer": "https://quotes.top-carinsurance.test/rates",
  "is_bot": false,
  "created_at": "2025-08-26T13:54:19.000000Z",
  "updated_at": "2025-08-26T13:54:21.000000Z"
} */
type Visitor = {
  id: string;
  fingerprint: string;
  visit_date: string;
  visit_count: number;
  ip_address: string;
  device_type: string;
  browser: string;
  os: string;
  country_code: string;
  state: string;
  city: string;
  traffic_source: string;
  traffic_medium: string;
  host: string;
  path_visited: string;
  referrer: string;
  is_bot: boolean;
  created_at: string;
  updated_at: string;
}
interface IndexProps {
  rows: {
    data: Visitor[];
  };
}
const Index = ({ rows }: IndexProps) => {
  return (
    <VisitorsProvider>
      <Head title="Visitors" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Visitors" description="Manage visitors from our landing pages." />
        <TableVisitors visitors={rows.data} />
      </div>
    </VisitorsProvider>
  );
};

Index.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />;
export default Index;

import PageHeader from '@/components/page-header';
import { createBreadcrumbs } from '@/components/ping-post/buyers/breadcrumbs';
import { BuyerForm } from '@/components/ping-post/buyers/buyer-form';
import { BuyersProvider } from '@/context/buyers-provider';
import AppLayout from '@/layouts/app-layout';
import type { Integration } from '@/types/ping-post';
import { Head } from '@inertiajs/react';

interface Props {
  integrations: Integration[];
  priceSources: Array<{ value: string; label: string }>;
  fields: { id: number; name: string }[];
  externalPostbacks: any[];
}

const BuyersCreate = ({ integrations, priceSources, fields, externalPostbacks }: Props) => (
  <BuyersProvider>
    <Head title="Create Buyer" />
    <div className="relative flex-1 space-y-6 p-6 md:p-8">
      <PageHeader title="Create Buyer" description="Select an integration and configure this buyer." />
      <BuyerForm integrations={integrations} priceSources={priceSources} fields={fields} externalPostbacks={externalPostbacks} />
    </div>
  </BuyersProvider>
);

BuyersCreate.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={createBreadcrumbs} />;
export default BuyersCreate;

import PageHeader from '@/components/page-header';
import { editBreadcrumbs } from '@/components/ping-post/buyers/breadcrumbs';
import { BuyerForm } from '@/components/ping-post/buyers/buyer-form';
import { BuyersProvider } from '@/context/buyers-provider';
import AppLayout from '@/layouts/app-layout';
import type { Buyer, Integration, TimezoneOption } from '@/types/ping-post';
import { Head } from '@inertiajs/react';

interface Props {
  buyer: Buyer;
  integrations: Integration[];
  priceSources: Array<{ value: string; label: string }>;
  fields: { id: number; name: string }[];
  externalPostbacks: any[];
  timezones: TimezoneOption[];
}

const BuyersEdit = ({ buyer, integrations, priceSources, fields, externalPostbacks, timezones }: Props) => (
  <BuyersProvider buyer={buyer}>
    <Head title={`Edit ${buyer.name}`} />
    <div className="relative flex-1 space-y-6 p-6 md:p-8">
      <PageHeader title={`Edit ${buyer.name}`} description="Update buyer configuration." />
      <BuyerForm
        integrations={integrations}
        priceSources={priceSources}
        fields={fields}
        externalPostbacks={externalPostbacks}
        timezones={timezones}
      />
    </div>
  </BuyersProvider>
);

BuyersEdit.layout = (page: React.ReactNode & { props: { buyer: Buyer } }) => (
  <AppLayout children={page} breadcrumbs={editBreadcrumbs(page.props.buyer)} />
);
export default BuyersEdit;

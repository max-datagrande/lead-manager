import { editBreadcrumbs } from '@/components/ping-post/buyers/breadcrumbs'
import { BuyerForm } from '@/components/ping-post/buyers/buyer-form'
import PageHeader from '@/components/page-header'
import { BuyersProvider } from '@/context/buyers-provider'
import AppLayout from '@/layouts/app-layout'
import type { Buyer, Integration } from '@/types/ping-post'
import { Head } from '@inertiajs/react'

interface Props {
  buyer: Buyer
  integrations: Integration[]
  pricingTypes: Array<{ value: string; label: string }>
}

const BuyersEdit = ({ buyer, integrations, pricingTypes }: Props) => (
  <BuyersProvider buyer={buyer}>
    <Head title={`Edit ${buyer.name}`} />
    <div className="relative flex-1 space-y-6 p-6 md:p-8">
      <PageHeader title={`Edit ${buyer.name}`} description="Update buyer configuration." />
      <BuyerForm integrations={integrations} pricingTypes={pricingTypes} />
    </div>
  </BuyersProvider>
)

BuyersEdit.layout = (page: React.ReactNode & { props: { buyer: Buyer } }) =>
  <AppLayout children={page} breadcrumbs={editBreadcrumbs(page.props.buyer)} />
export default BuyersEdit

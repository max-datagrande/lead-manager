import { BuyerActions } from '@/components/ping-post/buyers/actions'
import { indexBreadcrumbs } from '@/components/ping-post/buyers/breadcrumbs'
import { TableBuyers } from '@/components/ping-post/buyers/table-buyers'
import PageHeader from '@/components/page-header'
import AppLayout from '@/layouts/app-layout'
import type { Buyer } from '@/types/ping-post'
import { Head } from '@inertiajs/react'

interface Props {
  buyers: { data: Buyer[] }
}

const BuyersIndex = ({ buyers }: Props) => (
  <>
    <Head title="Buyers" />
    <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
      <PageHeader title="Buyers" description="Manage ping-post and post-only buyers.">
        <BuyerActions />
      </PageHeader>
      <TableBuyers entries={buyers.data} />
    </div>
  </>
)

BuyersIndex.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={indexBreadcrumbs} />
export default BuyersIndex

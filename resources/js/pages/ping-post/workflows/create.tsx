import { createBreadcrumbs } from '@/components/ping-post/workflows/breadcrumbs'
import { WorkflowForm } from '@/components/ping-post/workflows/workflow-form'
import PageHeader from '@/components/page-header'
import { WorkflowsProvider } from '@/context/workflows-provider'
import AppLayout from '@/layouts/app-layout'
import type { Buyer } from '@/types/ping-post'
import { Head } from '@inertiajs/react'

interface Props {
  buyers: Buyer[]
  strategies: Array<{ value: string; label: string }>
}

const WorkflowsCreate = ({ buyers, strategies }: Props) => (
  <WorkflowsProvider availableBuyers={buyers} strategies={strategies}>
    <Head title="Create Workflow" />
    <div className="relative flex-1 space-y-6 p-6 md:p-8">
      <PageHeader title="Create Workflow" description="Configure how leads are distributed to buyers." />
      <WorkflowForm />
    </div>
  </WorkflowsProvider>
)

WorkflowsCreate.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={createBreadcrumbs} />
export default WorkflowsCreate

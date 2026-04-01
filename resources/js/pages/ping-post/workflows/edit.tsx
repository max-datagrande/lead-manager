import { editBreadcrumbs } from '@/components/ping-post/workflows/breadcrumbs'
import { WorkflowForm } from '@/components/ping-post/workflows/workflow-form'
import PageHeader from '@/components/page-header'
import { WorkflowsProvider } from '@/context/workflows-provider'
import AppLayout from '@/layouts/app-layout'
import type { Buyer, Workflow } from '@/types/ping-post'
import { Head } from '@inertiajs/react'

interface Props {
  workflow: Workflow
  buyers: Buyer[]
  strategies: Array<{ value: string; label: string }>
}

const WorkflowsEdit = ({ workflow, buyers, strategies }: Props) => (
  <WorkflowsProvider workflow={workflow} availableBuyers={buyers} strategies={strategies}>
    <Head title={`Edit ${workflow.name}`} />
    <div className="relative flex-1 space-y-6 p-6 md:p-8">
      <PageHeader title={`Edit ${workflow.name}`} description="Update workflow configuration." />
      <WorkflowForm />
    </div>
  </WorkflowsProvider>
)

WorkflowsEdit.layout = (page: React.ReactNode & { props: { workflow: Workflow } }) =>
  <AppLayout children={page} breadcrumbs={editBreadcrumbs(page.props.workflow)} />
export default WorkflowsEdit

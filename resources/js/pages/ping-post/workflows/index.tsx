import { indexBreadcrumbs } from '@/components/ping-post/workflows/breadcrumbs'
import { WorkflowActions } from '@/components/ping-post/workflows/actions'
import { TableWorkflows } from '@/components/ping-post/workflows/table-workflows'
import PageHeader from '@/components/page-header'
import AppLayout from '@/layouts/app-layout'
import type { Workflow } from '@/types/ping-post'
import { Head } from '@inertiajs/react'

interface Props {
  workflows: { data: Workflow[] }
}

const WorkflowsIndex = ({ workflows }: Props) => (
  <>
    <Head title="Workflows" />
    <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
      <PageHeader title="Workflows" description="Manage lead distribution workflows.">
        <WorkflowActions />
      </PageHeader>
      <TableWorkflows entries={workflows.data} />
    </div>
  </>
)

WorkflowsIndex.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={indexBreadcrumbs} />
export default WorkflowsIndex

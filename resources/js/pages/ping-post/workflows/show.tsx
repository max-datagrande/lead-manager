import { showBreadcrumbs } from '@/components/ping-post/workflows/breadcrumbs'
import PageHeader from '@/components/page-header'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import AppLayout from '@/layouts/app-layout'
import type { Workflow } from '@/types/ping-post'
import { Head, Link, router } from '@inertiajs/react'
import { Copy, Edit, Trash2 } from 'lucide-react'
import { route } from 'ziggy-js'

interface Props {
  workflow: Workflow
}

const STRATEGY_LABELS: Record<string, string> = {
  best_bid: 'Best Bid',
  waterfall: 'Waterfall',
  combined: 'Combined',
}

const WorkflowsShow = ({ workflow }: Props) => {
  const handleDelete = () => {
    if (confirm(`Delete workflow "${workflow.name}"?`)) {
      router.delete(route('ping-post.workflows.destroy', workflow.id))
    }
  }

  return (
    <>
      <Head title={workflow.name} />
      <div className="relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title={workflow.name} description={`${STRATEGY_LABELS[workflow.strategy] ?? workflow.strategy} workflow`}>
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => router.post(route('ping-post.workflows.duplicate', workflow.id))}>
              <Copy className="mr-2 h-4 w-4" />
              Duplicate
            </Button>
            <Button variant="outline" asChild>
              <Link href={route('ping-post.workflows.edit', workflow.id)}>
                <Edit className="mr-2 h-4 w-4" />
                Edit
              </Link>
            </Button>
            <Button variant="destructive" onClick={handleDelete}>
              <Trash2 className="mr-2 h-4 w-4" />
              Delete
            </Button>
          </div>
        </PageHeader>

        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>Configuration</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              <div className="flex justify-between">
                <span className="text-muted-foreground">Strategy</span>
                <Badge variant="outline">{STRATEGY_LABELS[workflow.strategy] ?? workflow.strategy}</Badge>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Mode</span>
                <span className="capitalize">{workflow.execution_mode}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Status</span>
                <Badge variant="outline" className={workflow.is_active ? 'border-green-500 text-green-600' : 'border-red-400 text-red-500'}>
                  {workflow.is_active ? 'Active' : 'Inactive'}
                </Badge>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Global Timeout</span>
                <span>{workflow.global_timeout_ms}ms</span>
              </div>
              {workflow.user && (
                <div className="flex justify-between">
                  <span className="text-muted-foreground">Owner</span>
                  <span>{workflow.user.name}</span>
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Buyers</CardTitle>
              <CardDescription>{workflow.workflow_buyers?.length ?? 0} configured</CardDescription>
            </CardHeader>
            <CardContent>
              {workflow.workflow_buyers?.length ? (
                <div className="space-y-2">
                  {[...workflow.workflow_buyers]
                    .sort((a, b) => a.position - b.position)
                    .map((wb) => (
                      <div key={wb.id} className="flex items-center justify-between rounded bg-muted px-3 py-2 text-sm">
                        <div className="flex items-center gap-2">
                          <span className="text-muted-foreground">#{wb.position + 1}</span>
                          <span className="font-medium">{wb.integration?.name ?? `Buyer #${wb.integration_id}`}</span>
                          {wb.buyer_group === 'secondary' && (
                            <Badge variant="outline" className="text-xs">Secondary</Badge>
                          )}
                        </div>
                        <div className="flex items-center gap-2">
                          {wb.is_fallback && <Badge variant="outline" className="border-amber-500 text-amber-600 text-xs">Fallback</Badge>}
                          {!wb.is_active && <Badge variant="outline" className="border-red-400 text-red-500 text-xs">Inactive</Badge>}
                        </div>
                      </div>
                    ))}
                </div>
              ) : (
                <p className="text-sm text-muted-foreground">No buyers configured.</p>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  )
}

WorkflowsShow.layout = (page: React.ReactNode & { props: { workflow: Workflow } }) =>
  <AppLayout children={page} breadcrumbs={showBreadcrumbs(page.props.workflow)} />
export default WorkflowsShow

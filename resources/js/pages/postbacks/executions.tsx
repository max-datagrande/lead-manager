import { ServerTable } from '@/components/data-table/server-table'
import PageHeader from '@/components/page-header'
import { type PostbackExecution } from '@/components/postbacks/executions'
import { createExecutionColumns } from '@/components/postbacks/executions/list-columns'
import { useServerTable } from '@/hooks/use-server-table'
import AppLayout from '@/layouts/app-layout'
import { type BreadcrumbItem, type PageLink } from '@/types'
import { Head } from '@inertiajs/react'
import type { ReactNode } from 'react'

const executionColumns = createExecutionColumns()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Postbacks', href: '/postbacks' },
  { title: 'Executions', href: '/postbacks/executions' },
]

interface IndexProps {
  rows: {
    data: PostbackExecution[]
    current_page: number
    last_page: number
    links: PageLink[]
    per_page: number
    total: number
  }
  meta: {
    total: number
    per_page: number
    current_page: number
    last_page: number
    from: number
    to: number
  }
  state: {
    search?: string
    sort?: string
    filters?: any[]
    page?: number
    per_page?: number
  }
  data: {
    statusOptions: Array<{ value: string; label: string }>
    fireModeOptions: Array<{ value: string; label: string }>
    postbacks: Array<{ id: number; name: string }>
  }
}

const Index = ({ rows, meta, state, data }: IndexProps) => {
  const table = useServerTable({
    routeName: 'postbacks.executions.index',
    initialState: state,
    defaultPageSize: 25,
  })

  return (
    <>
      <Head title="Executions" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Executions" description="History of all postback fire executions." />
        <ServerTable
          data={rows.data}
          columns={executionColumns}
          meta={meta}
          isLoading={table.isLoading}
          pagination={table.pagination}
          setPagination={table.setPagination}
          sorting={table.sorting}
          setSorting={table.setSorting}
          columnFilters={table.columnFilters}
          setColumnFilters={table.setColumnFilters}
          globalFilter={table.globalFilter}
          setGlobalFilter={table.setGlobalFilter}
          toolbarConfig={{
            searchPlaceholder: 'Search UUID, URL, IP…',
            filters: [
              {
                columnId: 'status',
                title: 'Status',
                options: data.statusOptions,
              },
              {
                columnId: 'fire_mode',
                title: 'Fire Mode',
                options: data.fireModeOptions,
              },
              {
                columnId: 'postback_id',
                title: 'Postback',
                options: data.postbacks.map((p) => ({ value: String(p.id), label: p.name })),
              },
            ],
            dateRange: { column: 'created_at', label: 'Created At' },
          }}
        />
      </div>
    </>
  )
}

Index.layout = (page: ReactNode) => <AppLayout children={page} breadcrumbs={breadcrumbs} />
export default Index

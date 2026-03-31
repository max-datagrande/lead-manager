import { indexBreadcrumbs } from '@/components/ping-post/dispatches/breadcrumbs'
import { dispatchColumns } from '@/components/ping-post/dispatches/list-columns'
import { StatusBadge } from '@/components/ping-post/status-badge'
import PageHeader from '@/components/page-header'
import { DataTableContent } from '@/components/data-table/table-content'
import { DataTableHeader } from '@/components/data-table/table-header'
import { DataTablePagination } from '@/components/data-table/table-pagination'
import { DataTableToolbar } from '@/components/data-table/toolbar'
import { Table, TableBody } from '@/components/ui/table'
import AppLayout from '@/layouts/app-layout'
import type { LeadDispatch } from '@/types/ping-post'
import { Head } from '@inertiajs/react'
import {
  getCoreRowModel,
  getFacetedRowModel,
  getFacetedUniqueValues,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useReactTable,
} from '@tanstack/react-table'
import { useState } from 'react'

const toolbarConfig = {
  dateRange: { column: 'created_at', label: 'Created At' },
  filters: [
    {
      columnId: 'status',
      title: 'Status',
      options: [
        { label: 'Sold', value: 'sold' },
        { label: 'Not Sold', value: 'not_sold' },
        { label: 'Running', value: 'running' },
        { label: 'Error', value: 'error' },
        { label: 'Timeout', value: 'timeout' },
      ],
    },
    {
      columnId: 'strategy_used',
      title: 'Strategy',
      options: [
        { label: 'Best Bid', value: 'best_bid' },
        { label: 'Waterfall', value: 'waterfall' },
        { label: 'Combined', value: 'combined' },
      ],
    },
  ],
}

interface Props {
  dispatches: { data: LeadDispatch[] }
}

const DispatchesIndex = ({ dispatches }: Props) => {
  const [sorting, setSorting] = useState([{ id: 'created_at', desc: true }])
  const [globalFilter, setGlobalFilter] = useState('')
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 50 })
  const [columnFilters, setColumnFilters] = useState<any[]>([])

  const table = useReactTable({
    data: dispatches.data,
    columns: dispatchColumns,
    state: { sorting, globalFilter, pagination, columnFilters },
    onPaginationChange: setPagination,
    onSortingChange: setSorting,
    onGlobalFilterChange: setGlobalFilter,
    onColumnFiltersChange: setColumnFilters,
    getPaginationRowModel: getPaginationRowModel(),
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getFacetedRowModel: getFacetedRowModel(),
    getFacetedUniqueValues: getFacetedUniqueValues(),
    globalFilterFn: 'includesString',
  })

  return (
    <>
      <Head title="Dispatch Logs" />
      <div className="slide-in-up relative flex-1 space-y-6 p-6 md:p-8">
        <PageHeader title="Dispatch Logs" description="View lead dispatch activity and results." />
        <div className="space-y-4">
          <DataTableToolbar table={table} config={toolbarConfig} />
          <Table>
            <DataTableHeader table={table} />
            <TableBody>
              <DataTableContent table={table} data={dispatches.data} />
            </TableBody>
          </Table>
          <DataTablePagination table={table} />
        </div>
      </div>
    </>
  )
}

DispatchesIndex.layout = (page: React.ReactNode) => <AppLayout children={page} breadcrumbs={indexBreadcrumbs} />
export default DispatchesIndex

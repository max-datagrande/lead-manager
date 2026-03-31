import { DataTableContent } from '@/components/data-table/table-content'
import { DataTableHeader } from '@/components/data-table/table-header'
import { DataTablePagination } from '@/components/data-table/table-pagination'
import { DataTableToolbar } from '@/components/data-table/toolbar'
import { Table, TableBody } from '@/components/ui/table'
import type { Workflow } from '@/types/ping-post'
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
import { workflowColumns } from './list-columns'

const toolbarConfig = {
  dateRange: { column: 'created_at', label: 'Created At' },
  filters: [
    {
      columnId: 'strategy',
      title: 'Strategy',
      options: [
        { label: 'Best Bid', value: 'best_bid' },
        { label: 'Waterfall', value: 'waterfall' },
        { label: 'Combined', value: 'combined' },
      ],
    },
    {
      columnId: 'is_active',
      title: 'Status',
      options: [
        { label: 'Active', value: 'true' },
        { label: 'Inactive', value: 'false' },
      ],
    },
  ],
}

interface Props {
  entries: Workflow[]
}

export function TableWorkflows({ entries }: Props) {
  const [sorting, setSorting] = useState([{ id: 'id', desc: true }])
  const [globalFilter, setGlobalFilter] = useState('')
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 25 })
  const [columnFilters, setColumnFilters] = useState<any[]>([])

  const table = useReactTable({
    data: entries,
    columns: workflowColumns,
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
    <div className="space-y-4">
      <DataTableToolbar table={table} config={toolbarConfig} />
      <Table>
        <DataTableHeader table={table} />
        <TableBody>
          <DataTableContent table={table} data={entries} />
        </TableBody>
      </Table>
      <DataTablePagination table={table} />
    </div>
  )
}

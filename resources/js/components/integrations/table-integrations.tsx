import { DataTableContent } from '@/components/data-table/table-content';
import { DataTableHeader } from '@/components/data-table/table-header';
import { DataTablePagination } from '@/components/data-table/table-pagination';
import { DataTableToolbar } from '@/components/data-table/toolbar';
import { Table, TableBody } from '@/components/ui/table';
import { useModal } from '@/hooks/use-modal';
import { getSortState } from '@/utils/table';
import { router } from '@inertiajs/react';
import { getCoreRowModel, getFilteredRowModel, getPaginationRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table';
import { useState } from 'react';
import { columns } from './list-columns';

export function TableIntegrations({ entries, filters }) {
  const modal = useModal();
  const { sort } = filters;
  const [sorting, setSorting] = useState(sort ? getSortState(sort) : []);
  const [globalFilter, setGlobalFilter] = useState('');
  const [pagination, setPagination] = useState({
    pageIndex: 0,
    pageSize: 10,
  });
  const showDeleteModal = async (integrationToDelete: any) => {
    const confirmed = await modal.confirm({
      title: 'Delete Integration',
      description: `Are you sure you want to delete "${integrationToDelete.name}"? This action cannot be undone.`,
      confirmText: 'Delete',
      cancelText: 'Cancel',
      destructive: true,
    });
    if (confirmed) {
      router.delete(route('integrations.destroy', integrationToDelete.id), {
        preserveState: true,
        preserveScroll: true,
      });
    }
  };

  const table = useReactTable({
    data: entries,
    columns: columns,
    state: {
      sorting,
      globalFilter,
      pagination,
    },
    onPaginationChange: setPagination,
    onSortingChange: setSorting,
    onGlobalFilterChange: setGlobalFilter,
    getPaginationRowModel: getPaginationRowModel(),
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getSortedRowModel: getSortedRowModel(),
    rowCount: entries.length,
    globalFilterFn: 'includesString',
    meta: {
      showDeleteModal,
    },
  });

  return (
    <>
      <div className="mb-4">
        <div className="mb-4 flex justify-between gap-2">
          <DataTableToolbar table={table} searchPlaceholder="Search integrations..." />
        </div>
      </div>
      <div className="rounded-md border">
        <Table>
          <DataTableHeader table={table} />
          <TableBody>
            <DataTableContent table={table} data={entries} />
          </TableBody>
        </Table>
      </div>
      <DataTablePagination table={table} />
    </>
  );
}

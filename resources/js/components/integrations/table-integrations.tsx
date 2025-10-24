import { DataTableContent } from '@/components/data-table/table-content';
import { DataTableHeader } from '@/components/data-table/table-header';
import { DataTablePagination } from '@/components/data-table/table-pagination';
import { DataTableToolbar } from '@/components/data-table/toolbar';
import { Table, TableBody } from '@/components/ui/table';
import { useModal } from '@/hooks/use-modal';
import { type SharedData } from '@/types';
import { getSortState } from '@/utils/table';
import { router, usePage } from '@inertiajs/react';
import { getCoreRowModel, getFilteredRowModel, getPaginationRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table';
import { useState } from 'react';
import { columns } from './list-columns';

interface IntegrationsPageData extends SharedData {
  state: {
    sort: string;
    filters: Record<string, string>;
  };
}

export function TableIntegrations({ entries }) {
  const {
    props: { state },
  } = usePage<IntegrationsPageData>();
  const modal = useModal();
  const { sort } = state;
  const [sorting, setSorting] = useState(sort ? getSortState(sort) : []);
  const [columnFilters, setColumnFilters] = useState([]);
  const [globalFilter, setGlobalFilter] = useState('');
  const [pagination, setPagination] = useState({
    pageIndex: 0,
    pageSize: 10,
  });

  // Crear opciones de filtro únicas para Company y Type
  const companyOptions = Array.from(
    new Set(entries.map((entry) => entry.company?.name).filter(Boolean))
  ).map((name) => ({ label: name, value: name }));

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
  console.log(columns);

  const table = useReactTable({
    data: entries,
    columns: columns,
    state: {
      sorting,
      columnFilters,
      globalFilter,
      pagination,
    },
    onPaginationChange: setPagination,
    onSortingChange: setSorting,
    onColumnFiltersChange: setColumnFilters,
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
          <DataTableToolbar
            table={table}
            searchPlaceholder="Search integrations..."
            config={{
              filters: [
                {
                  columnId: 'company',
                  title: 'Company',
                  options: companyOptions,
                },
              ],
              dateRange: { column: 'created_at', label: 'Created At' },
            }}
          />
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

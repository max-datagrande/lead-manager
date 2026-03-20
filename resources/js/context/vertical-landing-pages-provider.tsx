import { FormModal } from '@/components/vertical-landing-pages/index';
import { useModal } from '@/hooks/use-modal';
import { useToast } from '@/hooks/use-toast';
import { getSortState } from '@/utils/table';
import { useForm, usePage } from '@inertiajs/react';
import { createContext, useContext, useState } from 'react';

export const VerticalLandingPagesContext = createContext(null);

export function VerticalLandingPagesProvider({ children, verticals, companies }) {
  const { filters } = usePage().props as any;
  const modal = useModal();
  const { addMessage: setNotify } = useToast();
  const [currentRow, setCurrentRow] = useState(null);
  const [resetTrigger, setResetTrigger] = useState(false);
  const [sorting, setSorting] = useState(filters?.sort ? getSortState(filters.sort) : []);
  const [globalFilter, setGlobalFilter] = useState('');
  const [columnFilters, setColumnFilters] = useState([]);
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 });
  const { delete: destroy } = useForm();

  const showCreateModal = async () => {
    try {
      const result = await modal.openAsync(
        <FormModal id={0} verticals={verticals} companies={companies} />
      );
      console.log(result);
    } catch (error) {
      setNotify('Error creating landing page', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

  const showEditModal = async (entry) => {
    try {
      const result = await modal.openAsync(
        <FormModal id={0} entry={entry} isEdit={true} verticals={verticals} companies={companies} />
      );
      console.log(result);
    } catch (error) {
      setNotify('Error updating landing page', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

  const deleteEntry = (entry) => {
    const url = route('vertical_landing_pages.destroy', entry.id);
    destroy(url, {
      preserveScroll: true,
      preserveState: true,
    });
  };

  const showDeleteModal = async (entry) => {
    const confirmed = await modal.confirm({
      title: 'Delete Landing Page',
      description: 'This action cannot be undone. Are you sure you want to continue?',
      confirmText: 'Delete',
      cancelText: 'Cancel',
      destructive: true,
    });
    if (confirmed) {
      setNotify('Deleting landing page', 'info');
      deleteEntry(entry);
    }
  };

  return (
    <VerticalLandingPagesContext.Provider
      value={{
        currentRow,
        setCurrentRow,
        showCreateModal,
        showEditModal,
        showDeleteModal,
        resetTrigger,
        setResetTrigger,
        sorting,
        setSorting,
        globalFilter,
        setGlobalFilter,
        pagination,
        setPagination,
        columnFilters,
        setColumnFilters,
      }}
    >
      {children}
    </VerticalLandingPagesContext.Provider>
  );
}
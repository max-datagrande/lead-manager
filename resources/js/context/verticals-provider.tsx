import { FormModal } from '@/components/verticals/index';
import { useModal } from '@/hooks/use-modal';
import { useToast } from '@/hooks/use-toast';
import { getSortState } from '@/utils/table';
import { useForm, usePage } from '@inertiajs/react';
import { createContext, useState } from 'react';

export const VerticalsContext = createContext(null);

export function VerticalsProvider({ children }) {
  const { filters } = usePage().props as any;

  const modal = useModal();
  const { addMessage: setNotify } = useToast();
  const [currentRow, setCurrentRow] = useState(null);
  const [resetTrigger, setResetTrigger] = useState(false);
  const [sorting, setSorting] = useState(filters?.sort ? getSortState(filters.sort) : []);
  const [columnFilters, setColumnFilters] = useState([]);
  const [globalFilter, setGlobalFilter] = useState('');
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 });
  const { delete: destroy } = useForm();

  const showCreateModal = async () => {
    try {
      const result = await modal.openAsync(<FormModal id={0} />);
      console.log(result);
    } catch (error) {
      setNotify('Error creating vertical', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

  const showEditModal = async (entry) => {
    try {
      const result = await modal.openAsync(<FormModal id={0} entry={entry} isEdit={true} />);
      console.log(result);
    } catch (error) {
      setNotify('Error updating vertical', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

  const deleteEntry = async (entry) => {
    const url = route('verticals.destroy', entry.id);
    destroy(url, {
      preserveScroll: true,
      preserveState: true,
    });
  };

  const showDeleteModal = async (entry) => {
    const confirmed = await modal.confirm({
      title: 'Delete Vertical',
      description: 'This action cannot be undone. Are you sure you want to continue?',
      confirmText: 'Delete',
      cancelText: 'Cancel',
      destructive: true,
    });
    if (confirmed) {
      setNotify('Deleting vertical', 'info');
      deleteEntry(entry);
    }
  };

  return (
    <VerticalsContext.Provider
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
        columnFilters,
        setColumnFilters,
        globalFilter,
        setGlobalFilter,
        pagination,
        setPagination,
      }}
    >
      {children}
    </VerticalsContext.Provider>
  );
}
import { FormModal } from '@/components/offerwall';
import { useModal } from '@/hooks/use-modal';
import { useToast } from '@/hooks/use-toast';
import { getSortState } from '@/utils/table';
import { useForm } from '@inertiajs/react';
import { createContext, useState } from 'react';

export const OfferwallContext = createContext(null);

export function OfferwallProvider({ children, initialState }) {
  const modal = useModal();
  const inistialSorting = typeof initialState?.sort === 'string' ? getSortState(initialState.sort) : [];
  const { addMessage: setNotify } = useToast();
  const [currentRow, setCurrentRow] = useState(null);
  const [sorting, setSorting] = useState(inistialSorting);
  const [globalFilter, setGlobalFilter] = useState('');
  const [pagination, setPagination] = useState({
    pageIndex: 0, //initial page index
    pageSize: 10, //default page size
  });
  const { delete: destroy, processing } = useForm();
  const showCreateModal = async () => {
    try {
      const result = await modal.openAsync(<FormModal />);
      console.log(result);
      result && setNotify('Offerwall mix created successfully!', 'success');
    } catch (error) {
      setNotify('Error creating offerwall mix', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

  const showEditModal = async (entry) => {
    try {
      const result = await modal.openAsync(<FormModal entry={entry} isEdit={true} />);
      console.log(result);
    } catch (error) {
      setNotify('Error updating offerwall mix', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

  const deleteEntry = async (entry) => {
    const url = route('offerwall.destroy', entry.id);
    destroy(url, {
      preserveScroll: true,
      preserveState: true,
    });
  };
  const showDeleteModal = async (entry) => {
    const confirmed = await modal.confirm({
      title: 'Delete element',
      description: 'This action cannot be undone. Are you sure you want to continue?',
      confirmText: 'Delete',
      cancelText: 'Cancel',
      destructive: true,
    });
    if (confirmed) {
      setNotify('Deleting offerwall mix', 'info');
      deleteEntry(entry);
    }
  };

  return (
    <OfferwallContext.Provider
      value={{
        currentRow,
        setCurrentRow,
        showCreateModal,
        showEditModal,
        showDeleteModal,
        sorting,
        setSorting,
        globalFilter,
        setGlobalFilter,
        pagination,
        setPagination,
      }}
    >
      {children}
    </OfferwallContext.Provider>
  );
}

import { FormModal } from '@/components/fields/index';
import { useModal } from '@/hooks/use-modal';
import { useToast } from '@/hooks/use-toast';
import { getSortState } from '@/utils/table';
import { router, useForm } from '@inertiajs/react';
import axios from 'axios';
import { createContext, useState } from 'react';

export const FieldsContext = createContext(null);

export function FieldsProvider({ children, initialState }) {
  //Props sort
  const { sort } = initialState;
  const modal = useModal();
  const { addMessage: setNotify } = useToast();
  const [currentRow, setCurrentRow] = useState(null);
  const [resetTrigger, setResetTrigger] = useState(false);
  const [sorting, setSorting] = useState(sort ? getSortState(sort) : []);
  const [globalFilter, setGlobalFilter] = useState('');
  const [pagination, setPagination] = useState({
    pageIndex: 0, //initial page index
    pageSize: 10, //default page size
  });
  const { delete: destroy, processing } = useForm();
  const showCreateModal = async () => {
    try {
      const result = await modal.openAsync(<FormModal id={0} />);
      console.log(result);
    } catch (error) {
      setNotify('Error creating field entry', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

  const showEditModal = async (entry) => {
    try {
      const result = await modal.openAsync(<FormModal id={0} entry={entry} isEdit={true} />);
      console.log(result);
    } catch (error) {
      setNotify('Error updating field entry', 'error');
      console.log('Modal cancelled or error:', error);
    }
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
      setNotify('Deleting field', 'info');
      deleteEntry(entry);
    }
  };

  const deleteEntry = async (entry) => {
    const url = route('fields.destroy', entry.id);
    destroy(url, {
      preserveScroll: true,
      preserveState: true,
    });
  };

  const confirmSync = async () => {
    const confirmed = await modal.confirm({
      title: 'Sync fields',
      description:
        'This action will sync all fields from production to local. Are you sure you want to sync from production? This will TRUNCATE your local fields table!',
      confirmText: 'Sync',
      cancelText: 'Cancel',
      destructive: true,
    });
    if (confirmed) {
      setNotify('Syncing fields', 'info');
      syncFields();
    }
  };
  const syncFields = async () => {
    const url = route('api.fields.import');
    try {
      const response = await axios.post(url);
      const notify = response.data.message || 'Sync completed!';
      setNotify(notify, 'success');
      router.reload();
    } catch (error) {
      const errorMessage = error.response?.data?.error || 'An unknown error occurred.';
      console.log(error);
      setNotify(errorMessage, 'error');
    }
  };

  return (
    <FieldsContext.Provider
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
        confirmSync,
      }}
    >
      {children}
    </FieldsContext.Provider>
  );
}

import { FormModal } from '@/components/companies/index';
import { useModal } from '@/hooks/use-modal';
import { useToast } from '@/hooks/use-toast';
import { getSortState } from '@/utils/table';
import { router, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { createContext, useState } from 'react';

export const CompaniesContext = createContext(null);

export function CompaniesProvider({ children }) {
  //Props sort
  const {
    filters: { sort },
  } = usePage().props;
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
      setNotify('Error creating company entry', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

  const showEditModal = async (entry) => {
    try {
      const result = await modal.openAsync(<FormModal id={0} entry={entry} isEdit={true} />);
      console.log(result);
    } catch (error) {
      setNotify('Error updating company entry', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

  const deleteEntry = async (entry) => {
    const url = route('companies.destroy', entry.id);
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
      setNotify('Deleting company', 'info');
      deleteEntry(entry);
    }
  };

  const confirmSync = async () => {
    const confirmed = await modal.confirm({
      title: 'Sync companies',
      description:
        'This action will sync all companies from production to local. Are you sure you want to sync from production? This will TRUNCATE your local companies table!',
      confirmText: 'Sync',
      cancelText: 'Cancel',
      destructive: true,
    });
    if (confirmed) {
      setNotify('Syncing companies', 'info');
      syncCompanies();
    }
  };

  const syncCompanies = async () => {
    const url = route('api.companies.import');
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
    <CompaniesContext.Provider
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
    </CompaniesContext.Provider>
  );
}

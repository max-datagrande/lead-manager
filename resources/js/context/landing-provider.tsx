import { FormModal } from '@/components/landing-pages/index';
import { useModal } from '@/hooks/use-modal';
import { useToast } from '@/hooks/use-toast';
import { getSortState } from '@/utils/table';
import { useForm, usePage, router } from '@inertiajs/react';
import { createContext, useState } from 'react';

export const LandingPagesContext = createContext(null);

export function LandingPagesProvider({ children, verticals, companies }) {
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
      const result = await modal.openAsync(<FormModal entry={null} verticals={verticals} companies={companies} />);
      console.log(result);
    } catch (error) {
      setNotify('Error creating landing page', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

  const showEditModal = async (entry) => {
    try {
      const result = await modal.openAsync(<FormModal entry={entry} isEdit={true} verticals={verticals} companies={companies} />);
      console.log(result);
    } catch (error) {
      setNotify('Error updating landing page', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

  const showVersions = async (entry) => {
   router.get(
         route('landing_pages.versions.index', {
           landing_page: entry.id,
         }),
       );
  };

  const deleteEntry = (entry) => {
    const url = route('landing_pages.destroy', entry.id);
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
    <LandingPagesContext.Provider
      value={{
        currentRow,
        setCurrentRow,
        showCreateModal,
        showEditModal,
        showVersions,
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
    </LandingPagesContext.Provider>
  );
}

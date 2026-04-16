import { FormModalVersion } from '@/components/landing-pages-version/form-versions-modal';
import { FormModal } from '@/components/landing-pages/index';
import { useModal } from '@/hooks/use-modal';
import { useToast } from '@/hooks/use-toast';
import { getSortState } from '@/utils/table';
import { useForm, usePage } from '@inertiajs/react';
import { createContext, useState } from 'react';

export const LandingPagesVersionContext = createContext(null);

export function LandingPagesVersionProvider({ children, landingPage }) {
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

  const showCreateModal = async (entry) => {
    try {
      const result = await modal.openAsync(<FormModalVersion entry={entry} landingPageId={landingPage.id} />);
      console.log(result);
    } catch (error) {
      setNotify('Error creating landing page', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

  const showEditModal = async (entry) => {
    try {
      const result = await modal.openAsync(<FormModalVersion landingPageId={landingPage.id} entry={entry} isEdit={true} />);
      console.log(result);
    } catch (error) {
      setNotify('Error updating landing page', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

  const showEditVersionsModal = async (entry) => {
    window.location.href = `/landing_pages/${entry.id}/versions`;
  };

  const deleteEntry = (entry) => {
    const url = route('landing_pages.versions.destroy', {version: entry.id, landing_page: landingPage.id});
    destroy(url, {
      preserveScroll: true,
      preserveState: true,
    });
  };

  const showDeleteModal = async (entry) => {
    const confirmed = await modal.confirm({
      title: 'Delete Landing Page Version',
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
    <LandingPagesVersionContext.Provider
      value={{
        currentRow,
        setCurrentRow,
        showCreateModal,
        showEditModal,
        showEditVersionsModal,
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
    </LandingPagesVersionContext.Provider>
  );
}

import WhitelistDeleteModal from '@/components/whitelist/whitelist-delete-modal';
import WhitelistFormModal from '@/components/whitelist/whitelist-form-modal';
import { useModal } from '@/hooks/use-modal';
import { getSortState } from '@/utils/table';
import { usePage } from '@inertiajs/react';
import { createContext, useState } from 'react';
import { useToast } from '@/components/ui/toaster';

export const WhitelistContext = createContext(null);

export function WhitelistProvider({ children }) {
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

  const showCreateModal = async () => {
    try {
      const result = await modal.openAsync(<WhitelistFormModal id={0} />);
    } catch (error) {
      setNotify('Error creating whitelist entry', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

  const showEditModal = async (entry) => {
    try {
      const result = await modal.openAsync(<WhitelistFormModal id={0} entry={entry} isEdit={true} />);
      console.log(result);
    } catch (error) {
      setNotify('Error updating whitelist entry', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };
  const showDeleteModal = async (entry) => {
    try {
      const result = await modal.openAsync(<WhitelistDeleteModal id={0} entry={entry} />);
      console.log(result);
    } catch (error) {
      setNotify('Error deleting whitelist entry', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

  return (
    <WhitelistContext.Provider
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
      }}
    >
      {children}
    </WhitelistContext.Provider>
  );
}

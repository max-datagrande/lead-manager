import WhitelistDeleteModal from '@/components/whitelist/whitelist-delete-modal';
import WhitelistFormModal from '@/components/whitelist/whitelist-form-modal';
import { useDebouncedFunction } from '@/hooks/use-debounce';
import { useModal } from '@/hooks/use-modal';
import { getSortState, serializeSort } from '@/utils/table';
import { router, usePage } from '@inertiajs/react';
import { createContext, useCallback, useRef, useState } from 'react';
import { route } from 'ziggy-js';

import { useToast } from '@/components/ui/toaster';

export const WhitelistContext = createContext(null);

export function WhitelistProvider({ children }) {
  const { state } = usePage().props;
  const { addMessage: setNotify } = useToast();
  const modal = useModal();
  const filters = state?.filters ?? [];
  const [currentRow, setCurrentRow] = useState(null);
  const [resetTrigger, setResetTrigger] = useState(false);
  const [globalFilter, setGlobalFilter] = useState(state?.search ?? '');
  const [sorting, setSorting] = useState(state?.sort ? getSortState(state?.sort) : []);
  const [columnFilters, setColumnFilters] = useState(filters);
  const [isLoading, setIsLoading] = useState(false);
  const isFirstRender = useRef(true);

  const setFilter = (id, value) => {
    setColumnFilters((prev) => {
      const others = prev.filter((f) => f.id !== id);
      return value ? [...others, { id, value }] : others;
    });
  };

  const handleClearFilters = () => {
    setColumnFilters([]);
  };

  const getWhitelistEntries = useDebouncedFunction(
    useCallback(
      (newData) => {
        if (isFirstRender.current) {
          isFirstRender.current = false;
          return;
        }
        setIsLoading(true);
        const data = {
          search: globalFilter || undefined,
          sort: serializeSort(sorting),
          filters: JSON.stringify(columnFilters || []),
          ...newData,
        };
        const url = route('whitelist.index');
        const options = {
          only: ['rows', 'meta', 'state'],
          replace: true,
          preserveState: true,
          preserveScroll: true,
          onFinish: () => setIsLoading(false),
        };
        router.get(url, data, options);
      },
      [sorting, columnFilters, globalFilter],
    ),
    200,
  );

  const showCreateModal = async () => {
    try {
      const result = await modal.openAsync(<WhitelistFormModal id={0} />);
      if (result) {
        getWhitelistEntries();
      }
    } catch (error) {
      setNotify('Error creating whitelist entry', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

  const showEditModal = async (entry) => {
    try {
      const result = await modal.openAsync(<WhitelistFormModal id={0} entry={entry} isEdit={true} />);
      console.log(result);
      if (result) {
        getWhitelistEntries();
      }
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
        getWhitelistEntries,
        setFilter,
        handleClearFilters,
        columnFilters,
        setColumnFilters,
        sorting,
        setSorting,
        isFirstRender,
        globalFilter,
        setGlobalFilter,
        currentRow,
        setCurrentRow,
        resetTrigger,
        setResetTrigger,
        isLoading,
        showCreateModal,
        showEditModal,
        showDeleteModal,
      }}
    >
      {children}
    </WhitelistContext.Provider>
  );
}

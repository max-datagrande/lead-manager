import { useDebouncedFunction } from '@/hooks/use-debounce';
import { getSortState, serializeSort } from '@/utils/table';
import { router, usePage } from '@inertiajs/react';
import { createContext, useCallback, useRef, useState } from 'react';
import { route } from 'ziggy-js';
import { useModal } from '@/hooks/use-modal';

export const PostbackContext = createContext(null);

export function PostbackProvider({ children }) {
  const { state } = usePage().props;
  const modal = useModal();
  const filters = useRef(state?.filters ?? []);
  const [currentRow, setCurrentRow] = useState(null);
  const [resetTrigger, setResetTrigger] = useState(false);
  const [globalFilter, setGlobalFilter] = useState(state?.search ?? '');
  const [sorting, setSorting] = useState(state?.sort ? getSortState(state?.sort) : []);
  const [columnFilters, setColumnFilters] = useState(filters.current);
  const [isLoading, setIsLoading] = useState(false);
  const isFirstRender = useRef(true);

  const showRequestViewer = async (postback) => {
    const { PostbackApiRequestsViewer } = await import('@/components/postback/postback-api-requests-viewer');
    modal.open(<PostbackApiRequestsViewer postbackId={postback.id} />, { className: 'max-w-4xl sm:max-w-4xl w-full' });
  };

  const handleClearFilters = () => {
    setColumnFilters([]);
  };

  const showDeleteModal = async (postback) => {
    const isConfirmed = await modal.confirm({
      title: 'Delete element',
      description: 'This action cannot be undone. Are you sure you want to continue?',
      confirmText: 'Delete',
      cancelText: 'Cancel',
      destructive: true,
    });
    if (isConfirmed) {
      setNotify('Deleting postback', 'info');
      deletePostback(postback);
    }
  };

  const deletePostback = async (entry) => {
    const url = route('companies.destroy', entry.id);
    destroy(url, {
      preserveScroll: true,
      preserveState: true,
    });
  };

  const getPostbacks = useDebouncedFunction(
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
        const url = route('postbacks.index');
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

  return (
    <PostbackContext.Provider
      value={{
        getPostbacks,
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
        showRequestViewer,
        resetTrigger,
        setResetTrigger,
        isLoading,
        showDeleteModal,
      }}
    >
      {children}
    </PostbackContext.Provider>
  );
}

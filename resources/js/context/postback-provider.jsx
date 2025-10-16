import { useDebouncedFunction } from '@/hooks/use-debounce';
import { useModal } from '@/hooks/use-modal';
import { useToast } from '@/hooks/use-toast';
import { getSortState, serializeSort } from '@/utils/table';
import { router, useForm } from '@inertiajs/react';
import { createContext, useRef, useState } from 'react';
import { route } from 'ziggy-js';
export const PostbackContext = createContext(null);

export function PostbackProvider({ children, initialState }) {
  const modal = useModal();
  const filters = useRef(initialState?.filters ?? []);
  const [globalFilter, setGlobalFilter] = useState(initialState?.search ?? '');
  const [sorting, setSorting] = useState(initialState?.sort ? getSortState(initialState?.sort) : []);
  const [columnFilters, setColumnFilters] = useState(filters.current);
  const [isLoading, setIsLoading] = useState(false);
  const isFirstRender = useRef(true);
  const { addMessage: setNotify } = useToast();
  const { delete: destroy } = useForm();
  const [pagination, setPagination] = useState({
    pageIndex: (initialState.page ?? 1) - 1,
    pageSize: initialState.per_page ?? 10,
  });
  const showStatusModal = async (postback) => {
    try {
      const { UpdateStatusModal } = await import('@/components/postback/update-status-modal');
      const result = await modal.openAsync(<UpdateStatusModal postback={postback} />);
      console.log(result);
    } catch (error) {
      setNotify('Error updating postback entry', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

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

  const handleForceSync = async (postback) => {
    const isConfirmed = await modal.confirm({
      title: 'Force Sync Postback',
      description: `This will attempt to find a matching conversion for click ID: ${postback.click_id}. This action may take a few seconds.`,
      confirmText: 'Sync',
      cancelText: 'Cancel',
    });

    if (isConfirmed) {
      const url = route('postbacks.force-sync', postback.id);
      router.post(
        url,
        {},
        {
          preserveScroll: true,
          onSuccess: (page) => {
            console.log(page);
          },
          onError: (errors) => {
            console.error('Error syncing postback:', errors);
          },
        },
      );
    }
  };

  const deletePostback = async (entry) => {
    const url = route('postbacks.destroy', entry.id);
    destroy(url, {
      preserveScroll: true,
      preserveState: true,
    });
  };
  const updatePostbacks = (newData) => {
    if (isFirstRender.current) {
      isFirstRender.current = false;
      return;
    }
    setIsLoading(true);
    const data = {
      search: globalFilter || undefined,
      sort: serializeSort(sorting),
      filters: JSON.stringify(columnFilters || []),
      page: pagination.pageIndex + 1,
      per_page: pagination.pageSize,
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
  };

  const getPostbacks = useDebouncedFunction(updatePostbacks, 200);

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
        showRequestViewer,
        isLoading,
        showDeleteModal,
        showStatusModal,
        handleForceSync,
        pagination,
        setPagination,
      }}
    >
      {children}
    </PostbackContext.Provider>
  );
}

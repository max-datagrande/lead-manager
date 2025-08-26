import { PostbackApiRequestsViewer } from '@/components/postback';
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useDebouncedFunction } from '@/hooks/use-debounce';
import { useModal } from '@/hooks/use-modal';
import { getSortState, serializeSort } from '@/utils/table';
import { router, usePage } from '@inertiajs/react';
import { createContext, useCallback, useRef, useState } from 'react';
import { route } from 'ziggy-js';

export const PostbackContext = createContext(null);

export function PostbackProvider({ children }) {
  const { state } = usePage().props;
  const filters = useRef(state?.filters ?? []);
  const [currentRow, setCurrentRow] = useState(null);
  const [resetTrigger, setResetTrigger] = useState(false);
  const [globalFilter, setGlobalFilter] = useState(state?.search ?? '');
  const [sorting, setSorting] = useState(state?.sort ? getSortState(state?.sort) : []);
  const [columnFilters, setColumnFilters] = useState(filters.current);
  const isFirstRender = useRef(true);
  const modal = useModal();

  const showRequestViewer = (postback) => {
    modal.open(
      <>
        <DialogHeader>
          <DialogTitle>API Requests - Postback #{postback.id}</DialogTitle>
          <DialogDescription className="sr-only">
            Displays all API requests made for this postback, including request and response data.
          </DialogDescription>
        </DialogHeader>
        <PostbackApiRequestsViewer postbackId={postback.id} />
      </>,
    );
  };

  const handleClearFilters = () => {
    setColumnFilters([]);
  };

  const getPostbacks = useDebouncedFunction(useCallback((newData) => {
    console.log('me estoy renderizando');
    if (isFirstRender.current) {
      isFirstRender.current = false;
      return;
    }
    const data = {
      search: globalFilter || undefined,
      sort: serializeSort(sorting),
      filters: JSON.stringify(columnFilters || []),
      ...newData,
    };
    const url = route('postbacks.index');
    const options = { only: ['rows', 'meta', 'state'], replace: true, preserveState: true, preserveScroll: true };
    router.get(url, data, options);
  }, []), 300);

  return <PostbackContext.Provider value={{
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
  }}>{children}</PostbackContext.Provider>;
}

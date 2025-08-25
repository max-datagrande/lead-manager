import { useDebouncedFunction } from '@/hooks/use-debounce';
import { getSortState, serializeSort } from '@/utils/table';
import { router, usePage } from '@inertiajs/react';
import { createContext, useRef, useState } from 'react';
import { route } from 'ziggy-js';
import { useModal } from '@/hooks/use-modal';
import { PostbackApiRequestsViewer } from '@/components/postback';

export const PostbackContext = createContext(null);

export function PostbackProvider({ children }) {
  const { state } = usePage().props;
  const filters = state?.filters ?? [];
  const [currentRow, setCurrentRow] = useState(null);
  const [globalFilter, setGlobalFilter] = useState(state?.search ?? '');
  const [sorting, setSorting] = useState(state?.sort ? getSortState(state?.sort) : []);
  const [columnFilters, setColumnFilters] = useState(filters);
  const isFirstRender = useRef(true);
  const modal = useModal();

  const showRequestViewer = (postback) => {
    modal.open(
      <div className="flex items-center gap-2">
        <Dialog>
          <DialogTrigger asChild>
            <Button variant="outline" size="sm" className="h-8 px-2">
              <Eye className="h-3 w-3 mr-1" />
              API Requests
            </Button>
          </DialogTrigger>
          <DialogContent className="max-w-4xl max-h-[80vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle>
                API Requests - Postback #{postback.id}
              </DialogTitle>
            </DialogHeader>
            <PostbackApiRequestsViewer postbackId={postback.id} />
          </DialogContent>
        </Dialog>
      </div>
    );
  };
  const setFilter = (id, value) => {
    setColumnFilters((prev) => {
      const others = prev.filter((f) => f.id !== id);
      return value ? [...others, { id, value }] : others;
    });
  };

  const handleClearFilters = () => {
    setColumnFilters([]);
  };

  const getPostbacks = useDebouncedFunction((newData) => {
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
  }, 300);

  const contextValue = {
    getPostbacks,
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
    showRequestViewer,
  };

  return <PostbackContext.Provider value={contextValue}>{children}</PostbackContext.Provider>;
}

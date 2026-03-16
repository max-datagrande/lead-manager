import { ModalDetails } from '@/components/postbacks';
import { useModal } from '@/hooks/use-modal';
import { useToast } from '@/hooks/use-toast';
import { router, useForm } from '@inertiajs/react';
import { createContext, useState } from 'react';

export const PostbacksContext = createContext(null);

export function PostbacksProvider({ children }) {
  const modal = useModal();
  const { addMessage } = useToast();
  const [sorting, setSorting] = useState([]);
  const [globalFilter, setGlobalFilter] = useState('');
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 });
  const [resetTrigger, setResetTrigger] = useState(false);
  const { delete: destroy } = useForm();

  const showModalDetails = (entry) => {
    modal.open(<ModalDetails postback={entry} />, { maxWidth: 'sm:max-w-2xl' });
  };

  const copyUrl = (entry) => {
    navigator.clipboard.writeText(entry.generated_url);
    addMessage('URL copied to clipboard', 'success');
  };

  const showEditModal = (entry) => {
    router.visit(route('postbacks.edit', entry.id));
  };

  const showDeleteModal = async (entry) => {
    const confirmed = await modal.confirm({
      title: 'Delete Postback',
      description: `Are you sure you want to delete "${entry.name}"? This action cannot be undone.`,
      confirmText: 'Delete',
      cancelText: 'Cancel',
      destructive: true,
    });
    if (confirmed) {
      destroy(route('postbacks.destroy', entry.id), { preserveScroll: true, preserveState: true });
    }
  };

  return (
    <PostbacksContext.Provider
      value={{
        showModalDetails,
        copyUrl,
        showEditModal,
        showDeleteModal,
        sorting,
        setSorting,
        globalFilter,
        setGlobalFilter,
        pagination,
        setPagination,
        resetTrigger,
        setResetTrigger,
      }}
    >
      {children}
    </PostbacksContext.Provider>
  );
}

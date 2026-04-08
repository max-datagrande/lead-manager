import { FormModal } from '@/components/alert-channels';
import { useModal } from '@/hooks/use-modal';
import { useToast } from '@/hooks/use-toast';
import { type AlertChannel, type ChannelType } from '@/pages/alert-channels/index';
import { useForm } from '@inertiajs/react';
import { createContext, useState } from 'react';

type AlertChannelsContextType = {
  channelTypes: ChannelType[];
  showCreateModal: () => void;
  showEditModal: (entry: AlertChannel) => void;
  showDeleteModal: (entry: AlertChannel) => void;
  resetTrigger: boolean;
  setResetTrigger: (v: boolean) => void;
  sorting: any[];
  setSorting: (v: any) => void;
  columnFilters: any[];
  setColumnFilters: (v: any) => void;
  globalFilter: string;
  setGlobalFilter: (v: string) => void;
  pagination: { pageIndex: number; pageSize: number };
  setPagination: (v: any) => void;
};

export const AlertChannelsContext = createContext<AlertChannelsContextType | null>(null);

export function AlertChannelsProvider({ children, channelTypes }: { children: React.ReactNode; channelTypes: ChannelType[] }) {
  const modal = useModal();
  const { addMessage: setNotify } = useToast();
  const [resetTrigger, setResetTrigger] = useState(false);
  const [sorting, setSorting] = useState([]);
  const [columnFilters, setColumnFilters] = useState([]);
  const [globalFilter, setGlobalFilter] = useState('');
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 });
  const { delete: destroy } = useForm();

  const showCreateModal = async () => {
    try {
      await modal.openAsync(<FormModal channelTypes={channelTypes} />);
    } catch (error) {
      setNotify('Error creating alert channel', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

  const showEditModal = async (entry: AlertChannel) => {
    try {
      await modal.openAsync(<FormModal channelTypes={channelTypes} entry={entry} isEdit />);
    } catch (error) {
      setNotify('Error updating alert channel', 'error');
      console.log('Modal cancelled or error:', error);
    }
  };

  const deleteEntry = async (entry: AlertChannel) => {
    const url = route('alert-channels.destroy', entry.id);
    destroy(url, {
      preserveScroll: true,
      preserveState: true,
    });
  };

  const showDeleteModal = async (entry: AlertChannel) => {
    const confirmed = await modal.confirm({
      title: 'Delete Alert Channel',
      description: 'This action cannot be undone. Are you sure you want to continue?',
      confirmText: 'Delete',
      cancelText: 'Cancel',
      destructive: true,
    });
    if (confirmed) {
      setNotify('Deleting alert channel', 'info');
      deleteEntry(entry);
    }
  };

  return (
    <AlertChannelsContext.Provider
      value={{
        channelTypes,
        showCreateModal,
        showEditModal,
        showDeleteModal,
        resetTrigger,
        setResetTrigger,
        sorting,
        setSorting,
        columnFilters,
        setColumnFilters,
        globalFilter,
        setGlobalFilter,
        pagination,
        setPagination,
      }}
    >
      {children}
    </AlertChannelsContext.Provider>
  );
}

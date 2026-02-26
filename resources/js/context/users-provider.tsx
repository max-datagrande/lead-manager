import { UserDeactivateModal, UserFormModal } from '@/components/users';
import { useModal } from '@/hooks/use-modal';
import { useToast } from '@/hooks/use-toast';
import { getSortState } from '@/utils/table';
import { usePage } from '@inertiajs/react';
import { createContext, useState } from 'react';

export const UsersContext = createContext<any>(null);

export function UsersProvider({ children }: { children: React.ReactNode }) {
  const {
    props: {
      auth,
      state = { sort: 'created_at:desc' },
      roles = [],
    },
  } = usePage<any>();

  const modal = useModal();
  const { addMessage: notify } = useToast();

  const [resetTrigger, setResetTrigger] = useState(false);
  const [sorting, setSorting] = useState(state.sort ? getSortState(state.sort) : []);
  const [globalFilter, setGlobalFilter] = useState('');
  const [pagination, setPagination] = useState({ pageIndex: 0, pageSize: 10 });

  const showCreateModal = async () => {
    try {
      await modal.openAsync(<UserFormModal roles={roles} />);
    } catch {
      notify('Error creating user.', 'error');
    }
  };

  const showEditModal = async (user: any) => {
    try {
      await modal.openAsync(<UserFormModal user={user} roles={roles} isEdit={true} />);
    } catch {
      notify('Error updating user.', 'error');
    }
  };

  const showDeactivateModal = async (user: any) => {
    try {
      await modal.openAsync(<UserDeactivateModal user={user} />);
    } catch {
      notify('Error deactivating user.', 'error');
    }
  };

  return (
    <UsersContext.Provider
      value={{
        authUser: auth.user,
        roles,
        showCreateModal,
        showEditModal,
        showDeactivateModal,
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
    </UsersContext.Provider>
  );
}

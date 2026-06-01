import { UserDeactivateModal, UserFormModal } from '@/components/users';
import { useModal } from '@/hooks/use-modal';
import { useToast } from '@/hooks/use-toast';
import { getSortState } from '@/utils/table';
import { router, usePage } from '@inertiajs/react';
import { createContext, useState } from 'react';
import { route } from 'ziggy-js';

export const UsersContext = createContext<any>(null);

export function UsersProvider({ children }: { children: React.ReactNode }) {
  const {
    props: { auth, state = { sort: 'created_at:desc' }, roles = [] },
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

  const showSendResetModal = async (user: any) => {
    const confirmed = await modal.warnConfirm({
      title: 'Send password reset link',
      description: `An email with a password reset link will be sent to ${user.email}.`,
      consequences: ['The user receives an email with a link to set a new password.', 'Any previous reset link for this user will stop working.'],
      confirmText: 'Send link',
      confirmCode: user.email,
    });

    if (!confirmed) return;

    router.post(
      route('admin.users.password-reset', user.id),
      {},
      {
        preserveScroll: true,
        onError: () => notify('Error sending password reset link.', 'error'),
      },
    );
  };

  return (
    <UsersContext.Provider
      value={{
        authUser: auth.user,
        roles,
        showCreateModal,
        showEditModal,
        showDeactivateModal,
        showSendResetModal,
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

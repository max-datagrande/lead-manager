import { Button } from '@/components/ui/button';
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useCurrentModalId, useModal } from '@/hooks/use-modal';
import { useForm } from '@inertiajs/react';

export default function UserDeactivateModal({ user }: { user: any }) {
  const modal = useModal();
  const modalId = useCurrentModalId();
  const { delete: destroy, processing } = useForm();

  const onConfirm = () => {
    destroy(route('admin.users.destroy', user.id), {
      preserveScroll: true,
      onSuccess: () => {
        modal.resolve(modalId, true);
      },
    });
  };

  return (
    <>
      <DialogHeader>
        <DialogTitle>Deactivate user</DialogTitle>
        <DialogDescription>
          This action will deactivate <strong>{user.email}</strong>. The account will remain in the system but the user will not be able to log in.
        </DialogDescription>
      </DialogHeader>

      <div className="flex justify-end gap-2">
        <Button variant="outline" onClick={() => modal.resolve(modalId, false)} disabled={processing}>
          Cancel
        </Button>
        <Button variant="destructive" onClick={onConfirm} disabled={processing}>
          {processing ? 'Deactivating...' : 'Deactivate'}
        </Button>
      </div>
    </>
  );
}

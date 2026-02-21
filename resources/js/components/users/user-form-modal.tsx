import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useCurrentModalId, useModal } from '@/hooks/use-modal';
import { useForm } from '@inertiajs/react';

interface UserFormModalProps {
  user?: any;
  roles: string[];
  isEdit?: boolean;
}

export default function UserFormModal({ user, roles, isEdit = false }: UserFormModalProps) {
  const modal = useModal();
  const modalId = useCurrentModalId();

  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: user?.name || '',
    email: user?.email || '',
    role: user?.role || 'user',
    is_active: user?.is_active ?? true,
  });

  const submit = (event: React.FormEvent) => {
    event.preventDefault();

    const url = isEdit ? route('admin.users.update', user.id) : route('admin.users.store');
    const options = {
      preserveScroll: true,
      onSuccess: () => {
        modal.resolve(modalId, true);
        reset();
      },
    };

    if (isEdit) {
      put(url, options);
      return;
    }

    post(url, options);
  };

  const closeModal = () => {
    modal.resolve(modalId, false);
    reset();
  };

  return (
    <>
      <DialogHeader>
        <DialogTitle>{isEdit ? 'Edit User' : 'Create User'}</DialogTitle>
        <DialogDescription>
          {isEdit ? 'Update user details and permissions.' : 'Create a new user and send invitation email to set their password.'}
        </DialogDescription>
      </DialogHeader>

      <form className="space-y-4" onSubmit={submit}>
        <div className="space-y-2">
          <Label htmlFor="name">Name</Label>
          <Input id="name" value={data.name} onChange={(event) => setData('name', event.target.value)} placeholder="John Doe" />
          <InputError message={errors.name} />
        </div>

        <div className="space-y-2">
          <Label htmlFor="email">Email</Label>
          <Input
            id="email"
            type="email"
            value={data.email}
            onChange={(event) => setData('email', event.target.value)}
            placeholder="john@company.com"
          />
          <InputError message={errors.email} />
        </div>

        <div className="space-y-2">
          <Label htmlFor="role">Role</Label>
          <Select value={data.role} onValueChange={(value) => setData('role', value)}>
            <SelectTrigger>
              <SelectValue placeholder="Select role" />
            </SelectTrigger>
            <SelectContent>
              {roles.map((role: string) => (
                <SelectItem key={role} value={role}>
                  {role}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <InputError message={errors.role} />
        </div>

        <div className="flex items-center gap-2">
          <Switch id="is_active" checked={Boolean(data.is_active)} onCheckedChange={(checked) => setData('is_active', checked)} />
          <Label htmlFor="is_active">Active</Label>
        </div>
        <InputError message={errors.is_active} />

        <div className="flex justify-end gap-2 pt-2">
          <Button type="button" variant="outline" onClick={closeModal} disabled={processing}>
            Cancel
          </Button>
          <Button type="submit" disabled={processing}>
            {processing ? 'Saving...' : isEdit ? 'Update User' : 'Create User'}
          </Button>
        </div>
      </form>
    </>
  );
}

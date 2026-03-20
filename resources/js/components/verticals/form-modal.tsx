import { Button } from '@/components/ui/button';
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useCurrentModalId, useModal } from '@/hooks/use-modal';
import { useForm } from '@inertiajs/react';

export default function FormModal({ entry, isEdit = false }) {
  const modal = useModal();
  const modalId = useCurrentModalId();

  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: entry?.name ?? '',
    description: entry?.description ?? '',
    active: entry?.active ?? true,
  });

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const url = isEdit ? route('verticals.update', entry.id) : route('verticals.store');
    const options = {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        modal.resolve(modalId, true);
        reset();
      },
      onError: (errors) => console.log('Validation errors:', errors),
    };
    isEdit && entry?.id ? put(url, options) : post(url, options);
  };

  const handleCancel = () => {
    modal.resolve(modalId, false);
    reset();
  };

  return (
    <>
      <DialogHeader>
        <DialogTitle>{isEdit ? 'Edit Vertical' : 'Create Vertical'}</DialogTitle>
        <DialogDescription>{isEdit ? 'Edit the vertical details' : 'Add a new vertical'}</DialogDescription>
        {/* Active */}
        <div className="flex items-center space-x-2">
          <Switch id="is_active" checked={data.active} onCheckedChange={(checked: boolean) => setData('active', checked)} />
          <Label htmlFor="is_active">{data.active ? 'Active' : 'Inactive'}</Label>
        </div>
      </DialogHeader>

      <form onSubmit={handleSubmit} className="space-y-4">
        {/* Name */}
        <div className="space-y-2">
          <Label htmlFor="name">Name</Label>
          <Input
            id="name"
            type="text"
            value={data.name}
            onChange={(e) => setData('name', e.target.value)}
            placeholder="e.g. Autos, Health Insurance"
          />
          {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
        </div>

        {/* Description */}
        <div className="space-y-2">
          <Label htmlFor="description">Description</Label>
          <Input
            id="description"
            type="text"
            value={data.description}
            onChange={(e) => setData('description', e.target.value)}
            placeholder="Internal description (optional)"
          />
          {errors.description && <p className="text-sm text-destructive">{errors.description}</p>}
        </div>

        {/* Actions */}
        <div className="flex justify-end gap-2 pt-4">
          <Button type="button" variant="outline" onClick={handleCancel} disabled={processing}>
            Cancel
          </Button>
          <Button type="submit" disabled={processing || !data.name.trim()}>
            {processing ? 'Saving...' : isEdit ? 'Update' : 'Create'}
          </Button>
        </div>
      </form>
    </>
  );
}

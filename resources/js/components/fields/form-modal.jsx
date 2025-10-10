import { Button } from '@/components/ui/button';
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import TagInput from '@/components/ui/tag-input.jsx';
import { useCurrentModalId, useModal } from '@/hooks/use-modal';
import { useForm } from '@inertiajs/react';

/**
 * Modal component for creating and editing field entries
 */
export default function FormModal({ entry, isEdit = false }) {
  const modal = useModal();
  const modalId = useCurrentModalId();

  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: entry?.name ?? '',
    label: entry?.label ?? '',
    possible_values: entry?.possible_values ?? [],
  });

  /**
   * Validates the form data
   */
  const validateForm = () => {
    if (!data.name.trim()) {
      return 'Paameter name is required';
    }
    return null;
  };

  /**
   * Handles form submission
   */
  const handleSubmit = (e) => {
    e.preventDefault();

    const validationError = validateForm();
    if (validationError) {
      return;
    }

    const url = isEdit ? route('fields.update', entry.id) : route('fields.store');
    const options = {
      preserveState: true,
      preserveScroll: true,
      onSuccess: (response) => {
        modal.resolve(modalId, true);
        reset();
      },
      onError: (errors) => {
        console.log('Validation errors:', errors);
      },
    };
    isEdit && entry?.id ? put(url, options) : post(url, options);
  };

  /**
   * Handles modal cancellation
   */
  const handleCancel = () => {
    modal.resolve(modalId, false);
    reset();
  };

  const validationError = validateForm();

  return (
    <>
      <DialogHeader>
        <DialogTitle>{isEdit ? 'Edit Field' : 'Create Field'}</DialogTitle>
        <DialogDescription>{isEdit ? 'Edit the field details' : 'Add a new field'}</DialogDescription>
      </DialogHeader>

      <form onSubmit={handleSubmit} className="space-y-4">
        {/* Value Label */}
        <div className="space-y-2">
          <Label htmlFor="value">Friendly Name</Label>
          <Input
            id="label"
            type="text"
            value={data.label}
            onChange={(e) => setData('label', e.target.value)}
            placeholder="Friendly name for this field"
          />
          {errors.label && <p className="text-sm text-destructive">{errors.label}</p>}
        </div>

        {/* Value Field */}
        <div className="space-y-2">
          <Label htmlFor="name">Parameter Name</Label>
          <Input id="name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Parameter name" />
          {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
        </div>

        {/* Possible Values */}
        <div className="space-y-2">
          <Label htmlFor="possible_values">Possible Values</Label>
          <TagInput
            id="possible_values"
            value={data.possible_values}
            onChange={(values) => setData('possible_values', values)}
          />
          {errors.possible_values && <p className="text-sm text-destructive">{errors.possible_values}</p>}
        </div>

        {/* Form Actions */}
        <div className="flex justify-end gap-2 pt-4">
          <Button type="button" variant="outline" onClick={handleCancel} disabled={processing}>
            Cancel
          </Button>
          <Button type="submit" disabled={processing || !!validationError}>
            {processing ? 'Saving...' : isEdit ? 'Update' : 'Create'}
          </Button>
        </div>
      </form>
    </>
  );
}

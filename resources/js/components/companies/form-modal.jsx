import { Button } from '@/components/ui/button';
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCurrentModalId, useModal } from '@/hooks/use-modal';
import { useForm } from '@inertiajs/react';
import { Switch } from '@/components/ui/switch';

/**
 * Modal component for creating and editing company entries
 */
export default function FormModal({ entry, isEdit = false }) {
  const modal = useModal();
  const modalId = useCurrentModalId();

  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: entry?.name ?? '',
    contact_email: entry?.contact_email ?? '',
    contact_phone: entry?.contact_phone ?? '',
    company_name: entry?.contact_name ?? '',
    active: entry?.active ?? true,
  });

  /**
   * Validates the form data
   */
  const validateForm = () => {
    if (!data.name.trim()) {
      return 'Company name is required';
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

    const url = isEdit ? route('companies.update', entry.id) : route('companies.store');
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
        <DialogTitle>{isEdit ? 'Edit Company' : 'Create Company'}</DialogTitle>
        <DialogDescription>{isEdit ? 'Edit the company details' : 'Add a new company'}</DialogDescription>
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
            placeholder="Company Name"
          />
          {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
        </div>

        {/* Contact Name */}
        <div className="space-y-2">
          <Label htmlFor="contact_name">Contact Name</Label>
          <Input
            id="contact_name"
            type="text"
            value={data.contact_name}
            onChange={(e) => setData('contact_name', e.target.value)}
            placeholder="Contact Name (e.g., John Doe)"
          />
          {errors.contact_name && <p className="text-sm text-destructive">{errors.contact_name}</p>}
        </div>

        {/* Contact Email */}
        <div className="space-y-2">
          <Label htmlFor="contact_email">Contact Email</Label>
          <Input
            id="contact_email"
            type="email"
            value={data.contact_email}
            onChange={(e) => setData('contact_email', e.target.value)}
            placeholder="contact@example.com"
          />
          {errors.contact_email && <p className="text-sm text-destructive">{errors.contact_email}</p>}
        </div>

        {/* Contact Phone */}
        <div className="space-y-2">
          <Label htmlFor="contact_phone">Contact Phone</Label>
          <Input
            id="contact_phone"
            type="text"
            value={data.contact_phone}
            onChange={(e) => setData('contact_phone', e.target.value)}
            placeholder="+1234567890"
          />
          {errors.contact_phone && <p className="text-sm text-destructive">{errors.contact_phone}</p>}
        </div>

        {/* Active Switch */}
        <div className="flex items-center space-x-2">
          <Switch
            id="active"
            checked={data.active}
            onCheckedChange={(checked) => setData('active', checked)}
          />
          <Label htmlFor="active">Active</Label>
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

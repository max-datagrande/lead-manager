import { Button } from '@/components/ui/button';
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import TagInput from '@/components/ui/tag-input.jsx';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useCurrentModalId, useModal } from '@/hooks/use-modal';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Modal component for creating and editing field entries
 */
export default function FormModal({ entry, isEdit = false }) {
  const modal = useModal();
  const modalId = useCurrentModalId();
  const [rawMode, setRawMode] = useState(false);

  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: entry?.name ?? '',
    label: entry?.label ?? '',
    possible_values: entry?.possible_values ?? [],
    is_array: entry?.is_array ?? false,
  });

  const handleRawChange = (e) => {
    const parsed = e.target.value
      .split(';')
      .map((v) => v.trim())
      .filter((v) => v.length > 0);
    setData('possible_values', parsed);
  };

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
        {/* Friendly Name */}
        <div className="space-y-2">
          <Label htmlFor="label">Friendly Name</Label>
          <Input
            id="label"
            type="text"
            value={data.label}
            onChange={(e) => setData('label', e.target.value)}
            placeholder="Friendly name for this field"
          />
          {errors.label && <p className="text-sm text-destructive">{errors.label}</p>}
        </div>

        {/* Parameter Name */}
        <div className="space-y-2">
          <Label htmlFor="name">Parameter Name</Label>
          <Input id="name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Parameter name" />
          {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
        </div>

        {/* Field Type */}
        <div className="space-y-2">
          <Label>Field Type</Label>
          <ToggleGroup
            type="single"
            variant="outline"
            value={data.is_array ? 'array' : 'normal'}
            onValueChange={(v) => v && setData('is_array', v === 'array')}
            className="w-full"
          >
            <ToggleGroupItem value="normal" aria-label="Normal field" className="flex-1">
              Normal
            </ToggleGroupItem>
            <ToggleGroupItem value="array" aria-label="Array field" className="flex-1">
              Array
            </ToggleGroupItem>
          </ToggleGroup>
          {data.is_array && (
            <p className="text-xs text-muted-foreground">
              Array fields must be sent from the SDK as a single string with values separated by <code className="font-mono">;</code> (e.g.{' '}
              <code className="font-mono">value1;value2;value3</code>).
            </p>
          )}
        </div>

        {/* Possible Values */}
        <div className="space-y-2">
          <div className="flex items-center justify-between gap-2">
            <Label htmlFor="possible_values">Possible Values</Label>
            <div className="flex items-center gap-2">
              <Label htmlFor="raw_mode" className="text-xs text-muted-foreground">
                Raw mode
              </Label>
              <Switch id="raw_mode" checked={rawMode} onCheckedChange={setRawMode} />
            </div>
          </div>
          {rawMode ? (
            <div className="mt-4">
              <Input
                id="possible_values"
                type="text"
                value={data.possible_values.join(';')}
                onChange={handleRawChange}
                placeholder="value1;value2;value3"
                className="border-zinc-800 bg-zinc-950 font-mono text-zinc-100 placeholder:text-zinc-500 focus-visible:ring-zinc-700"
              />
            </div>
          ) : (
            <TagInput id="possible_values" value={data.possible_values} onChange={(values) => setData('possible_values', values)} />
          )}
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

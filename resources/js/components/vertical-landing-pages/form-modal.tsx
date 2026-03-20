import { Button } from '@/components/ui/button';
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useCurrentModalId, useModal } from '@/hooks/use-modal';
import { useForm } from '@inertiajs/react';
export default function FormModal({ entry, isEdit = false, verticals = [], companies = [] }) {

  const modal = useModal();
  const modalId = useCurrentModalId();

  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: entry?.name ?? '',
    url: entry?.url ?? '',
    is_external: entry?.is_external ?? false,
    vertical_id: entry?.vertical_id ? String(entry.vertical_id) : '',
    company_id: entry?.company_id ? String(entry.company_id) : '',
    active: entry?.active ?? true,
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    const url = isEdit
      ? route('vertical_landing_pages.update', entry.id)
      : route('vertical_landing_pages.store');
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
        <DialogTitle>{isEdit ? 'Edit Landing Page' : 'Create Landing Page'}</DialogTitle>
        <DialogDescription>
          {isEdit ? 'Edit the landing page details' : 'Add a new landing page'}
        </DialogDescription>
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
            placeholder="e.g. Auto Insurance Landing"
          />
          {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
        </div>

        {/* URL */}
        <div className="space-y-2">
          <Label htmlFor="url">URL</Label>
          <Input
            id="url"
            type="text"
            value={data.url}
            onChange={(e) => setData('url', e.target.value)}
            placeholder="https://example.com/landing"
          />
          {errors.url && <p className="text-sm text-destructive">{errors.url}</p>}
        </div>

        {/* Vertical */}
        <div className="space-y-2">
          <Label htmlFor="vertical_id">Vertical</Label>
          <Select value={data.vertical_id} onValueChange={(val) => setData('vertical_id', val)}>
            <SelectTrigger>
              <SelectValue placeholder="Select a vertical" />
            </SelectTrigger>
            <SelectContent>
              {verticals.map((v) => (
                <SelectItem key={v.id} value={String(v.id)}>
                  {v.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.vertical_id && <p className="text-sm text-destructive">{errors.vertical_id}</p>}
        </div>

        {/* Is External Switch */}
        <div className="flex items-center space-x-2">
          <Switch
            id="is_external"
            checked={data.is_external}
            onCheckedChange={(checked) => {
              setData('is_external', checked);
              if (!checked) setData('company_id', '');
            }}
          />
          <Label htmlFor="is_external">External Landing Page</Label>
        </div>

        {/* Company — only shown when is_external is true */}
        {data.is_external && (
          <div className="space-y-2">
            <Label htmlFor="company_id">Company <span className="text-destructive">*</span></Label>
            <Select value={data.company_id} onValueChange={(val) => setData('company_id', val)}>
              <SelectTrigger>
                <SelectValue placeholder="Select a company" />
              </SelectTrigger>
              <SelectContent>
                {companies.map((c) => (
                  <SelectItem key={c.id} value={String(c.id)}>
                    {c.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.company_id && <p className="text-sm text-destructive">{errors.company_id}</p>}
          </div>
        )}

        {/* Active */}
        <div className="flex items-center space-x-2">
          <Switch
            id="active"
            checked={data.active}
            onCheckedChange={(checked) => setData('active', checked)}
          />
          <Label htmlFor="active">Active</Label>
        </div>

        {/* Actions */}
        <div className="flex justify-end gap-2 pt-4">
          <Button type="button" variant="outline" onClick={handleCancel} disabled={processing}>
            Cancel
          </Button>
          <Button
            type="submit"
            disabled={processing || !data.name.trim() || !data.url.trim() || !data.vertical_id || (data.is_external && !data.company_id)}
          >
            {processing ? 'Saving...' : isEdit ? 'Update' : 'Create'}
          </Button>
        </div>
      </form>
    </>
  );
}
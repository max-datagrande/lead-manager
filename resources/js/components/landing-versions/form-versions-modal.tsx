import { Button } from '@/components/ui/button';
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useCurrentModalId, useModal } from '@/hooks/use-modal';
import { useForm } from '@inertiajs/react';

export function FormModalVersion({ entry, landingPageId, isEdit = false }) {
  const modal = useModal();
  const modalId = useCurrentModalId();
  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: entry?.name ?? '',
    description: entry?.description ?? '',
    url: entry?.url ?? '',
    status: entry?.status ?? true,
  });

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();

    data.url = normalizeSlug(data.url);

    if (data.url === '__INVALID__' || !data.url.trim()) {
      alert('Invalid slug. Only simple paths like "v1" are allowed.');
      return;
    }

    const urlRoute = isEdit
      ? route('landing_pages.versions.update', {
          landing_page: landingPageId,
          version: entry.id,
        })
      : route('landing_pages.versions.store', {
          landing_page: landingPageId,
        });

    const options = {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        modal.resolve(modalId, true);
        reset();
      },
    };

    isEdit && entry?.id ? put(urlRoute, options) : post(urlRoute, options);
  };

  const handleCancel = () => {
    modal.resolve(modalId, false);
    reset();
  };

  const normalizeSlug = (value: string) => {
    if (!value) return '';

    let slug = value.trim();

    if (/https?:\/\//i.test(slug) || /\./.test(slug)) {
      return '__INVALID__';
    }

    slug = slug.replace(/^\/+|\/+$/g, '');

    return `/${slug}/`;
  };

  return (
    <>
      <DialogHeader>
        <DialogTitle>{isEdit ? 'Edit Version' : 'Create Version'}</DialogTitle>
        <DialogDescription>{isEdit ? 'Edit the version details' : 'Create a new version for this landing page'}</DialogDescription>
      </DialogHeader>

      <form onSubmit={handleSubmit} className="space-y-4">
        {/* Name */}
        <div className="space-y-2">
          <Label htmlFor="name">Name</Label>
          <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. Version A" />
          {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
        </div>

        {/* Description */}
        <div className="space-y-2">
          <Label htmlFor="description">Description</Label>
          <Textarea
            id="description"
            value={data.description}
            onChange={(e) => setData('description', e.target.value)}
            placeholder="Optional description..."
          />
          {errors.description && <p className="text-sm text-destructive">{errors.description}</p>}
        </div>

        {/* URL */}
        <div className="space-y-2">
          <Label htmlFor="url">URL</Label>
          <Input
            id="url"
            value={data.url}
            onChange={(e) => {
              const raw = e.target.value;

              // block invalid patterns early
              if (/https?:\/\//i.test(raw) || raw.includes('.')) {
                return;
              }

              setData('url', raw);
            }}
            placeholder="e.g. v1"
          />
          {errors.url && <p className="text-sm text-destructive">{errors.url}</p>}
        </div>

        {/* Status */}
        <div className="flex items-center space-x-2">
          <Switch id="status" checked={data.status} onCheckedChange={(checked) => setData('status', checked)} />
          <Label htmlFor="status">Active</Label>
        </div>

        {/* Actions */}
        <div className="flex justify-end gap-2 pt-4">
          <Button type="button" variant="outline" onClick={handleCancel} disabled={processing}>
            Cancel
          </Button>

          <Button type="submit" disabled={processing || !data.name.trim() || !data.url.trim()}>
            {processing ? 'Saving...' : isEdit ? 'Update' : 'Create'}
          </Button>
        </div>
      </form>
    </>
  );
}

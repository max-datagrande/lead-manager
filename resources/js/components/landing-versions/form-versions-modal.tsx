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
    path: entry?.path ?? '',
    status: entry?.status ?? true,
  });

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();

    data.path = normalizeSlug(data.path);

    if (data.path === '__INVALID__' || !data.path.trim()) {
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
    const slug = (value ?? '').trim().replace(/^\/+|\/+$/g, '');

    // Slug vacio (o solo "/") = home de la landing.
    if (slug === '') {
      return '/';
    }

    if (/https?:\/\//i.test(slug) || /\./.test(slug)) {
      return '__INVALID__';
    }

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

        {/* Path */}
        <div className="space-y-2">
          <Label htmlFor="path">Path</Label>
          <Input
            id="path"
            value={data.path}
            onChange={(e) => {
              const raw = e.target.value;

              // block invalid patterns early
              if (/https?:\/\//i.test(raw) || raw.includes('.')) {
                return;
              }

              setData('path', raw);
            }}
            placeholder="e.g. v1 — use / for the home"
          />
          <p className="text-xs text-muted-foreground">
            Simple slug (e.g. <code>v1</code> → <code>/v1/</code>). For the landing's home page, enter <code>/</code>.
          </p>
          {errors.path && <p className="text-sm text-destructive">{errors.path}</p>}
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

          <Button type="submit" disabled={processing || !data.name.trim() || !data.path.trim()}>
            {processing ? 'Saving...' : isEdit ? 'Update' : 'Create'}
          </Button>
        </div>
      </form>
    </>
  );
}

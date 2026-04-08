import { Button } from '@/components/ui/button';
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useCurrentModalId, useModal } from '@/hooks/use-modal';
import { type AlertChannel, type ChannelType } from '@/pages/alert-channels/index';
import { useForm } from '@inertiajs/react';

interface FormModalProps {
  channelTypes: ChannelType[];
  entry?: AlertChannel;
  isEdit?: boolean;
}

export default function FormModal({ channelTypes, entry, isEdit = false }: FormModalProps) {
  const modal = useModal();
  const modalId = useCurrentModalId();

  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: entry?.name ?? '',
    type: entry?.type ?? channelTypes[0]?.value ?? '',
    webhook_url: entry?.webhook_url ?? '',
    active: entry?.active ?? true,
  });

  const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const url = isEdit ? route('alert-channels.update', entry!.id) : route('alert-channels.store');
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
        <DialogTitle>{isEdit ? 'Edit Alert Channel' : 'Create Alert Channel'}</DialogTitle>
        <DialogDescription>{isEdit ? 'Edit the alert channel details' : 'Add a new alert channel'}</DialogDescription>
        <div className="flex items-center space-x-2">
          <Switch id="is_active" checked={data.active} onCheckedChange={(checked: boolean) => setData('active', checked)} />
          <Label htmlFor="is_active">{data.active ? 'Active' : 'Inactive'}</Label>
        </div>
      </DialogHeader>

      <form onSubmit={handleSubmit} className="space-y-4">
        {/* Name */}
        <div className="space-y-2">
          <Label htmlFor="name">Name</Label>
          <Input id="name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. Slack Errors" />
          {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
        </div>

        {/* Type */}
        <div className="space-y-2">
          <Label htmlFor="type">Type</Label>
          <Select value={data.type} onValueChange={(value) => setData('type', value)}>
            <SelectTrigger>
              <SelectValue placeholder="Select a type" />
            </SelectTrigger>
            <SelectContent>
              {channelTypes.map((ct) => (
                <SelectItem key={ct.value} value={ct.value}>
                  {ct.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.type && <p className="text-sm text-destructive">{errors.type}</p>}
        </div>

        {/* Webhook URL */}
        <div className="space-y-2">
          <Label htmlFor="webhook_url">Webhook URL</Label>
          <Input
            id="webhook_url"
            type="url"
            value={data.webhook_url}
            onChange={(e) => setData('webhook_url', e.target.value)}
            placeholder="https://hooks.slack.com/services/..."
          />
          {errors.webhook_url && <p className="text-sm text-destructive">{errors.webhook_url}</p>}
        </div>

        {/* Actions */}
        <div className="flex justify-end gap-2 pt-4">
          <Button type="button" variant="outline" onClick={handleCancel} disabled={processing}>
            Cancel
          </Button>
          <Button type="submit" disabled={processing || !data.name.trim() || !data.webhook_url.trim()}>
            {processing ? 'Saving...' : isEdit ? 'Update' : 'Create'}
          </Button>
        </div>
      </form>
    </>
  );
}

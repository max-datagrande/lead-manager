import { DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useModal, useCurrentModalId } from '@/hooks/use-modal';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { AlertCircle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';

/**
 * Modal component for creating and editing whitelist entries
 */
export default function WhitelistFormModal({ id, entry, isEdit = false }) {
  const modal = useModal();
  const modalId = useCurrentModalId();

  const { data, setData, post, put, processing, errors, reset } = useForm({
    type: entry?.type || 'domain',
    name: entry?.name || '',
    value: entry?.value || '',
    is_active: entry?.is_active ?? true,
  });

  useEffect(() => {
    if (entry) {
      setData({
        type: entry.type,
        name: entry.name,
        value: entry.value,
        is_active: entry.is_active,
      });
    }
  }, [entry]);

  /**
   * Validates URL format
   */
  const isValidUrl = (string) => {
    try {
      new URL(string.startsWith('http') ? string : `https://${string}`);
      return true;
    } catch (_) {
      return false;
    }
  };

  /**
   * Validates IP address format
   */
  const isValidIP = (ip) => {
    const ipv4Regex = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
    const ipv6Regex = /^(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}$|^::1$|^::$/;
    return ipv4Regex.test(ip) || ipv6Regex.test(ip);
  };

  /**
   * Validates the form data
   */
  const validateForm = () => {
    if (!data.value.trim()) {
      return 'Value is required';
    }

    if (data.type === 'domain' && !isValidUrl(data.value)) {
      return 'Please enter a valid domain or URL';
    }

    if (data.type === 'ip' && !isValidIP(data.value)) {
      return 'Please enter a valid IP address';
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
      // You could set a local error state here if needed
      return;
    }

    const submitData = {
      ...data,
      is_active: data.is_active ? 1 : 0,
    };

    if (isEdit && entry?.id) {
      put(route('admin.whitelist.update', entry.id), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
          modal.resolve(modalId, true);
          reset();
        },
        onError: (e) => {
          console.log(e);
        },
      });
    } else {
      post(route('admin.whitelist.store'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
          modal.resolve(modalId, true);
          reset();
        },
        onError: () => {
          console.log(e);
        },
      });
    }
  };

  /**
   * Handles modal cancellation
   */
  const handleCancel = () => {
    modal.resolve(modalId, false);
    reset();
  };

  /**
   * Gets placeholder text based on type
   */
  const getValuePlaceholder = () => {
    return data.type === 'domain'
      ? 'example.com or https://example.com'
      : '192.168.1.1 or 2001:db8::1';
  };

  const validationError = validateForm();

  return (
    <>
      <DialogHeader>
        <DialogTitle>{isEdit ? 'Edit Whitelist Entry' : 'Create Whitelist Entry'}</DialogTitle>
        <DialogDescription>{isEdit ? 'Edit the whitelist entry details' : 'Add a new whitelist entry'}</DialogDescription>
      </DialogHeader>

      <form onSubmit={handleSubmit} className="space-y-4">
        {/* Type Selection */}
        <div className="space-y-2">
          <Label htmlFor="type">Type</Label>
          <Select value={data.type} onValueChange={(value) => setData('type', value)}>
            <SelectTrigger>
              <SelectValue placeholder="Select type" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="domain">Domain</SelectItem>
              <SelectItem value="ip">IP Address</SelectItem>
            </SelectContent>
          </Select>
          {errors.type && <p className="text-sm text-destructive">{errors.type}</p>}
        </div>

        {/* Name Field */}
        <div className="space-y-2">
          <Label htmlFor="name">Name (Optional)</Label>
          <Input
            id="name"
            type="text"
            value={data.name}
            onChange={(e) => setData('name', e.target.value)}
            placeholder="Friendly name for this entry"
          />
          {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
        </div>

        {/* Value Field */}
        <div className="space-y-2">
          <Label htmlFor="value">{data.type === 'domain' ? 'Domain/URL' : 'IP Address'}</Label>
          <Input
            id="value"
            type="text"
            value={data.value}
            onChange={(e) => setData('value', e.target.value)}
            placeholder={getValuePlaceholder()}
            className={validationError && data.value ? 'border-destructive' : ''}
          />
          {errors.value && <p className="text-sm text-destructive">{errors.value}</p>}
          {validationError && data.value && (
            <Alert variant="destructive">
              <AlertCircle className="h-4 w-4" />
              <AlertDescription>{validationError}</AlertDescription>
            </Alert>
          )}
        </div>

        {/* Active Status */}
        <div className="flex items-center space-x-2">
          <Switch id="is_active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked)} />
          <Label htmlFor="is_active">Active</Label>
        </div>
        {errors.is_active && <p className="text-sm text-destructive">{errors.is_active}</p>}

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

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useCurrentModalId, useModal } from '@/hooks/use-modal';
import { useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

/**
 * Modal component for confirming whitelist entry deletion
 */
export default function WhitelistDeleteModal({ id, entry }) {
  const modal = useModal();
  const modalId = useCurrentModalId();
  const { delete: destroy, processing } = useForm();

  /**
   * Handles deletion confirmation
   */
  const handleConfirm = () => {
    console.log('Deleting entry:', entry);
    const url = route('admin.whitelist.destroy', entry.id);
    console.log('URL:', url);
    destroy(url, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        modal.resolve(modalId, true);
      },
      onError: (errors) => {
        console.log('Delete errors:', errors);
        modal.reject(modalId, errors);
      },
    });
  };

  /**
   * Handles modal cancellation
   */
  const handleCancel = () => {
    modal.resolve(modalId, false);
  };

  /**
   * Gets the display name for the entry
   */
  const getDisplayName = () => {
    if (entry.name) {
      return `${entry.name} (${entry.value})`;
    }
    return entry.value;
  };

  /**
   * Gets the type label
   */
  const getTypeLabel = () => {
    return entry.type === 'domain' ? 'domain' : 'IP address';
  };

  return (
    <>
      <DialogHeader>
        <DialogTitle className="flex items-center gap-2">
          <AlertTriangle className="h-5 w-5 text-destructive" />
          Delete Whitelist Entry
        </DialogTitle>
        <DialogDescription>This action cannot be undone. The {getTypeLabel()} will be permanently removed from the whitelist.</DialogDescription>
      </DialogHeader>

      <div className="mt-4 space-y-4">
        {/* Entry Details */}
        <Alert>
          <AlertDescription>
            <div className="space-y-1">
              <div>
                <strong>Type:</strong> {entry.type === 'domain' ? 'Domain' : 'IP Address'}
              </div>
              {entry.name && (
                <div>
                  <strong>Name:</strong> {entry.name}
                </div>
              )}
              <div>
                <strong>Value:</strong> <code className="rounded bg-muted px-1 py-0.5 text-sm">{entry.value}</code>
              </div>
              <div>
                <strong>Status:</strong> {entry.is_active ? 'Active' : 'Inactive'}
              </div>
            </div>
          </AlertDescription>
        </Alert>

        <Alert variant="destructive">
          <AlertTriangle className="h-4 w-4" />
          <AlertDescription>
            <p>Are you sure you want to delete <strong>{getDisplayName()}</strong>?</p>
          </AlertDescription>
        </Alert>
      </div>

      {/* Form Actions */}
      <div className="mt-6 flex justify-end gap-2">
        <Button variant="outline" onClick={handleCancel} disabled={processing}>
          Cancel
        </Button>
        <Button variant="destructive" onClick={handleConfirm} disabled={processing}>
          {processing ? 'Deleting...' : 'Delete Entry'}
        </Button>
      </div>
    </>
  );
}

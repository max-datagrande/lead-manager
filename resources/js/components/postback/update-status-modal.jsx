import { Button } from '@/components/ui/button';
import { DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogClose } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useCurrentModalId, useModal } from '@/hooks/use-modal';
import { useForm } from '@inertiajs/react';

export function UpdateStatusModal({ postback }) {
  //Modal providers
  const { resolve, reject } = useModal();
  const modalId = useCurrentModalId();

  //Data
  const { data, setData, patch, processing, errors, reset } = useForm({
    status: postback.status ?? '',
    message: postback.message ?? '',
  });

  //Enums
  const postbackStatus = [
    { value: 'pending', label: 'Pending' },
    { value: 'processed', label: 'Processed' },
    { value: 'failed', label: 'Failed' },
  ];

  //Handlers
  const handleSubmit = (e) => {
    e.preventDefault();
    const url = route('postbacks.updateStatus', postback.id);
    patch(url, {
      preserveScroll: true,
      onSuccess: () => {
        resolve(modalId, true);
        reset();
      },
      onError: () => {
        reject(modalId, false);
      },
    });
  };

  const handleCancel = () => {
    resolve(modalId, false);
    reset();
  };

  return (
    <>
      <DialogHeader>
        <DialogTitle>Update Postback Status</DialogTitle>
        <DialogDescription>Select a new status and provide a message if necessary.</DialogDescription>
      </DialogHeader>
      <form onSubmit={handleSubmit} className="grid gap-4 py-4" id='update-status-modal'>
        <div className="grid grid-cols-4 items-center gap-4">
          <Label htmlFor="status" className="text-right">
            Status
          </Label>
          <div className="col-span-3">
            <Select id="status" value={data.status} onValueChange={(value) => setData('status', value)}>
              <SelectTrigger>
                <SelectValue placeholder="Select a status" />
              </SelectTrigger>
              <SelectContent>
                {postbackStatus.map((status) => (
                  <SelectItem key={status.value} value={status.value}>
                    {status.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.status && <p className="mt-1 text-xs text-red-500">{errors.status}</p>}
          </div>
        </div>
        <div className="grid grid-cols-4 items-center gap-4">
          <Label htmlFor="message" className="text-right">
            Message
          </Label>
          <div className="col-span-3">
            <Textarea
              id="message"
              value={data.message}
              onChange={(e) => setData('message', e.target.value)}
              className="col-span-3"
              placeholder="Enter a message (optional)"
            />
            {errors.message && <p className="mt-1 text-xs text-red-500">{errors.message}</p>}
          </div>
        </div>
      </form>
      <DialogFooter>
        <DialogClose asChild>
          <Button type="button" variant="outline" onClick={handleCancel} disabled={processing}>
            Cancel
          </Button>
        </DialogClose>
        <Button type="submit" disabled={processing} form='update-status-modal'>
          {processing ? 'Updating...' : 'Update Status'}
        </Button>
      </DialogFooter>
    </>
  );
}

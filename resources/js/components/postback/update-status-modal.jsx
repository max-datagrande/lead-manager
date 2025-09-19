import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { usePostbacks } from '@/hooks/use-postbacks';

export function UpdateStatusModal() {
    const {
        isUpdating,
        statusData,
        setStatusData,
        handleUpdateStatus,
        statusErrors,
        modal,
    } = usePostbacks();

    const postbackStatus = [
        { value: 'pending', label: 'Pending' },
        { value: 'processed', label: 'Processed' },
        { value: 'failed', label: 'Failed' },
    ];

    return (
        <Dialog open={modal.isOpen} onOpenChange={modal.close}>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Update Postback Status</DialogTitle>
                    <DialogDescription>
                        Select a new status and provide a message if necessary.
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-4 py-4">
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="status" className="text-right">
                            Status
                        </Label>
                        <div className="col-span-3">
                            <Select
                                id="status"
                                value={statusData.status}
                                onValueChange={(value) => setStatusData('status', value)}
                            >
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
                            {statusErrors.status && <p className="text-red-500 text-xs mt-1">{statusErrors.status}</p>}
                        </div>
                    </div>
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="message" className="text-right">
                            Message
                        </Label>
                        <div className="col-span-3">
                            <Textarea
                                id="message"
                                value={statusData.message}
                                onChange={(e) => setStatusData('message', e.target.value)}
                                className="col-span-3"
                                placeholder="Enter a message (optional)"
                            />
                            {statusErrors.message && <p className="text-red-500 text-xs mt-1">{statusErrors.message}</p>}
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={modal.close} disabled={isUpdating}>
                        Cancel
                    </Button>
                    <Button type="submit" onClick={handleUpdateStatus} disabled={isUpdating}>
                        {isUpdating ? 'Updating...' : 'Update Status'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

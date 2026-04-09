import { Button } from '@/components/ui/button';
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useCurrentModalId, useModal } from '@/hooks/use-modal';
import { TriangleAlert } from 'lucide-react';
import { useState } from 'react';

type Props = {
  id: number;
  title?: string;
  description?: string;
  consequences?: string[];
  confirmText?: string;
  cancelText?: string;
  confirmCode?: string;
};

export default function WarnConfirmDialog({
  id,
  title = 'Are you sure?',
  description,
  consequences = [],
  confirmText = 'Confirm',
  cancelText = 'Cancel',
  confirmCode,
}: Props) {
  const modal = useModal();
  const modalId = useCurrentModalId();
  const [code, setCode] = useState('');

  const codeMatches = !confirmCode || code === confirmCode;

  const handleConfirm = () => {
    if (!codeMatches) return;
    modal.resolve(modalId, true);
  };

  const handleCancel = () => {
    modal.resolve(modalId, false);
  };

  return (
    <div className="space-y-5">
      <div className="flex flex-col items-center gap-4 pt-2">
        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-950/50">
          <TriangleAlert className="h-8 w-8 text-amber-600 dark:text-amber-400" />
        </div>
        <DialogHeader className="text-center">
          <DialogTitle className="text-xl">{title}</DialogTitle>
          {description && <DialogDescription className="text-sm">{description}</DialogDescription>}
        </DialogHeader>
      </div>

      {consequences.length > 0 && (
        <div className="rounded-lg border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-900/50 dark:bg-amber-950/20">
          <p className="mb-2 text-xs font-semibold tracking-wide text-amber-700 uppercase dark:text-amber-400">What will happen</p>
          <ul className="space-y-1.5">
            {consequences.map((item, i) => (
              <li key={i} className="flex items-start gap-2 text-sm text-amber-900 dark:text-amber-200">
                <span className="mt-0.5 block h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500" />
                {item}
              </li>
            ))}
          </ul>
        </div>
      )}

      {confirmCode && (
        <div className="space-y-2">
          <p className="text-sm text-muted-foreground">
            Type <span className="font-mono font-bold text-foreground">{confirmCode}</span> to confirm:
          </p>
          <Input value={code} onChange={(e) => setCode(e.target.value)} placeholder={confirmCode} className="font-mono" autoFocus />
        </div>
      )}

      <div className="flex justify-end gap-3 border-t pt-4">
        <Button variant="outline" size="lg" onClick={handleCancel}>
          {cancelText}
        </Button>
        <Button variant="destructive" size="lg" onClick={handleConfirm} disabled={!codeMatches}>
          {confirmText}
        </Button>
      </div>
    </div>
  );
}

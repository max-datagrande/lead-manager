import { Button } from '@/components/ui/button';
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useCurrentModalId, useModal } from '@/hooks/use-modal';
import { useState } from 'react';

/**
 * Props for the PromptDialog component
 */
type Props = {
  id: number;
  title?: string;
  description?: string;
  defaultValue?: string;
  placeholder?: string;
  confirmText?: string;
  cancelText?: string;
};

/**
 * Prompt dialog component that uses shadcn/ui
 * Integrates with the modal system to display input prompts
 */
export default function PromptDialog({
  id,
  title = 'Please enter a value',
  description,
  defaultValue = '',
  placeholder = '',
  confirmText = 'Confirm',
  cancelText = 'Cancel',
}: Props) {
  const modal = useModal();
  const modalId = useCurrentModalId();
  const [value, setValue] = useState(defaultValue);

  /**
   * Handles dialog confirmation
   */
  const handleConfirm = () => {
    modal.resolve(modalId, value);
  };

  /**
   * Handles dialog cancellation
   */
  const handleCancel = () => {
    modal.resolve(modalId, null);
  };

  return (
    <>
      <DialogHeader>
        <DialogTitle>{title}</DialogTitle>
        {description && <DialogDescription>{description}</DialogDescription>}
      </DialogHeader>

      <div className="py-4">
        <Input
          value={value}
          onChange={(e) => setValue(e.target.value)}
          placeholder={placeholder}
          onKeyDown={(e) => {
            if (e.key === 'Enter') handleConfirm();
          }}
          autoFocus
        />
      </div>

      <div className="flex justify-end gap-2">
        <Button variant="outline" onClick={handleCancel}>
          {cancelText}
        </Button>

        <Button onClick={handleConfirm}>{confirmText}</Button>
      </div>
    </>
  );
}
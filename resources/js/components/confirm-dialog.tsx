import { DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { useModal, useCurrentModalId } from '@/hooks/use-modal'

/**
 * Props for the ConfirmDialog component
 */
type Props = {
  id: number
  title?: string
  description?: string
  confirmText?: string
  cancelText?: string
  destructive?: boolean
}

/**
 * Confirmation dialog component that uses shadcn/ui
 * Integrates with the modal system to display standard confirmations
 */
export default function ConfirmDialog({
  id,
  title = 'Are you sure?',
  description,
  confirmText = 'Confirm',
  cancelText = 'Cancel',
  destructive = false
}: Props) {
  const modal = useModal()
  const modalId = useCurrentModalId() // equals to `id`

  /**
   * Handles dialog confirmation
   */
  const handleConfirm = () => {
    modal.resolve(modalId, true)
  }

  /**
   * Handles dialog cancellation
   */
  const handleCancel = () => {
    modal.resolve(modalId, false)
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle>{title}</DialogTitle>
        {description && (
          <DialogDescription>{description}</DialogDescription>
        )}
      </DialogHeader>

      <div className="mt-6 flex justify-end gap-2">
        <Button
          variant="outline"
          onClick={handleCancel}
        >
          {cancelText}
        </Button>

        <Button
          variant={destructive ? 'destructive' : 'default'}
          onClick={handleConfirm}
        >
          {confirmText}
        </Button>
      </div>
    </>
  )
}

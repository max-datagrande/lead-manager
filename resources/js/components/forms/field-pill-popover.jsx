import { Button } from '@/components/ui/button'
import { Trash2 } from 'lucide-react'
import { useEffect, useRef } from 'react'
import { createPortal } from 'react-dom'

/**
 * Small popover that appears when clicking a field pill inside the JSON editor.
 *
 * @param {{
 *   field: { name: string, label?: string } | undefined,
 *   fieldId: number,
 *   position: { top: number, left: number },
 *   onDelete: () => void,
 *   onClose: () => void,
 * }} props
 */
export function FieldPillPopover({ field, fieldId, position, onDelete, onClose }) {
  const containerRef = useRef(null)
  const displayName = field?.label ?? field?.name ?? `Field #${fieldId}`

  useEffect(() => {
    const handler = (e) => {
      if (containerRef.current && !containerRef.current.contains(e.target)) onClose()
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [onClose])

  if (typeof document === 'undefined') return null

  return createPortal(
    <div
      ref={containerRef}
      style={{ position: 'fixed', top: position.top, left: position.left, zIndex: 9999 }}
      className="w-52 rounded-lg border bg-popover p-3 shadow-md"
    >
      <p className="mb-0.5 text-xs text-muted-foreground">Field token</p>
      <p className="mb-3 text-sm font-semibold">{displayName}</p>
      <Button type="button" size="sm" variant="destructive" className="h-7 w-full gap-1.5 text-xs" onClick={onDelete}>
        <Trash2 className="size-3" />
        Remove token
      </Button>
    </div>,
    document.body,
  )
}

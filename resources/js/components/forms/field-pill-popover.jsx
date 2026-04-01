import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Trash2 } from 'lucide-react'
import { useEffect, useRef } from 'react'
import { createPortal } from 'react-dom'

const HASH_ALGORITHMS = [
  { value: 'md5', label: 'MD5' },
  { value: 'sha1', label: 'SHA-1' },
  { value: 'sha256', label: 'SHA-256' },
  { value: 'sha512', label: 'SHA-512' },
  { value: 'base64', label: 'Base64' },
  { value: 'hmac_sha256', label: 'HMAC SHA-256' },
]

/**
 * Small popover that appears when clicking a field pill inside the JSON editor.
 *
 * @param {{
 *   field: { name: string, label?: string } | undefined,
 *   fieldId: number,
 *   position: { top: number, left: number },
 *   hashConfig: { is_hashed: boolean, hash_algorithm: string|null, hmac_secret: string|null } | null,
 *   onHashChange: ((patch: object) => void) | null,
 *   onDelete: () => void,
 *   onClose: () => void,
 * }} props
 */
export function FieldPillPopover({ field, fieldId, position, hashConfig, onHashChange, onDelete, onClose }) {
  const containerRef = useRef(null)
  const displayName = field?.label ?? field?.name ?? `Field #${fieldId}`

  const isHashed = hashConfig?.is_hashed ?? false
  const algorithm = hashConfig?.hash_algorithm ?? null
  const hmacSecret = hashConfig?.hmac_secret ?? ''

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
      className="w-64 rounded-lg border bg-popover p-3 shadow-md space-y-3"
    >
      <div>
        <p className="text-xs text-muted-foreground">Field token</p>
        <p className="text-sm font-semibold">{displayName}</p>
      </div>

      {/* Hash config — only shown when onHashChange is wired */}
      {onHashChange && (
        <div className="space-y-2.5 border-t pt-2.5">
          <div className="flex items-center justify-between">
            <Label htmlFor={`hash-toggle-${fieldId}`} className="text-xs">Hash value</Label>
            <Switch
              id={`hash-toggle-${fieldId}`}
              checked={isHashed}
              onCheckedChange={(checked) =>
                onHashChange({ is_hashed: checked, hash_algorithm: checked ? (algorithm ?? 'md5') : null, hmac_secret: null })
              }
            />
          </div>

          {isHashed && (
            <>
              <div className="space-y-1">
                <Label className="text-xs">Algorithm</Label>
                <Select
                  value={algorithm ?? 'md5'}
                  onValueChange={(val) => onHashChange({ hash_algorithm: val, hmac_secret: val === 'hmac_sha256' ? hmacSecret : null })}
                >
                  <SelectTrigger className="h-7 text-xs">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {HASH_ALGORITHMS.map((a) => (
                      <SelectItem key={a.value} value={a.value} className="text-xs">
                        {a.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              {algorithm === 'hmac_sha256' && (
                <div className="space-y-1">
                  <Label className="text-xs">HMAC Secret</Label>
                  <Input
                    className="h-7 text-xs"
                    placeholder="Secret key"
                    value={hmacSecret}
                    onChange={(e) => onHashChange({ hmac_secret: e.target.value || null })}
                  />
                </div>
              )}
            </>
          )}
        </div>
      )}

      <div className="border-t pt-2.5">
        <Button type="button" size="sm" variant="destructive" className="h-7 w-full gap-1.5 text-xs" onClick={onDelete}>
          <Trash2 className="size-3" />
          Remove token
        </Button>
      </div>
    </div>,
    document.body,
  )
}

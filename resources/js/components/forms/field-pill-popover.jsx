import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { cn } from '@/lib/utils'
import { Check, ChevronDown, Trash2, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'

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
  const [algoOpen, setAlgoOpen] = useState(false)
  const [algoSearch, setAlgoSearch] = useState('')
  const algoInputRef = useRef(null)
  const containerRef = useRef(null)

  useEffect(() => {
    document.addEventListener('mousedown', onClose)
    return () => document.removeEventListener('mousedown', onClose)
  }, [onClose])

  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [onClose])

  const label = field?.label ?? field?.name ?? `Field #${fieldId}`
  const technicalName = field?.name

  const isHashed = hashConfig?.is_hashed ?? false
  const algorithm = hashConfig?.hash_algorithm ?? null
  const hmacSecret = hashConfig?.hmac_secret ?? ''

  const filteredAlgos = HASH_ALGORITHMS.filter((a) =>
    a.label.toLowerCase().includes(algoSearch.toLowerCase()),
  )
  const currentAlgoLabel = HASH_ALGORITHMS.find((a) => a.value === (algorithm ?? 'md5'))?.label ?? 'MD5'

  useEffect(() => {
    if (algoOpen) {
      setAlgoSearch('')
      algoInputRef.current?.focus()
    }
  }, [algoOpen])

  return (
    <div
      ref={containerRef}
      onMouseDown={(e) => e.stopPropagation()}
      style={{ position: 'absolute', top: position.top, left: position.left, zIndex: 9999 }}
      className="w-fit min-w-2xs rounded-lg border bg-popover shadow-lg"
    >
      {/* Header */}
      <div className="relative px-3 py-2 pr-8">
        <p className="text-xs uppercase tracking-wide text-muted-foreground">Field token</p>
        <p className="mt-0.5 text-sm font-semibold leading-tight">{label}</p>
        {technicalName && label !== technicalName && (
          <code className="mt-1 block w-fit rounded bg-foreground/60 p-1">
            <p className="font-mono text-xs text-muted">{technicalName}</p>
          </code>
        )}
        <button
          type="button"
          onClick={onClose}
          className="absolute right-2 top-2 rounded p-0.5 text-muted-foreground hover:bg-accent hover:text-foreground"
        >
          <X className="size-3.5" />
        </button>
      </div>

      {/* Hash config — only shown when onHashChange is wired */}
      {onHashChange && (
        <div className="border-t px-3 py-2">
          <div className="flex items-center justify-between">
            <Label htmlFor={`hash-toggle-${fieldId}`} className="cursor-pointer text-sm font-medium">Hash value</Label>
            <Switch
              id={`hash-toggle-${fieldId}`}
              checked={isHashed}
              onCheckedChange={(checked) =>
                onHashChange({
                  is_hashed: checked,
                  hash_algorithm: checked ? (algorithm ?? 'md5') : null,
                  hmac_secret: null,
                })
              }
            />
          </div>

          {isHashed && (
            <div className="mt-2 space-y-2">
              {/* Inline searchable algorithm picker — no portal, avoids z-index/mousedown issues */}
              <div className="space-y-1">
                <Label className="text-xs text-muted-foreground">Algorithm</Label>
                <div className="relative">
                  <button
                    type="button"
                    onClick={() => setAlgoOpen((v) => !v)}
                    className="flex h-7 w-full items-center justify-between rounded-md border bg-background px-2 text-xs hover:bg-accent"
                  >
                    <span>{currentAlgoLabel}</span>
                    <ChevronDown className={cn('size-3 text-muted-foreground transition-transform', algoOpen && 'rotate-180')} />
                  </button>

                  {algoOpen && (
                    <div className="absolute left-0 top-full z-10 mt-1 w-full overflow-hidden rounded-md border bg-popover shadow-md">
                      <div className="border-b p-1">
                        <Input
                          ref={algoInputRef}
                          value={algoSearch}
                          onChange={(e) => setAlgoSearch(e.target.value)}
                          placeholder="Search…"
                          className="h-6 text-xs"
                        />
                      </div>
                      <div className="max-h-36 overflow-y-auto py-0.5">
                        {filteredAlgos.length === 0 && (
                          <p className="px-2 py-1.5 text-xs text-muted-foreground">No results.</p>
                        )}
                        {filteredAlgos.map((a) => (
                          <button
                            key={a.value}
                            type="button"
                            onMouseDown={(e) => {
                              e.preventDefault()
                              onHashChange({ hash_algorithm: a.value, hmac_secret: a.value === 'hmac_sha256' ? hmacSecret : null })
                              setAlgoOpen(false)
                            }}
                            className={cn(
                              'flex w-full items-center gap-2 px-2 py-1.5 text-xs hover:bg-accent',
                              (algorithm ?? 'md5') === a.value && 'font-medium',
                            )}
                          >
                            <Check className={cn('size-3 shrink-0', (algorithm ?? 'md5') === a.value ? 'opacity-100' : 'opacity-0')} />
                            {a.label}
                          </button>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              </div>

              {algorithm === 'hmac_sha256' && (
                <div className="space-y-1">
                  <Label className="text-xs text-muted-foreground">HMAC Secret</Label>
                  <Input
                    className="h-7 text-xs"
                    placeholder="Secret key"
                    value={hmacSecret}
                    onChange={(e) => onHashChange({ hmac_secret: e.target.value || null })}
                  />
                </div>
              )}
            </div>
          )}
        </div>
      )}

      {/* Danger zone */}
      <div className="px-3 py-2">
        <Button
          type="button"
          size="sm"
          variant="ghost"
          className="h-7 w-full gap-1.5 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
          onClick={onDelete}
        >
          <Trash2 className="size-3" />
          Remove token
        </Button>
      </div>
    </div>
  )
}

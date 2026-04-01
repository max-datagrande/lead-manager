import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'
import { useEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'

/**
 * Floating searchable popover for picking a field to insert as a token.
 *
 * @param {{ fields: Array, position: { top: number, left: number }, onSelect: Function, onClose: Function }} props
 */
export function FieldSearchPopover({ fields = [], position, onSelect, onClose }) {
  const [search, setSearch] = useState('')
  const [activeIndex, setActiveIndex] = useState(0)
  const inputRef = useRef(null)
  const containerRef = useRef(null)

  const filtered = fields.filter(
    (f) =>
      f.name.toLowerCase().includes(search.toLowerCase()) ||
      (f.label ?? '').toLowerCase().includes(search.toLowerCase()),
  )

  useEffect(() => {
    inputRef.current?.focus()
  }, [])

  useEffect(() => {
    setActiveIndex(0)
  }, [search])

  useEffect(() => {
    const handler = (e) => {
      if (containerRef.current && !containerRef.current.contains(e.target)) {
        onClose()
      }
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [onClose])

  const handleKeyDown = (e) => {
    if (e.key === 'Escape') {
      onClose()
      return
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      setActiveIndex((i) => Math.min(i + 1, filtered.length - 1))
      return
    }
    if (e.key === 'ArrowUp') {
      e.preventDefault()
      setActiveIndex((i) => Math.max(i - 1, 0))
      return
    }
    if (e.key === 'Enter') {
      e.preventDefault()
      if (filtered[activeIndex]) onSelect(filtered[activeIndex])
    }
  }

  if (typeof document === 'undefined') return null

  return createPortal(
    <div
      ref={containerRef}
      style={{ position: 'fixed', top: position.top, left: position.left, zIndex: 9999 }}
      className="w-64 rounded-lg border bg-popover shadow-md"
    >
      <div className="p-2">
        <Input
          ref={inputRef}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder="Search fields..."
          className="h-7 text-xs"
        />
      </div>
      <div className="max-h-52 overflow-y-auto pb-1">
        {filtered.length === 0 && <p className="px-3 py-2 text-xs text-muted-foreground">No fields found.</p>}
        {filtered.map((field, i) => (
          <button
            key={field.id}
            type="button"
            onMouseDown={(e) => {
              e.preventDefault()
              onSelect(field)
            }}
            className={cn('flex w-full flex-col px-3 py-1.5 text-left hover:bg-accent', i === activeIndex && 'bg-accent')}
          >
            <span className="text-xs font-medium">{field.label ?? field.name}</span>
            <span className="text-xs text-muted-foreground">{field.name}</span>
          </button>
        ))}
      </div>
    </div>,
    document.body,
  )
}

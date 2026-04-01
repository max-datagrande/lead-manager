import { Input } from '@/components/ui/input'
import { cn } from '@/lib/utils'
import { useEffect, useRef, useState } from 'react'

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
  const itemRefs = useRef([])

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

  // Scroll active item into view when navigating with keyboard
  useEffect(() => {
    itemRefs.current[activeIndex]?.scrollIntoView({ block: 'nearest' })
  }, [activeIndex])

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

  return (
    <div
      ref={containerRef}
      style={{ position: 'absolute', top: position.top, left: position.left, zIndex: 9999 }}
      className="w-72 overflow-hidden rounded-lg border bg-popover shadow-lg"
    >
      <div className="border-b p-2">
        <Input
          ref={inputRef}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onKeyDown={handleKeyDown}
          placeholder="Search fields…"
          className="h-7 text-xs"
        />
      </div>

      <div className="max-h-56 overflow-y-auto py-1">
        {filtered.length === 0 && (
          <p className="px-3 py-4 text-center text-xs text-muted-foreground">No fields found.</p>
        )}
        {filtered.map((field, i) => (
          <button
            key={field.id}
            ref={(el) => (itemRefs.current[i] = el)}
            type="button"
            onMouseDown={(e) => {
              e.preventDefault()
              onSelect(field)
            }}
            className={cn(
              'flex w-full flex-col gap-0.5 px-3 py-2 text-left transition-colors',
              i === activeIndex ? 'bg-accent text-accent-foreground' : 'hover:bg-accent/50',
            )}
          >
            <span className="text-xs font-medium leading-none">{field.label ?? field.name}</span>
            <span className="font-mono text-[10px] leading-none text-muted-foreground">{field.name}</span>
          </button>
        ))}
      </div>

      {filtered.length > 0 && (
        <div className="border-t px-3 py-1.5">
          <p className="text-[10px] text-muted-foreground">
            <kbd className="rounded border px-1 font-mono text-[9px]">↑↓</kbd> navegar
            {' · '}
            <kbd className="rounded border px-1 font-mono text-[9px]">↵</kbd> insertar
            {' · '}
            <kbd className="rounded border px-1 font-mono text-[9px]">Esc</kbd> cerrar
          </p>
        </div>
      )}
    </div>
  )
}

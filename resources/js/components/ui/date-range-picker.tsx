/* eslint-disable max-lines */
import { useEffect, useMemo, useRef, useState } from 'react'
import { Calendar as CalendarIcon, ChevronDownIcon, ChevronUpIcon, Eraser, Globe } from 'lucide-react'
import type { DateRange } from 'react-day-picker'

import { Button } from './button'
import { Calendar } from './calendar'
import { Popover, PopoverContent, PopoverTrigger } from './popover'
import { TimezoneSelect } from './timezone-select'
import { cn } from '@/lib/utils'
import { localDateToUtcIso, rangeDays, utcIsoToLocalDate } from '@/lib/timezone'
import { DEFAULT_TIMEZONE } from '@/hooks/use-user-timezone'

// NOTE: Compare mode is disabled — no consumer view uses it yet. To re-enable:
//   1) restore Switch + rangeCompare state
//   2) emit rangeCompare from onUpdate
//   3) wire compare-range visualization on the Calendar
// Original implementation is in git history (pre 2026-05-18).

interface InternalRange {
  from: Date | undefined
  to: Date | undefined
}

interface Preset {
  name: string
  label: string
  range: () => InternalRange
}

const PRESETS: Preset[] = [
  { name: 'today', label: 'Today', range: () => withDayBounds(new Date(), new Date()) },
  {
    name: 'yesterday',
    label: 'Yesterday',
    range: () => {
      const d = new Date()
      d.setDate(d.getDate() - 1)
      return withDayBounds(d, d)
    },
  },
  { name: 'last3', label: 'Last 3 days', range: () => lastNDays(3) },
  { name: 'last7', label: 'Last 7 days', range: () => lastNDays(7) },
  { name: 'last14', label: 'Last 14 days', range: () => lastNDays(14) },
  { name: 'last30', label: 'Last 30 days', range: () => lastNDays(30) },
  {
    name: 'thisMonth',
    label: 'This month',
    range: () => {
      const now = new Date()
      const from = new Date(now.getFullYear(), now.getMonth(), 1)
      return withDayBounds(from, now)
    },
  },
  {
    name: 'lastMonth',
    label: 'Last month',
    range: () => {
      const now = new Date()
      const from = new Date(now.getFullYear(), now.getMonth() - 1, 1)
      const to = new Date(now.getFullYear(), now.getMonth(), 0)
      return withDayBounds(from, to)
    },
  },
  { name: 'last90', label: 'Last 90 days', range: () => lastNDays(90) },
  {
    name: 'last6m',
    label: 'Last 6 months',
    range: () => {
      const to = new Date()
      const from = new Date(to.getFullYear(), to.getMonth() - 6, to.getDate())
      return withDayBounds(from, to)
    },
  },
]

function lastNDays(n: number): InternalRange {
  const to = new Date()
  const from = new Date()
  from.setDate(from.getDate() - (n - 1))
  return withDayBounds(from, to)
}

function withDayBounds(from: Date, to: Date): InternalRange {
  const f = new Date(from)
  f.setHours(0, 0, 0, 0)
  const t = new Date(to)
  t.setHours(23, 59, 59, 999)
  return { from: f, to: t }
}

function adjustForLocalTz(input: Date | string): Date {
  if (typeof input === 'string') {
    // Allow "YYYY-MM-DD" plain dates without TZ shift.
    if (/^\d{4}-\d{2}-\d{2}$/.test(input)) {
      const [y, m, d] = input.split('-').map((p) => parseInt(p, 10))
      return new Date(y, m - 1, d)
    }
    return new Date(input)
  }
  return input
}

function formatLabel(date: Date, locale: string): string {
  return date.toLocaleDateString(locale, { month: 'short', day: 'numeric', year: 'numeric' })
}

function isSameDay(a?: Date, b?: Date): boolean {
  if (!a || !b) return false
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()
}

function matchPreset(range: InternalRange): string | undefined {
  if (!range.from || !range.to) return undefined
  for (const p of PRESETS) {
    const r = p.range()
    if (isSameDay(r.from, range.from) && isSameDay(r.to, range.to)) {
      return p.name
    }
  }
  return undefined
}

export interface DateRangePickerProps {
  initialDateFrom?: Date | string
  initialDateTo?: Date | string
  /**
   * Emitted on Apply (range with UTC ISO strings) or on Clear (range = null).
   * Consumers should treat `range === null` as "drop the date filter".
   */
  onUpdate: (payload: { range: { from: string; to: string } | null; timezone: string }) => void
  align?: 'start' | 'center' | 'end'
  locale?: string
  isReset?: boolean
  /**
   * Default timezone used for converting the picker's wall-clock dates to UTC
   * on Apply. Falls back to America/New_York (EST) when omitted.
   * Typically fed from useUserTimezone().timezone.
   */
  defaultTimezone?: string
}

export function DateRangePicker({
  initialDateFrom,
  initialDateTo,
  onUpdate,
  align = 'end',
  locale = 'en-US',
  isReset = false,
  defaultTimezone = DEFAULT_TIMEZONE,
}: DateRangePickerProps) {
  const hasInitial = initialDateFrom !== undefined || initialDateTo !== undefined

  const getInitialRange = (): InternalRange => {
    if (!hasInitial) return { from: undefined, to: undefined }
    return {
      from: initialDateFrom !== undefined ? adjustForLocalTz(initialDateFrom) : undefined,
      to: initialDateTo !== undefined ? adjustForLocalTz(initialDateTo) : undefined,
    }
  }

  const [isOpen, setIsOpen] = useState(false)
  const [range, setRange] = useState<InternalRange>(getInitialRange)
  const [timezone, setTimezone] = useState<string>(defaultTimezone)
  const [isPlaceholder, setIsPlaceholder] = useState(!hasInitial)

  const openedRangeRef = useRef<InternalRange | undefined>(undefined)
  const openedTimezoneRef = useRef<string>(defaultTimezone)

  // External reset (e.g. "Clear Filters" in toolbar).
  useEffect(() => {
    if (isReset) {
      setRange({ from: undefined, to: undefined })
      setIsPlaceholder(true)
    }
  }, [isReset])

  // Sync timezone when the consumer-provided default changes (e.g. user updated profile).
  useEffect(() => {
    setTimezone(defaultTimezone)
  }, [defaultTimezone])

  // Snapshot when opened so Cancel can revert.
  useEffect(() => {
    if (isOpen) {
      openedRangeRef.current = range
      openedTimezoneRef.current = timezone
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen])

  const selectedPreset = useMemo(() => matchPreset(range), [range])

  const handlePreset = (preset: Preset) => {
    const r = preset.range()
    setRange(r)
    setIsPlaceholder(false)
  }

  const handleCalendarSelect = (val: DateRange | undefined) => {
    if (!val?.from) return
    setIsPlaceholder(false)
    setRange({ from: val.from, to: val.to ?? val.from })
  }

  const handleCancel = () => {
    setRange(openedRangeRef.current ?? { from: undefined, to: undefined })
    setTimezone(openedTimezoneRef.current)
    setIsOpen(false)
  }

  const handleApply = () => {
    if (!range.from || !range.to) {
      setIsOpen(false)
      return
    }
    onUpdate({
      range: {
        from: localDateToUtcIso(range.from, timezone),
        to: localDateToUtcIso(range.to, timezone),
      },
      timezone,
    })
    setIsOpen(false)
  }

  const handleClear = () => {
    setRange({ from: undefined, to: undefined })
    setIsPlaceholder(true)
    onUpdate({ range: null, timezone })
    setIsOpen(false)
  }

  const triggerLabel = isPlaceholder
    ? 'dd/mm/yyyy - dd/mm/yyyy'
    : `${formatLabel(range.from!, locale)}${range.to ? ' - ' + formatLabel(range.to, locale) : ''}`

  const hasRange = Boolean(range.from)
  const headerRangeLabel = hasRange ? `${formatLabel(range.from!, locale)}${range.to ? ' - ' + formatLabel(range.to, locale) : ''}` : null
  const headerDays = range.from && range.to ? `Range: ${rangeDays(range.from, range.to)} days` : null

  return (
    <Popover modal={true} open={isOpen} onOpenChange={setIsOpen}>
      <PopoverTrigger asChild>
        <Button variant="outline" className="h-auto min-w-[240px] justify-between py-2">
          <div className="flex items-center gap-2 text-left">
            <CalendarIcon className="h-4 w-4 text-muted-foreground" />
            <span className={cn('transition-colors', isPlaceholder && 'text-muted-foreground')}>{triggerLabel}</span>
          </div>
          <span className="-mr-2 pl-1 opacity-60">{isOpen ? <ChevronUpIcon className="h-5 w-5" /> : <ChevronDownIcon className="h-5 w-5" />}</span>
        </Button>
      </PopoverTrigger>

      <PopoverContent align={align} className="w-auto p-0">
        {/* Header */}
        <div className={cn('flex items-start gap-4 border-b px-4 py-3', hasRange ? 'justify-between' : 'justify-end')}>
          {hasRange && (
            <div>
              <div className="text-sm font-medium">{headerRangeLabel}</div>
              {headerDays && <div className="text-xs text-muted-foreground">{headerDays}</div>}
            </div>
          )}
          <TimezoneSelect
            value={timezone}
            onValueChange={setTimezone}
            className="h-9 w-auto min-w-fit shrink-0"
            icon={<Globe className="h-3.5 w-3.5 text-muted-foreground" />}
          />
        </div>

        {/* Body: sidebar + dual calendar */}
        <div className="flex">
          <div className="flex w-[160px] flex-col gap-0.5 border-r p-2">
            {PRESETS.map((preset) => {
              const isSelected = selectedPreset === preset.name
              return (
                <button
                  key={preset.name}
                  type="button"
                  onClick={() => handlePreset(preset)}
                  className={cn(
                    'rounded-md px-3 py-1.5 text-left text-sm transition-colors',
                    isSelected ? 'bg-primary/10 text-primary font-medium' : 'hover:bg-accent hover:text-accent-foreground text-muted-foreground',
                  )}
                >
                  {preset.label}
                </button>
              )
            })}
          </div>

          <div className="p-3">
            <Calendar
              mode="range"
              numberOfMonths={2}
              selected={range as DateRange}
              onSelect={handleCalendarSelect}
              defaultMonth={range.from ?? new Date(new Date().setMonth(new Date().getMonth() - 1))}
            />
          </div>
        </div>

        {/* Footer */}
        <div className="flex items-center justify-between gap-2 border-t px-4 py-3">
          {/* TODO: Compare toggle removed — restore here when a consumer view needs it. */}
          <div>
            {hasRange && (
              <Button variant="ghost-destructive" size="sm" onClick={handleClear}>
                <Eraser className="h-3.5 w-3.5" />
                Clear
              </Button>
            )}
          </div>
          <div className="flex items-center gap-2">
            <Button variant="outline" onClick={handleCancel}>
              Cancel
            </Button>
            <Button onClick={handleApply} disabled={!range.from || !range.to}>
              Apply
            </Button>
          </div>
        </div>
      </PopoverContent>
    </Popover>
  )
}

// Re-export helpers consumers may need when hydrating from backend UTC ISO.
export { utcIsoToLocalDate }

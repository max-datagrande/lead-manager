/* eslint-disable max-lines */
import { useState, useEffect, useRef } from 'react'
import { format } from 'date-fns'
import { Button } from './button'
import { Popover, PopoverContent, PopoverTrigger } from './popover'
import { Calendar } from './calendar'
/* import { DateInput } from './date-input' */
import { Label } from './label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue
} from './select'
import { Switch } from './switch'
import { ChevronUpIcon, ChevronDownIcon, CheckIcon, Calendar as CalendarIcon, Eraser } from 'lucide-react'
import { cn } from '@/lib/utils'

interface DateRange {
  from: Date | undefined
  to: Date | undefined
}
interface Preset {
  name: string
  label: string
}
export interface DateRangePickerProps {
  /** Click handler for applying the updates from DateRangePicker. */
  onUpdate?: (values: { range: DateRange, rangeCompare?: DateRange }) => void
  /** Initial value for start date */
  initialDateFrom?: Date | string
  /** Initial value for end date */
  initialDateTo?: Date | string
  /** Initial value for start date for compare */
  initialCompareFrom?: Date | string
  /** Initial value for end date for compare */
  initialCompareTo?: Date | string
  /** Alignment of popover */
  align?: 'start' | 'center' | 'end'
  /** Option for locale */
  locale?: string
  /** Option for showing compare feature */
  showCompare?: boolean
  /** Option for resetting the picker */
  isReset?: boolean
}

// Define presets
const PRESETS: Preset[] = [
  { name: 'today', label: 'Today' },
  { name: 'yesterday', label: 'Yesterday' },
  { name: 'last7', label: 'Last 7 days' },
  { name: 'last14', label: 'Last 14 days' },
  { name: 'last30', label: 'Last 30 days' },
  { name: 'thisWeek', label: 'This Week' },
  { name: 'lastWeek', label: 'Last Week' },
  { name: 'thisMonth', label: 'This Month' },
  { name: 'lastMonth', label: 'Last Month' }
]

const DateInput = ({ value, isPlaceholder }: { value?: Date, isPlaceholder?: boolean }) => (
  <div className="flex border rounded-lg items-center text-sm px-1">
    <CalendarIcon className="w-3 h-3 mr-2 text-slate-400" />
    <span className={cn(
      "text-sm w-24 select-none text-center",
      isPlaceholder ? "text-muted-foreground" : ""
    )}>
      {(!isPlaceholder && value) ? format(value, "dd/MM/yyyy") : "dd/mm/yyyy"}
    </span>
  </div>
)

const formatDateLabel = (date: Date, locale: string = 'en-us'): string => {
  return date.toLocaleDateString(locale, {
    month: 'short',
    day: 'numeric',
    year: 'numeric'
  })
}

const getDateAdjustedForTimezone = (dateInput: Date | string): Date => {
  if (typeof dateInput === 'string') {
    const parts = dateInput.split('-').map((part) => parseInt(part, 10))
    return new Date(parts[0], parts[1] - 1, parts[2])
  }
  return dateInput
}

/** The DateRangePicker component allows a user to select a range of dates */
export function DateRangePicker({
  initialDateFrom,
  initialDateTo,
  initialCompareFrom,
  initialCompareTo,
  onUpdate,
  align = 'end',
  locale = 'en-US',
  showCompare = true,
  isReset = false
}: DateRangePickerProps) {
  const hasInitialDates = initialDateFrom !== undefined || initialDateTo !== undefined;

  const handleClean = (): void => {
    setRange({ from: undefined, to: undefined });
    setRangeCompare(undefined);
    setIsPlaceholderActive(true);
  }
  // Modificado: Si no hay prop inicial, devolver undefined en lugar de "hoy"
  const getInitialFrom = () => initialDateFrom !== undefined
    ? getDateAdjustedForTimezone(initialDateFrom)
    : undefined;

  const getInitialTo = () => initialDateTo
    ? getDateAdjustedForTimezone(initialDateTo)
    : undefined;

  const [isOpen, setIsOpen] = useState(false)
  const [range, setRange] = useState<DateRange>({
    from: getInitialFrom(),
    to: getInitialTo()
  })

  const [rangeCompare, setRangeCompare] = useState<DateRange | undefined>(
    initialCompareFrom
      ? {
          from: new Date(new Date(initialCompareFrom).setHours(0, 0, 0, 0)),
          to: initialCompareTo
            ? new Date(new Date(initialCompareTo).setHours(0, 0, 0, 0))
            : new Date(new Date(initialCompareFrom).setHours(0, 0, 0, 0))
        }
      : undefined
  )

  const openedRangeRef = useRef<DateRange | undefined>(undefined)
  const openedRangeCompareRef = useRef<DateRange | undefined>(undefined)

  // Ahora el placeholder es activo si no hay fechas iniciales explícitas
  const [isPlaceholderActive, setIsPlaceholderActive] = useState(!hasInitialDates);
  const [selectedPreset, setSelectedPreset] = useState<string | undefined>(undefined)
  const [isSmallScreen, setIsSmallScreen] = useState(false)

  // LOG DE CAMBIOS EN FORMATO UTC
  useEffect(() => {
    if (range.from) {
      console.log('--- Cambio de Fecha (UTC) ---');
      console.log('Desde:', range.from.toISOString());
      if (range.to) {
        console.log('Hasta:', range.to.toISOString());
      } else {
        console.log('Hasta: Selección pendiente...');
      }
    } else {
        console.log('--- Estado Inicial / Reset (Sin selección) ---');
    }
  }, [range]);

  useEffect(() => {
    setIsSmallScreen(window.innerWidth < 960)
    const handleResize = (): void => { setIsSmallScreen(window.innerWidth < 960) }
    window.addEventListener('resize', handleResize)
    return () => { window.removeEventListener('resize', handleResize) }
  }, [])

  useEffect(() => {
    if (isReset) {
      setIsPlaceholderActive(true);
      resetValues();
    }
  }, [isReset])

  const getPresetRange = (presetName: string): DateRange => {
    const preset = PRESETS.find(({ name }) => name === presetName)
    if (!preset) throw new Error(`Unknown date range: ${presetName}`)
    const from = new Date()
    const to = new Date()
    const first = from.getDate() - from.getDay()

    switch (preset.name) {
      case 'today':
        from.setHours(0, 0, 0, 0); to.setHours(23, 59, 59, 999); break
      case 'yesterday':
        from.setDate(from.getDate() - 1); from.setHours(0, 0, 0, 0); to.setDate(to.getDate() - 1); to.setHours(23, 59, 59, 999); break
      case 'last7':
        from.setDate(from.getDate() - 6); from.setHours(0, 0, 0, 0); to.setHours(23, 59, 59, 999); break
      case 'last14':
        from.setDate(from.getDate() - 13); from.setHours(0, 0, 0, 0); to.setHours(23, 59, 59, 999); break
      case 'last30':
        from.setDate(from.getDate() - 29); from.setHours(0, 0, 0, 0); to.setHours(23, 59, 59, 999); break
      case 'thisWeek':
        from.setDate(first); from.setHours(0, 0, 0, 0); to.setHours(23, 59, 59, 999); break
      case 'lastWeek':
        from.setDate(from.getDate() - 7 - from.getDay()); to.setDate(to.getDate() - to.getDay() - 1); from.setHours(0, 0, 0, 0); to.setHours(23, 59, 59, 999); break
      case 'thisMonth':
        from.setDate(1); from.setHours(0, 0, 0, 0); to.setHours(23, 59, 59, 999); break
      case 'lastMonth':
        from.setMonth(from.getMonth() - 1); from.setDate(1); from.setHours(0, 0, 0, 0); to.setDate(0); to.setHours(23, 59, 59, 999); break
    }
    return { from, to }
  }

  const setPreset = (preset: string): void => {
    const range = getPresetRange(preset)
    setIsPlaceholderActive(false);
    setRange(range)
    if (rangeCompare) {
      const rangeCompare = {
        from: new Date(range.from.getFullYear() - 1, range.from.getMonth(), range.from.getDate()),
        to: range.to ? new Date(range.to.getFullYear() - 1, range.to.getMonth(), range.to.getDate()) : undefined
      }
      setRangeCompare(rangeCompare)
    }
  }

  const checkPreset = (): void => {
    // Si no hay fechas seleccionadas, no marcamos ningún preset
    if (!range.from || !range.to) {
        setSelectedPreset(undefined);
        return;
    }

    for (const preset of PRESETS) {
      const presetRange = getPresetRange(preset.name)
      const normalizedRangeFrom = new Date(range.from);
      normalizedRangeFrom.setHours(0, 0, 0, 0);
      const normalizedPresetFrom = new Date(presetRange.from.setHours(0, 0, 0, 0))
      const normalizedRangeTo = new Date(range.to);
      normalizedRangeTo.setHours(0, 0, 0, 0);
      const normalizedPresetTo = new Date(presetRange.to?.setHours(0, 0, 0, 0) || 0)

      if (normalizedRangeFrom.getTime() === normalizedPresetFrom.getTime() && normalizedRangeTo.getTime() === normalizedPresetTo.getTime()) {
        setSelectedPreset(preset.name)
        return
      }
    }
    setSelectedPreset(undefined)
  }

  const resetValues = (): void => {
    setRange({
      from: getInitialFrom(),
      to: getInitialTo()
    })
    setRangeCompare(initialCompareFrom ? {
      from: getDateAdjustedForTimezone(initialCompareFrom),
      to: initialCompareTo ? getDateAdjustedForTimezone(initialCompareTo) : getDateAdjustedForTimezone(initialCompareFrom)
    } : undefined)
  }

  useEffect(() => { checkPreset() }, [range])

  const PresetButton = ({ preset, label, isSelected }: { preset: string, label: string, isSelected: boolean }) => (
    <Button className={cn((isSelected && 'pointer-events-none'), 'w-full text-center flex justify-between gap-2')} variant="ghost" onClick={() => setPreset(preset)}>
      <>
        <span className={cn('opacity-0', isSelected && 'opacity-70')}>
          <CheckIcon width={18} height={18} />
        </span>
        <span className='flex-1 text-center'>
          {label}
        </span>
      </>
    </Button>
  )

  const areRangesEqual = (a?: DateRange, b?: DateRange): boolean => {
    if (!a || !b) return a === b
    return (a.from?.getTime() === b.from?.getTime() && (!a.to || !b.to || a.to.getTime() === b.to.getTime()))
  }
  /* const restoreToOpenedState = (): void => {
    console.log('restoreToOpenedState', openedRangeRef.current)
    if (openedRangeRef.current) {
      setRange(openedRangeRef.current)
    }
  }*/
  useEffect(() => {
    if (isOpen) {
      openedRangeRef.current = range
      openedRangeCompareRef.current = rangeCompare
    }
  }, [isOpen])

  return (
    <Popover
      modal={true}
      open={isOpen}
      onOpenChange={(open: boolean) => {
        console.log('onOpenChange', open)
        /* if (!open) restoreToOpenedState() */
        setIsOpen(open)
      }}
    >
      <PopoverTrigger asChild>
        <Button variant="outline" className="min-w-[240px] justify-between h-auto py-2">
          <div className="text-left">
            <div className={cn("py-1 transition-colors", isPlaceholderActive ? "text-muted-foreground" : "")}>
              {isPlaceholderActive
                ? 'dd/mm/yyyy - dd/mm/yyyy'
                : `${formatDateLabel(range.from!, locale)}${range.to != null ? ' - ' + formatDateLabel(range.to, locale) : ''}`}
            </div>
            {rangeCompare != null && (
              <div className="opacity-60 text-xs -mt-1">
                vs. {formatDateLabel(rangeCompare.from!, locale)}{rangeCompare.to != null ? ` - ${formatDateLabel(rangeCompare.to, locale)}` : ''}
              </div>
            )}
          </div>
          <div className="pl-1 opacity-60 -mr-2 scale-125">
            {isOpen ? <ChevronUpIcon width={24} /> : <ChevronDownIcon width={24} />}
          </div>
        </Button>
      </PopoverTrigger>
      <PopoverContent align={align} className="w-auto">
        <div className="flex">
          <div className="flex">
            <div className="flex flex-col">
              <div className="flex flex-col lg:flex-row gap-2 px-3 justify-center items-center lg:items-start pb-4 lg:pb-0">
                {showCompare && (
                  <div className="flex items-center space-x-2 pr-4 py-1">
                    <Switch
                      defaultChecked={Boolean(rangeCompare)}
                      onCheckedChange={(checked: boolean) => {
                        if (checked) {
                          if (!range.to && range.from) setRange({ from: range.from, to: range.from })
                          if (range.from && range.to) {
                              setRangeCompare({
                                  from: new Date(range.from.getFullYear(), range.from.getMonth(), range.from.getDate() - 365),
                                  to: new Date(range.to.getFullYear() - 1, range.to.getMonth(), range.to.getDate())
                              })
                          }
                        } else setRangeCompare(undefined)
                      }}
                      id="compare-mode"
                    />
                    <Label htmlFor="compare-mode">Compare</Label>
                  </div>
                )}
                <div className="flex flex-col gap-2">
                  <div className="flex gap-2">
                  <DateInput value={range.from} isPlaceholder={isPlaceholderActive} />
                  <div className="py-1 text-muted-foreground">-</div>
                  <DateInput value={range.to} isPlaceholder={isPlaceholderActive} />
                </div>
                {rangeCompare != null && (
                  <div className="flex gap-2">
                    <DateInput value={rangeCompare?.from} isPlaceholder={isPlaceholderActive} />
                    <div className="py-1 text-muted-foreground">-</div>
                    <DateInput value={rangeCompare?.to} isPlaceholder={isPlaceholderActive} />
                  </div>
                )}
                </div>
              </div>
              { isSmallScreen && (
                <Select defaultValue={selectedPreset} onValueChange={(value) => { setPreset(value) }}>
                  <SelectTrigger className="w-[180px] mx-auto mb-2">
                    <SelectValue placeholder="Select..." />
                  </SelectTrigger>
                  <SelectContent>
                    {PRESETS.map((preset) => (
                      <SelectItem key={preset.name} value={preset.name}>
                        {preset.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              )}
              <div>
                <Calendar
                  mode="range"
                  onSelect={(value: any) => { if (value?.from != null) { setIsPlaceholderActive(false); setRange({ from: value.from, to: value?.to }) } }}
                  selected={range}
                  numberOfMonths={isSmallScreen ? 1 : 2}
                  defaultMonth={
                    new Date(
                      new Date().setMonth(
                        new Date().getMonth() - (isSmallScreen ? 0 : 1)
                      )
                    )
                  }
                />
              </div>
            </div>
          </div>
          {!isSmallScreen && (
            <div className="flex w-full flex-col items-end gap-1 px-3">
              {PRESETS.map((preset) => (
                <PresetButton
                  key={preset.name}
                  preset={preset.name}
                  label={preset.label}
                  isSelected={selectedPreset === preset.name}
                />
              ))}
            </div>
          )}
        </div>
        <div className="flex justify-end gap-2 py-2 pr-4 border-t mt-2">
          {range.from && range.to && (
            <Button onClick={handleClean} variant="destructive">
              <Eraser width={24} />
            </Button>
          )}
          <Button onClick={() => {/*  restoreToOpenedState(); */ setIsOpen(false); }} variant="ghost">Cancel</Button>
          <Button onClick={() => { setIsOpen(false); if (!areRangesEqual(range, openedRangeRef.current) || !areRangesEqual(rangeCompare, openedRangeCompareRef.current)) { setIsPlaceholderActive(false); onUpdate?.({ range, rangeCompare }) } }}>Update</Button>
        </div>
      </PopoverContent>
    </Popover>
  )
}


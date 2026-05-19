import { Badge } from '@/components/ui/badge'
import { SearchableSelect } from '@/components/ui/searchable-select'
import type { SharedData, TimezoneOption } from '@/types'
import { usePage } from '@inertiajs/react'
import type { ReactNode } from 'react'

interface TimezoneSelectProps {
  value: string
  onValueChange: (value: string) => void
  /** Override the timezone list. Defaults to Inertia shared `timezones`. */
  timezones?: TimezoneOption[]
  placeholder?: string
  searchPlaceholder?: string
  emptyMessage?: string
  className?: string
  disabled?: boolean
  /** Optional icon rendered before the label in the trigger button. */
  icon?: ReactNode
}

function renderTimezone(tz: TimezoneOption): ReactNode {
  return (
    <div className="flex w-full items-center justify-between gap-2">
      <span className="truncate">
        {tz.name}
        {tz.offset && <span className="ml-1 text-xs text-muted-foreground">({tz.offset})</span>}
      </span>
      {tz.description && (
        <Badge variant="muted" className="shrink-0">
          {tz.description}
        </Badge>
      )}
    </div>
  )
}

/**
 * Timezone picker built on top of SearchableSelect.
 *
 * Pulls the timezone catalogue from Inertia shared data by default (set in
 * HandleInertiaRequests::share → 'timezones'), so consumers usually only need
 * to wire value + onValueChange.
 */
export function TimezoneSelect({
  value,
  onValueChange,
  timezones,
  placeholder = 'Select timezone...',
  searchPlaceholder = 'Search timezone...',
  emptyMessage = 'No timezones match.',
  className,
  disabled,
  icon,
}: TimezoneSelectProps) {
  const sharedTimezones = usePage<SharedData>().props.timezones ?? []
  const options = timezones ?? sharedTimezones

  return (
    <SearchableSelect
      options={options}
      value={value}
      onValueChange={onValueChange}
      placeholder={placeholder}
      searchPlaceholder={searchPlaceholder}
      emptyMessage={emptyMessage}
      className={className}
      disabled={disabled}
      renderOption={renderTimezone}
      triggerIcon={icon}
    />
  )
}

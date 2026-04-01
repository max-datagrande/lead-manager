import { cn } from '@/lib/utils'
import { LayoutGrid, Radio, Send } from 'lucide-react'

const TYPES = [
  {
    value: 'post-only',
    label: 'Post Only',
    description: 'Send the lead directly to a buyer endpoint.',
    icon: Send,
  },
  {
    value: 'ping-post',
    label: 'Ping-Post',
    description: 'Ping for a bid first, then post on acceptance.',
    icon: Radio,
  },
  {
    value: 'offerwall',
    label: 'Offerwall',
    description: 'Display offers to users via an offerwall widget.',
    icon: LayoutGrid,
  },
] as const

type IntegrationType = (typeof TYPES)[number]['value']

interface IntegrationTypeCardsProps {
  value: IntegrationType
  onChange?: (value: IntegrationType) => void
  readonly?: boolean
}

export function IntegrationTypeCards({ value, onChange, readonly = false }: IntegrationTypeCardsProps) {
  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
      {TYPES.map(({ value: typeValue, label, description, icon: Icon }) => {
        const selected = value === typeValue
        return (
          <button
            key={typeValue}
            type="button"
            disabled={readonly}
            onClick={() => !readonly && onChange?.(typeValue)}
            className={cn(
              'flex flex-col gap-2 rounded-lg border p-4 text-left transition-colors',
              readonly
                ? selected
                  ? 'border-primary bg-primary/5 ring-1 ring-primary'
                  : 'cursor-default border-border opacity-40'
                : selected
                  ? 'border-primary bg-primary/5 ring-1 ring-primary'
                  : 'border-border hover:border-muted-foreground/40 hover:bg-muted/40',
            )}
          >
            <div className="flex items-center gap-2">
              <Icon className={cn('size-4 shrink-0', selected ? 'text-primary' : 'text-muted-foreground')} />
              <span className={cn('text-sm font-medium', selected ? 'text-primary' : 'text-foreground')}>{label}</span>
            </div>
            <p className="text-xs leading-snug text-muted-foreground">{description}</p>
          </button>
        )
      })}
    </div>
  )
}

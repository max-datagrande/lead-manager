import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { HelpCircle } from 'lucide-react'

interface HintItem {
  label: string
  description: string
}

interface Props {
  /** Simple one-liner hint, or an array of paragraphs rendered with spacing between them. */
  text?: string | string[]
  /** Structured list of options with label + description. */
  items?: HintItem[]
  side?: 'top' | 'right' | 'bottom' | 'left'
}

export function FieldHint({ text, items, side = 'right' }: Props) {
  const paragraphs = Array.isArray(text) ? text : text ? [text] : []

  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <HelpCircle className="ml-1 inline h-3.5 w-3.5 cursor-help text-muted-foreground" />
      </TooltipTrigger>
      <TooltipContent side={side} className="max-w-xs space-y-2 leading-relaxed">
        {paragraphs.map((p, i) => (
          <p key={i}>{p}</p>
        ))}
        {items && (
          <ul className="space-y-2">
            {items.map((item) => (
              <li key={item.label}>
                <span className="font-semibold">{item.label}</span>
                <span className="text-primary-foreground/80"> — {item.description}</span>
              </li>
            ))}
          </ul>
        )}
      </TooltipContent>
    </Tooltip>
  )
}

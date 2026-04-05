import { Button } from '@/components/ui/button'
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { cn } from '@/lib/utils'
import { Check, ChevronsUpDown } from 'lucide-react'
import { useMemo, useState } from 'react'

export interface TokenOption {
  id: number
  name: string
  label: string
  group: string
}

interface TokenComboboxProps {
  value: string
  onChange: (value: string) => void
  tokens: TokenOption[]
  usedTokens?: string[]
  placeholder?: string
  className?: string
}

export function TokenCombobox({ value, onChange, tokens, usedTokens = [], placeholder = 'Select token...', className }: TokenComboboxProps) {
  const [open, setOpen] = useState(false)

  const availableTokens = useMemo(() => tokens.filter((t) => t.name === value || !usedTokens.includes(t.name)), [tokens, value, usedTokens])

  const selectedToken = tokens.find((t) => t.name === value)

  const groups = useMemo(() => {
    const grouped: Record<string, TokenOption[]> = {}
    availableTokens.forEach((t) => {
      if (t.name === value) return
      if (!grouped[t.group]) grouped[t.group] = []
      grouped[t.group].push(t)
    })
    return grouped
  }, [availableTokens, value])

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          role="combobox"
          aria-expanded={open}
          className={cn('justify-between font-normal', !value && 'text-muted-foreground', className)}
        >
          <span className="truncate">{selectedToken ? selectedToken.label : placeholder}</span>
          <ChevronsUpDown className="ml-1 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-72 p-0" align="start">
        <Command>
          <CommandInput placeholder="Search tokens..." />
          <CommandList>
            <CommandEmpty>No tokens found.</CommandEmpty>
            {selectedToken && (
              <CommandGroup heading="Selected">
                <CommandItem
                  key={`selected-${selectedToken.id}`}
                  value={`${selectedToken.label} ${selectedToken.name}`}
                  onSelect={() => {
                    onChange('')
                    setOpen(false)
                  }}
                >
                  <div className="flex items-center gap-2">
                    <Check className="h-4 w-4 shrink-0 opacity-100" />
                    <div className="min-w-0">
                      <p className="truncate text-sm font-medium">{selectedToken.label}</p>
                      <p className="truncate text-xs text-muted-foreground">{selectedToken.name}</p>
                    </div>
                  </div>
                </CommandItem>
              </CommandGroup>
            )}
            {Object.entries(groups).map(([group, items]) => (
              <CommandGroup key={group} heading={group}>
                {items.map((token) => (
                  <CommandItem
                    key={`${token.group}-${token.id}`}
                    value={`${token.label} ${token.name}`}
                    onSelect={() => {
                      onChange(token.name === value ? '' : token.name)
                      setOpen(false)
                    }}
                  >
                    <div className="flex items-center gap-2">
                      <Check className={cn('h-4 w-4 shrink-0', value === token.name ? 'opacity-100' : 'opacity-0')} />
                      <div className="min-w-0">
                        <p className="truncate text-sm">{token.label}</p>
                        <p className="truncate text-xs text-muted-foreground">{token.name}</p>
                      </div>
                    </div>
                  </CommandItem>
                ))}
              </CommandGroup>
            ))}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}

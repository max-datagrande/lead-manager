import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown, Type } from 'lucide-react';
import { useMemo, useState } from 'react';

const LITERAL_PREFIX = 'literal:';

export interface TokenOption {
  id: number;
  name: string;
  label: string;
  group: string;
}

interface TokenComboboxProps {
  value: string;
  onChange: (value: string) => void;
  tokens: TokenOption[];
  usedTokens?: string[];
  placeholder?: string;
  className?: string;
}

function isLiteral(value: string): boolean {
  return value.startsWith(LITERAL_PREFIX);
}

function getLiteralValue(value: string): string {
  return value.slice(LITERAL_PREFIX.length);
}

export function TokenCombobox({ value, onChange, tokens, usedTokens = [], placeholder = 'Select token...', className }: TokenComboboxProps) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');

  const availableTokens = useMemo(() => tokens.filter((t) => t.name === value || !usedTokens.includes(t.name)), [tokens, value, usedTokens]);

  const selectedToken = tokens.find((t) => t.name === value);
  const isLiteralValue = isLiteral(value);

  const groups = useMemo(() => {
    const grouped: Record<string, TokenOption[]> = {};
    availableTokens.forEach((t) => {
      if (t.name === value) return;
      if (!grouped[t.group]) grouped[t.group] = [];
      grouped[t.group].push(t);
    });
    return grouped;
  }, [availableTokens, value]);

  const displayLabel = selectedToken ? selectedToken.label : isLiteralValue ? getLiteralValue(value) : placeholder;

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          role="combobox"
          aria-expanded={open}
          className={cn('justify-between font-normal', !value && 'text-muted-foreground', className)}
        >
          <span className="flex items-center gap-1.5 truncate">
            {isLiteralValue && <Type className="h-3 w-3 shrink-0 text-muted-foreground" />}
            <span className={cn('truncate', isLiteralValue && 'text-muted-foreground')}>{displayLabel}</span>
          </span>
          <ChevronsUpDown className="ml-1 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-72 p-0" align="start">
        <Command shouldFilter={false}>
          <CommandInput placeholder="Search tokens or type a value..." value={search} onValueChange={setSearch} />
          <CommandList>
            {/* Literal value option */}
            {search.trim() && (
              <CommandGroup heading="Literal Value">
                <CommandItem
                  value={`__literal__${search}`}
                  onSelect={() => {
                    onChange(`${LITERAL_PREFIX}${search.trim()}`);
                    setSearch('');
                    setOpen(false);
                  }}
                >
                  <div className="flex items-center gap-2">
                    <Type className="h-4 w-4 shrink-0 text-muted-foreground" />
                    <div className="min-w-0">
                      <p className="truncate text-sm">
                        Use <span className="font-medium text-foreground">"{search.trim()}"</span> as literal
                      </p>
                    </div>
                  </div>
                </CommandItem>
              </CommandGroup>
            )}

            {/* Selected token/literal */}
            {(selectedToken || isLiteralValue) && (
              <CommandGroup heading="Selected">
                <CommandItem
                  key="selected"
                  value={`__selected__${value}`}
                  onSelect={() => {
                    onChange('');
                    setSearch('');
                    setOpen(false);
                  }}
                >
                  <div className="flex items-center gap-2">
                    <Check className="h-4 w-4 shrink-0 opacity-100" />
                    <div className="min-w-0">
                      {selectedToken ? (
                        <>
                          <p className="truncate text-sm font-medium">{selectedToken.label}</p>
                          <p className="truncate text-xs text-muted-foreground">{selectedToken.name}</p>
                        </>
                      ) : (
                        <>
                          <p className="truncate text-sm font-medium text-muted-foreground">"{getLiteralValue(value)}"</p>
                          <p className="truncate text-xs text-muted-foreground">literal value</p>
                        </>
                      )}
                    </div>
                  </div>
                </CommandItem>
              </CommandGroup>
            )}

            {/* Token groups filtered by search */}
            {Object.entries(groups).map(([group, items]) => {
              const filtered = search
                ? items.filter((t) => t.label.toLowerCase().includes(search.toLowerCase()) || t.name.toLowerCase().includes(search.toLowerCase()))
                : items;
              if (filtered.length === 0) return null;
              return (
                <CommandGroup key={group} heading={group}>
                  {filtered.map((token) => (
                    <CommandItem
                      key={`${token.group}-${token.id}`}
                      value={`${token.label} ${token.name}`}
                      onSelect={() => {
                        onChange(token.name === value ? '' : token.name);
                        setSearch('');
                        setOpen(false);
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
              );
            })}

            {!search && Object.keys(groups).length === 0 && !selectedToken && !isLiteralValue && <CommandEmpty>No tokens available.</CommandEmpty>}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}

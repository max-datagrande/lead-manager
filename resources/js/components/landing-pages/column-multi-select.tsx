import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { AvailableColumns, ColumnCatalogItem, LandingPageColumn, LandingPageColumnSource } from '@/types/models/landing-page';
import { Check, ChevronsUpDown, X } from 'lucide-react';
import { useMemo, useState } from 'react';

interface ColumnMultiSelectProps {
  value: LandingPageColumn[];
  onChange: (next: LandingPageColumn[]) => void;
  available: AvailableColumns;
  disabled?: boolean;
  className?: string;
}

function makeKey(source: LandingPageColumnSource, reference: string) {
  return `${source}:${reference}`;
}

export function ColumnMultiSelect({ value, onChange, available, disabled = false, className }: ColumnMultiSelectProps) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');

  const selectedKeys = useMemo(() => new Set(value.map((c) => makeKey(c.source, c.reference))), [value]);

  const fieldByRef = useMemo(() => new Map(available.fields.map((f) => [String(f.id), f])), [available.fields]);
  const trafficByRef = useMemo(() => new Map(available.traffic.map((t) => [t.name, t])), [available.traffic]);

  const resolveLabel = (col: LandingPageColumn): string => {
    if (col.source === 'field') return fieldByRef.get(col.reference)?.label ?? `Field #${col.reference}`;
    return trafficByRef.get(col.reference)?.label ?? col.reference;
  };

  const toggle = (source: LandingPageColumnSource, reference: string) => {
    const key = makeKey(source, reference);
    if (selectedKeys.has(key)) {
      onChange(value.filter((c) => makeKey(c.source, c.reference) !== key));
    } else {
      onChange([...value, { source, reference }]);
    }
  };

  const remove = (source: LandingPageColumnSource, reference: string) => {
    onChange(value.filter((c) => !(c.source === source && c.reference === reference)));
  };

  const filterItems = (items: ColumnCatalogItem[]) => {
    if (!search.trim()) return items;
    const q = search.toLowerCase();
    return items.filter((i) => i.label.toLowerCase().includes(q) || i.name.toLowerCase().includes(q));
  };

  const filteredFields = filterItems(available.fields);
  const filteredTraffic = filterItems(available.traffic);
  const triggerLabel = value.length === 0 ? 'Select columns…' : `${value.length} column${value.length === 1 ? '' : 's'} selected`;

  return (
    <div className={cn('space-y-2', className)}>
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            type="button"
            variant="outline"
            role="combobox"
            aria-expanded={open}
            disabled={disabled}
            className={cn('w-full justify-between font-normal', value.length === 0 && 'text-muted-foreground')}
          >
            <span className="truncate">{triggerLabel}</span>
            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-(--radix-popover-trigger-width) p-0" align="start">
          <Command shouldFilter={false}>
            <CommandInput placeholder="Search columns…" value={search} onValueChange={setSearch} />
            <CommandList>
              {filteredFields.length === 0 && filteredTraffic.length === 0 && <CommandEmpty>No columns match your search.</CommandEmpty>}

              {filteredFields.length > 0 && (
                <CommandGroup heading="Lead Fields">
                  {filteredFields.map((item) => {
                    const reference = String(item.id);
                    const checked = selectedKeys.has(makeKey('field', reference));
                    return (
                      <CommandItem key={`field-${item.id}`} value={`field ${item.label} ${item.name}`} onSelect={() => toggle('field', reference)}>
                        <Check className={cn('mr-2 h-4 w-4 shrink-0', checked ? 'opacity-100' : 'opacity-0')} />
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm">{item.label}</p>
                          <p className="truncate text-xs text-muted-foreground">{item.name}</p>
                        </div>
                      </CommandItem>
                    );
                  })}
                </CommandGroup>
              )}

              {filteredTraffic.length > 0 && (
                <CommandGroup heading="Traffic Columns">
                  {filteredTraffic.map((item) => {
                    const checked = selectedKeys.has(makeKey('traffic', item.name));
                    return (
                      <CommandItem
                        key={`traffic-${item.id}`}
                        value={`traffic ${item.label} ${item.name}`}
                        onSelect={() => toggle('traffic', item.name)}
                      >
                        <Check className={cn('mr-2 h-4 w-4 shrink-0', checked ? 'opacity-100' : 'opacity-0')} />
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm">{item.label}</p>
                          <p className="truncate text-xs text-muted-foreground">{item.name}</p>
                        </div>
                      </CommandItem>
                    );
                  })}
                </CommandGroup>
              )}
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>

      {value.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {value.map((col) => (
            <Badge key={makeKey(col.source, col.reference)} variant="secondary" className="gap-1 pr-1 font-normal">
              <span className="text-xs text-muted-foreground">{col.source === 'field' ? 'field' : 'traffic'}</span>
              <span>{resolveLabel(col)}</span>
              <button
                type="button"
                onClick={() => remove(col.source, col.reference)}
                disabled={disabled}
                className="ml-0.5 rounded-sm p-0.5 hover:bg-muted-foreground/20"
                aria-label={`Remove ${resolveLabel(col)}`}
              >
                <X className="h-3 w-3" />
              </button>
            </Badge>
          ))}
        </div>
      )}
    </div>
  );
}

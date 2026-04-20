import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { BuyerOption } from '@/types/models/lead-quality';
import { Check, ChevronsUpDown, X } from 'lucide-react';
import { useState } from 'react';

interface Props {
  buyers: BuyerOption[];
  value: number[];
  onChange: (value: number[]) => void;
  disabled?: boolean;
}

export function BuyerMultiSelect({ buyers, value, onChange, disabled = false }: Props) {
  const [open, setOpen] = useState(false);

  const selected = buyers.filter((b) => value.includes(b.id));

  const toggle = (id: number) => {
    if (value.includes(id)) {
      onChange(value.filter((v) => v !== id));
    } else {
      onChange([...value, id]);
    }
  };

  const remove = (id: number) => {
    onChange(value.filter((v) => v !== id));
  };

  return (
    <div className="space-y-2">
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            type="button"
            variant="outline"
            role="combobox"
            aria-expanded={open}
            disabled={disabled}
            className="w-full justify-between font-normal"
          >
            <span className="text-muted-foreground">
              {selected.length === 0 ? 'Select buyers…' : `${selected.length} buyer${selected.length === 1 ? '' : 's'} selected`}
            </span>
            <ChevronsUpDown className="h-4 w-4 shrink-0 opacity-50" />
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-[--radix-popover-trigger-width] p-0" align="start">
          <Command>
            <CommandInput placeholder="Search buyer…" />
            <CommandList>
              <CommandEmpty>No buyers found.</CommandEmpty>
              <CommandGroup>
                {buyers.map((buyer) => {
                  const checked = value.includes(buyer.id);
                  return (
                    <CommandItem key={buyer.id} value={buyer.name} onSelect={() => toggle(buyer.id)} className="flex items-center gap-2">
                      <div
                        className={cn(
                          'flex h-4 w-4 items-center justify-center rounded-sm border',
                          checked ? 'border-primary bg-primary text-primary-foreground' : 'border-muted-foreground/40',
                        )}
                      >
                        {checked && <Check className="h-3 w-3" />}
                      </div>
                      <span className="flex-1 truncate">{buyer.name}</span>
                      <Badge variant="outline" className="text-xs">
                        {buyer.type}
                      </Badge>
                    </CommandItem>
                  );
                })}
              </CommandGroup>
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>

      {selected.length > 0 && (
        <div className="flex flex-wrap gap-2">
          {selected.map((buyer) => (
            <Badge key={buyer.id} variant="secondary" className="gap-1 py-1 pr-1 pl-2">
              <span>{buyer.name}</span>
              <button
                type="button"
                onClick={() => remove(buyer.id)}
                className="rounded-full p-0.5 hover:bg-muted-foreground/20"
                aria-label={`Remove ${buyer.name}`}
                disabled={disabled}
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

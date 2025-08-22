import { Check, ChevronsUpDown } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export function ComboboxUnique({ items, label, onChange, labelNoData, currentValue }) {
  const [open, setOpen] = React.useState(false);
  const [value, setValue] = React.useState(currentValue || '');

  // Sincronizar el estado interno con currentValue cuando cambie desde el exterior
  React.useEffect(() => {
    setValue(currentValue || '');
  }, [currentValue]);

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button variant="outline" role="combobox" aria-expanded={open} className="w-full max-w-52 justify-between">
          {value ? items.find((item) => item.value === value)?.label : `Select a ${label}...`}
          <ChevronsUpDown className="opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-full max-w-52 p-0">
        <Command>
          <CommandInput placeholder={`Search ${label}...`} className="h-9" />
          <CommandList>
            <CommandEmpty>{labelNoData ?? 'No options found'}</CommandEmpty>
            <CommandGroup>
              {items.map((item) => (
                <CommandItem
                  key={item.value}
                  value={item.value}
                  onSelect={(currentValue) => {
                    const newValue = currentValue === value ? '' : currentValue;
                    setValue(newValue);
                    setOpen(false);
                    onChange(newValue);
                  }}
                >
                  {item.label}
                  <Check className={cn('ml-auto', value === item.value ? 'opacity-100' : 'opacity-0')} />
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}

import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Check, ChevronsUpDown } from 'lucide-react';
import { cn } from '@/lib/utils';

export function TokenInserter({ fields = [], onTokenSelect }) {
  const fieldsSorted = fields.slice().sort((a, b) => a.name.localeCompare(b.name));
  const [open, setOpen] = useState(false);
  const [value, setValue] = useState('');

  return (
    <div className="flex w-full justify-end">
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            variant="outline"
            role="combobox"
            aria-expanded={open}
            className="mt-1 w-full justify-between"
          >
            {value ? fieldsSorted.find((f) => f.name === value)?.name : 'Insert field as token...'}
            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0">
          <Command>
            <CommandInput placeholder="Search field..." />
            <CommandList>
              <CommandEmpty>No fields found.</CommandEmpty>
              <CommandGroup>
                {fieldsSorted.map((field) => (
                  <CommandItem
                    key={field.id}
                    value={field.name}
                    onSelect={(currentValue) => {
                      setValue(currentValue);
                      setOpen(false);
                      onTokenSelect(currentValue);
                    }}
                  >
                    {field.name}
                  </CommandItem>
                ))}
              </CommandGroup>
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>
    </div>
  );
}

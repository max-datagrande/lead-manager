import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList, CommandSeparator } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';
import { Check, CirclePlus } from 'lucide-react';
import { useState } from 'react';

interface CptypeSelectorProps {
  label: string;
  options: string[];
  selected: string[];
  onChange: (selected: string[]) => void;
}

export default function CptypeSelector({ label, options, selected, onChange }: CptypeSelectorProps) {
  const [open, setOpen] = useState(false);
  const selectedSet = new Set(selected);
  const allSelected = selected.length === options.length;

  const handleToggle = (value: string) => {
    const next = new Set(selectedSet);
    if (next.has(value)) {
      next.delete(value);
    } else {
      next.add(value);
    }
    onChange(Array.from(next));
  };

  const handleToggleAll = () => {
    if (allSelected) {
      onChange([]);
    } else {
      onChange([...options]);
    }
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button variant="outline" className="h-9 w-full justify-start gap-1">
          <CirclePlus className="size-4" />
          {label}
          {selected.length > 0 && (
            <>
              <Separator orientation="vertical" className="mr-2 ml-auto h-4" />
              <Badge variant="secondary" className="rounded-sm px-1 font-normal">
                {selected.length} selected
              </Badge>
            </>
          )}
        </Button>
      </PopoverTrigger>

      <PopoverContent className="w-56 p-0" align="start">
        <Command>
          <CommandInput placeholder="Search cptypes..." />
          <CommandList>
            <CommandEmpty>No cptypes found.</CommandEmpty>

            {/* Select / Unselect all */}
            <CommandGroup>
              <CommandItem onSelect={handleToggleAll} className="font-medium">
                <div
                  className={cn(
                    'mr-1 flex size-4 items-center justify-center rounded-sm border border-primary',
                    allSelected ? 'bg-primary text-primary-foreground' : 'opacity-50 [&_svg]:invisible',
                  )}
                >
                  <Check className="h-4 w-4 text-background" />
                </div>
                {allSelected ? 'Unselect all' : 'Select all'}
              </CommandItem>
            </CommandGroup>

            <CommandSeparator />

            {/* Individual cptypes */}
            <CommandGroup>
              {options.map((value) => {
                const isSelected = selectedSet.has(value);
                return (
                  <CommandItem key={value} onSelect={() => handleToggle(value)}>
                    <div
                      className={cn(
                        'mr-1 flex size-4 items-center justify-center rounded-sm border border-primary',
                        isSelected ? 'bg-primary text-primary-foreground' : 'opacity-50 [&_svg]:invisible',
                      )}
                    >
                      <Check className="h-4 w-4 text-background" />
                    </div>
                    {value}
                  </CommandItem>
                );
              })}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}

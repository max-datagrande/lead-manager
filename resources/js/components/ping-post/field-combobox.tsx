import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown } from 'lucide-react';
import { useMemo, useState } from 'react';

interface FieldComboboxProps {
  value: string;
  onChange: (value: string) => void;
  fields: { id: number; name: string; label?: string }[];
  usedFields?: string[];
  placeholder?: string;
  className?: string;
}

export function FieldCombobox({ value, onChange, fields, usedFields = [], placeholder = 'Select field...', className }: FieldComboboxProps) {
  const [open, setOpen] = useState(false);

  const availableFields = useMemo(() => fields.filter((f) => f.name === value || !usedFields.includes(f.name)), [fields, value, usedFields]);

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          role="combobox"
          aria-expanded={open}
          className={cn('justify-between font-normal', !value && 'text-muted-foreground', className)}
        >
          <span className="truncate">{value ? (fields.find((f) => f.name === value)?.label || value) : placeholder}</span>
          <ChevronsUpDown className="ml-1 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-72 p-0" align="start">
        <Command>
          <CommandInput placeholder="Search fields..." />
          <CommandList>
            <CommandEmpty>No fields found.</CommandEmpty>
            <CommandGroup>
              {availableFields.map((field) => (
                <CommandItem
                  key={field.id}
                  value={`${field.label || ''} ${field.name}`}
                  onSelect={() => {
                    onChange(field.name === value ? '' : field.name);
                    setOpen(false);
                  }}
                >
                  <Check className={cn('mr-2 h-4 w-4 shrink-0', value === field.name ? 'opacity-100' : 'opacity-0')} />
                  <div className="min-w-0">
                    <p className="truncate text-sm">{field.label || field.name}</p>
                    <p className="truncate text-xs text-muted-foreground">{field.name}</p>
                  </div>
                </CommandItem>
              ))}
            </CommandGroup>
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}

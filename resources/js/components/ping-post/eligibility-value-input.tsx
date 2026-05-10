import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { SearchableSelect, type SearchableSelectOption } from '@/components/ui/searchable-select';
import type { EligibilityRule } from '@/types/ping-post';
import { X } from 'lucide-react';
import { useMemo } from 'react';

const VALUE_LESS_OPERATORS = ['is_empty', 'is_not_empty'];
const NUMERIC_OPERATORS = ['gt', 'gte', 'lt', 'lte'];
const MULTI_OPERATORS = ['in', 'not_in'];

interface Props {
  operator: EligibilityRule['operator'];
  value: string | string[] | null;
  onChange: (value: string | string[] | null) => void;
  possibleValues?: string[] | null;
  className?: string;
}

export function EligibilityValueInput({ operator, value, onChange, possibleValues, className }: Props) {
  const allOptions: SearchableSelectOption[] = useMemo(
    () => (Array.isArray(possibleValues) ? possibleValues.map((pv) => ({ value: pv, label: pv })) : []),
    [possibleValues],
  );

  if (VALUE_LESS_OPERATORS.includes(operator)) {
    return null;
  }

  const hasPossibleValues = allOptions.length > 0;

  // Numeric comparison: always free numeric input, even if field has possible_values
  if (NUMERIC_OPERATORS.includes(operator)) {
    return (
      <Input
        type="number"
        placeholder="value"
        value={typeof value === 'string' || typeof value === 'number' ? String(value) : ''}
        onChange={(e) => onChange(e.target.value)}
        className={className}
      />
    );
  }

  // Multi-value operators (in / not_in)
  if (MULTI_OPERATORS.includes(operator)) {
    const selected = Array.isArray(value)
      ? value
      : value
        ? String(value)
            .split(',')
            .map((s) => s.trim())
            .filter(Boolean)
        : [];

    if (hasPossibleValues) {
      const remainingOptions = allOptions.filter((opt) => !selected.includes(opt.value));
      const allSelected = remainingOptions.length === 0;

      return (
        <div className={className}>
          <SearchableSelect
            options={remainingOptions}
            value=""
            onValueChange={(v) => {
              if (v && !selected.includes(v)) onChange([...selected, v]);
            }}
            placeholder={allSelected ? 'All values selected' : 'Add value...'}
            searchPlaceholder="Search values..."
            emptyMessage="No values left."
            disabled={allSelected}
          />
          {selected.length > 0 && (
            <div className="mt-2 flex flex-wrap gap-1">
              {selected.map((item) => (
                <Badge key={item} variant="secondary" className="pr-1 pl-2">
                  <span>{item}</span>
                  <button
                    type="button"
                    onClick={() => onChange(selected.filter((s) => s !== item))}
                    className="ml-1 rounded-sm p-0.5 hover:bg-muted-foreground/20"
                    aria-label={`Remove ${item}`}
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

    // Fallback: free text comma-separated (legacy behavior)
    return (
      <Input
        placeholder="value1, value2, ..."
        value={selected.join(',')}
        onChange={(e) => onChange(e.target.value.split(',').map((s) => s.trim()))}
        className={className}
      />
    );
  }

  // Exact match operators (eq / neq): max 1 item
  if (hasPossibleValues) {
    const current = typeof value === 'string' ? value : Array.isArray(value) ? (value[0] ?? '') : '';

    return (
      <SearchableSelect
        options={allOptions}
        value={current}
        onValueChange={(v) => onChange(v)}
        placeholder="Select value..."
        searchPlaceholder="Search values..."
        emptyMessage="No values found."
        className={className}
      />
    );
  }

  // Fallback: free text input
  return (
    <Input placeholder="value" value={typeof value === 'string' ? value : ''} onChange={(e) => onChange(e.target.value)} className={className} />
  );
}

import { FieldCombobox } from '@/components/ping-post/field-combobox';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { EligibilityRule } from '@/types/ping-post';
import { Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';

const OPERATORS = [
  { value: 'eq', label: 'equals' },
  { value: 'neq', label: 'not equals' },
  { value: 'gt', label: 'greater than' },
  { value: 'gte', label: 'greater than or equal' },
  { value: 'lt', label: 'less than' },
  { value: 'lte', label: 'less than or equal' },
  { value: 'in', label: 'in (comma-separated)' },
  { value: 'not_in', label: 'not in (comma-separated)' },
  { value: 'is_empty', label: 'is empty' },
  { value: 'is_not_empty', label: 'is not empty' },
];

const VALUE_LESS_OPERATORS = ['is_empty', 'is_not_empty'];

interface Props {
  rules: EligibilityRule[];
  onChange: (rules: EligibilityRule[]) => void;
  fields?: { id: number; name: string }[];
}

export function EligibilityRuleEditor({ rules, onChange, fields = [] }: Props) {
  const usedFields = useMemo(() => rules.map((r) => r.field).filter(Boolean), [rules]);

  const addRule = () => {
    onChange([...rules, { field: '', operator: 'eq', value: '', sort_order: rules.length }]);
  };

  const removeRule = (index: number) => {
    onChange(rules.filter((_, i) => i !== index));
  };

  const updateRule = (index: number, key: keyof EligibilityRule, value: any) => {
    onChange(rules.map((r, i) => (i === index ? { ...r, [key]: value } : r)));
  };

  return (
    <div className="space-y-2">
      {rules.map((rule, i) => (
        <div key={i} className="flex items-center gap-2">
          {fields.length > 0 ? (
            <FieldCombobox
              value={rule.field}
              onChange={(v) => updateRule(i, 'field', v)}
              fields={fields}
              usedFields={usedFields}
              placeholder="Select field..."
              className="w-36"
            />
          ) : (
            <Input placeholder="field (e.g. state)" value={rule.field} onChange={(e) => updateRule(i, 'field', e.target.value)} className="w-36" />
          )}
          <Select
            value={rule.operator}
            onValueChange={(v) => {
              updateRule(i, 'operator', v);
              if (VALUE_LESS_OPERATORS.includes(v)) updateRule(i, 'value', null);
            }}
          >
            <SelectTrigger className="w-44">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {OPERATORS.map((op) => (
                <SelectItem key={op.value} value={op.value}>
                  {op.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {!VALUE_LESS_OPERATORS.includes(rule.operator) && (
            <Input
              placeholder="value"
              value={Array.isArray(rule.value) ? rule.value.join(',') : (rule.value as string)}
              onChange={(e) => {
                const v = e.target.value;
                const isMulti = rule.operator === 'in' || rule.operator === 'not_in';
                updateRule(i, 'value', isMulti ? v.split(',').map((s) => s.trim()) : v);
              }}
              className="flex-1"
            />
          )}
          <Button variant="ghost" size="icon" onClick={() => removeRule(i)} className="shrink-0 text-destructive">
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
      ))}
      <Button variant="outline" size="sm" onClick={addRule} type="button">
        <Plus className="mr-1 h-4 w-4" />
        Add Rule
      </Button>
    </div>
  );
}

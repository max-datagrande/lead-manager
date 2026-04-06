import { FieldCombobox } from '@/components/ping-post/field-combobox';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { Plus, Trash2 } from 'lucide-react';

const OPERATORS = [
  { value: 'eq', label: 'equals' },
  { value: 'neq', label: 'not equals' },
  { value: 'gt', label: 'greater than' },
  { value: 'gte', label: 'greater than or equal' },
  { value: 'lt', label: 'less than' },
  { value: 'lte', label: 'less than or equal' },
  { value: 'in', label: 'in (comma-separated)' },
  { value: 'not_in', label: 'not in (comma-separated)' },
];

export interface PricingCondition {
  field: string;
  op: string;
  value: string | string[];
}

export interface ConditionalPricingRule {
  conditions: PricingCondition[];
  price: number | string;
}

interface Props {
  rules: ConditionalPricingRule[];
  onChange: (rules: ConditionalPricingRule[]) => void;
  fields?: { id: number; name: string }[];
}

function emptyCondition(): PricingCondition {
  return { field: '', op: 'eq', value: '' };
}

function emptyRule(): ConditionalPricingRule {
  return { conditions: [emptyCondition()], price: '' };
}

export function ConditionalPricingEditor({ rules, onChange, fields = [] }: Props) {
  const addRule = () => {
    onChange([...rules, emptyRule()]);
  };

  const removeRule = (ruleIndex: number) => {
    onChange(rules.filter((_, i) => i !== ruleIndex));
  };

  const updateRule = (ruleIndex: number, patch: Partial<ConditionalPricingRule>) => {
    onChange(rules.map((r, i) => (i === ruleIndex ? { ...r, ...patch } : r)));
  };

  const addCondition = (ruleIndex: number) => {
    const rule = rules[ruleIndex];
    updateRule(ruleIndex, { conditions: [...rule.conditions, emptyCondition()] });
  };

  const removeCondition = (ruleIndex: number, condIndex: number) => {
    const rule = rules[ruleIndex];
    updateRule(ruleIndex, { conditions: rule.conditions.filter((_, i) => i !== condIndex) });
  };

  const updateCondition = (ruleIndex: number, condIndex: number, key: keyof PricingCondition, value: any) => {
    const rule = rules[ruleIndex];
    const updated = rule.conditions.map((c, i) => (i === condIndex ? { ...c, [key]: value } : c));
    updateRule(ruleIndex, { conditions: updated });
  };

  if (rules.length === 0) {
    return (
      <div className="space-y-3">
        <div className="rounded-lg border border-dashed p-6 text-center">
          <p className="text-sm text-muted-foreground">No rules yet. The first matching rule will determine the price.</p>
        </div>
        <Button variant="outline" size="sm" onClick={addRule} type="button">
          <Plus className="mr-1 h-4 w-4" />
          Add Rule
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {rules.map((rule, ruleIndex) => (
        <div key={ruleIndex} className={cn('rounded-lg border bg-muted/20 p-4 dark:bg-muted/10')}>
          {/* Rule header */}
          <div className="mb-3 flex items-center justify-between">
            <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Rule {ruleIndex + 1}</span>
            <Button
              variant="ghost"
              size="icon"
              type="button"
              onClick={() => removeRule(ruleIndex)}
              className="h-7 w-7 text-destructive hover:bg-destructive/10 hover:text-destructive"
              aria-label={`Remove rule ${ruleIndex + 1}`}
            >
              <Trash2 className="h-3.5 w-3.5" />
            </Button>
          </div>

          {/* Conditions */}
          <div className="space-y-2">
            {rule.conditions.map((cond, condIndex) => {
              const usedFieldsInRule = rule.conditions.map((c) => c.field).filter(Boolean);
              return (
                <div key={condIndex} className="flex items-center gap-2">
                  {condIndex === 0 ? (
                    <span className="w-6 shrink-0 text-center text-xs font-medium text-muted-foreground">IF</span>
                  ) : (
                    <span className="w-6 shrink-0 text-center text-xs font-medium text-primary">AND</span>
                  )}
                  {fields.length > 0 ? (
                    <FieldCombobox
                      value={cond.field}
                      onChange={(v) => updateCondition(ruleIndex, condIndex, 'field', v)}
                      fields={fields}
                      usedFields={usedFieldsInRule}
                      placeholder="Select field..."
                      className="w-48"
                    />
                  ) : (
                    <Input
                      placeholder="field (e.g. state)"
                      value={cond.field}
                      onChange={(e) => updateCondition(ruleIndex, condIndex, 'field', e.target.value)}
                      className="w-48"
                    />
                  )}
                  <Select value={cond.op} onValueChange={(v) => updateCondition(ruleIndex, condIndex, 'op', v)}>
                    <SelectTrigger className="w-60">
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
                  <Input
                    placeholder="value"
                    value={Array.isArray(cond.value) ? cond.value.join(',') : (cond.value as string)}
                    onChange={(e) => {
                      const v = e.target.value;
                      const isMulti = cond.op === 'in' || cond.op === 'not_in';
                      updateCondition(ruleIndex, condIndex, 'value', isMulti ? v.split(',').map((s) => s.trim()) : v);
                    }}
                    className="flex-1"
                  />
                  <Button
                    variant="ghost"
                    size="icon"
                    type="button"
                    onClick={() => removeCondition(ruleIndex, condIndex)}
                    disabled={rule.conditions.length === 1}
                    className="h-8 w-8 shrink-0 text-muted-foreground hover:text-destructive disabled:opacity-30"
                    aria-label="Remove condition"
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                  </Button>
                </div>
              );
            })}
          </div>

          {/* Add condition + price row */}
          <div className="mt-3 flex items-center justify-between gap-4 border-t pt-3">
            <Button
              variant="ghost"
              size="sm"
              type="button"
              onClick={() => addCondition(ruleIndex)}
              className="h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
            >
              <Plus className="mr-1 h-3 w-3" />
              Add condition
            </Button>

            <div className="flex shrink-0 items-center gap-2">
              <span className="text-xs font-medium text-muted-foreground">THEN price</span>
              <div className="relative">
                <span className="pointer-events-none absolute inset-y-0 left-2.5 flex items-center text-xs text-muted-foreground">$</span>
                <Input
                  type="number"
                  min={0}
                  step="0.01"
                  placeholder="0.00"
                  value={rule.price}
                  onChange={(e) => updateRule(ruleIndex, { price: e.target.value })}
                  className="w-28 pl-6"
                />
              </div>
            </div>
          </div>
        </div>
      ))}

      <Button variant="outline" size="sm" onClick={addRule} type="button">
        <Plus className="mr-1 h-4 w-4" />
        Add Rule
      </Button>
    </div>
  );
}

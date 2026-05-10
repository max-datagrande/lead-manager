import { EligibilityValueInput } from '@/components/ping-post/eligibility-value-input';
import { FieldCombobox } from '@/components/ping-post/field-combobox';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
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
  { value: 'in', label: 'in (any of)' },
  { value: 'not_in', label: 'not in (none of)' },
  { value: 'is_empty', label: 'is empty' },
  { value: 'is_not_empty', label: 'is not empty' },
];

const VALUE_LESS_OPERATORS = ['is_empty', 'is_not_empty'];

interface FieldOption {
  id: number;
  name: string;
  label?: string;
  possible_values?: string[] | null;
}

interface Props {
  rules: EligibilityRule[];
  onChange: (rules: EligibilityRule[]) => void;
  fields?: FieldOption[];
}

interface Group {
  index: number;
  conditions: Array<{ rule: EligibilityRule; flatIndex: number }>;
}

function buildGroups(rules: EligibilityRule[]): Group[] {
  const map = new Map<number, Group>();
  rules.forEach((rule, flatIndex) => {
    const idx = rule.group_index ?? 0;
    if (!map.has(idx)) {
      map.set(idx, { index: idx, conditions: [] });
    }
    map.get(idx)!.conditions.push({ rule, flatIndex });
  });
  return [...map.values()].sort((a, b) => a.index - b.index);
}

function nextGroupIndex(rules: EligibilityRule[]): number {
  if (rules.length === 0) return 0;
  return Math.max(...rules.map((r) => r.group_index ?? 0)) + 1;
}

export function EligibilityRuleEditor({ rules, onChange, fields = [] }: Props) {
  const groups = useMemo(() => buildGroups(rules), [rules]);
  const showHeaders = groups.length > 1;

  const addRuleSet = () => {
    const newGroupIndex = nextGroupIndex(rules);
    onChange([...rules, { field: '', operator: 'eq', value: '', sort_order: rules.length, group_index: newGroupIndex }]);
  };

  const removeGroup = (groupIndex: number) => {
    onChange(rules.filter((r) => (r.group_index ?? 0) !== groupIndex));
  };

  const addCondition = (groupIndex: number) => {
    onChange([...rules, { field: '', operator: 'eq', value: '', sort_order: rules.length, group_index: groupIndex }]);
  };

  const removeCondition = (flatIndex: number) => {
    onChange(rules.filter((_, i) => i !== flatIndex));
  };

  const updateCondition = (flatIndex: number, patch: Partial<EligibilityRule>) => {
    onChange(rules.map((r, i) => (i === flatIndex ? { ...r, ...patch } : r)));
  };

  const fieldByName = (name: string): FieldOption | undefined => fields.find((f) => f.name === name);

  if (rules.length === 0) {
    return (
      <div className="space-y-3">
        <div className="rounded-lg border border-dashed p-6 text-center">
          <p className="text-sm text-muted-foreground">
            No rules yet. Add a rule set to start filtering leads. The lead is eligible when at least one rule set fully matches.
          </p>
        </div>
        <Button variant="outline" size="sm" onClick={addRuleSet} type="button">
          <Plus className="mr-1 h-4 w-4" />
          Add Rule Set
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {groups.map((group, groupOrder) => (
        <div key={group.index}>
          {groupOrder > 0 && (
            <div className="my-2 flex items-center gap-3">
              <div className="h-px flex-1 bg-border" />
              <span className="text-xs font-semibold tracking-wide text-primary uppercase">OR</span>
              <div className="h-px flex-1 bg-border" />
            </div>
          )}
          <div className={cn('rounded-lg border bg-muted/20 p-4 dark:bg-muted/10')}>
            {showHeaders && (
              <div className="mb-3 flex items-center justify-between">
                <span className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Rule Set {groupOrder + 1}</span>
                <Button
                  variant="ghost"
                  size="icon"
                  type="button"
                  onClick={() => removeGroup(group.index)}
                  className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                  aria-label={`Remove rule set ${groupOrder + 1}`}
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </Button>
              </div>
            )}

            <div className="space-y-2">
              {group.conditions.map(({ rule, flatIndex }, condIndex) => {
                const usedFieldsInGroup = group.conditions.map((c) => c.rule.field).filter(Boolean);
                const fieldOption = fieldByName(rule.field);

                return (
                  <div key={flatIndex} className="flex items-start gap-2">
                    {condIndex === 0 ? (
                      <span className="mt-2 w-6 shrink-0 text-center text-xs font-medium text-muted-foreground">IF</span>
                    ) : (
                      <span className="mt-2 w-6 shrink-0 text-center text-xs font-medium text-primary">AND</span>
                    )}
                    {fields.length > 0 ? (
                      <FieldCombobox
                        value={rule.field}
                        onChange={(v) => updateCondition(flatIndex, { field: v, value: '' })}
                        fields={fields}
                        usedFields={usedFieldsInGroup}
                        placeholder="Select field..."
                        className="w-48"
                      />
                    ) : (
                      <input
                        placeholder="field"
                        value={rule.field}
                        onChange={(e) => updateCondition(flatIndex, { field: e.target.value })}
                        className="h-9 w-48 rounded-md border bg-background px-3 text-sm"
                      />
                    )}
                    <Select
                      value={rule.operator}
                      onValueChange={(v) => {
                        if (VALUE_LESS_OPERATORS.includes(v)) {
                          updateCondition(flatIndex, { operator: v as EligibilityRule['operator'], value: null });
                        } else {
                          updateCondition(flatIndex, { operator: v as EligibilityRule['operator'] });
                        }
                      }}
                    >
                      <SelectTrigger className="w-52 shrink-0">
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
                    <div className="flex-1">
                      <EligibilityValueInput
                        operator={rule.operator}
                        value={rule.value}
                        onChange={(v) => updateCondition(flatIndex, { value: v })}
                        possibleValues={fieldOption?.possible_values ?? null}
                      />
                    </div>
                    <Button
                      variant="ghost"
                      size="icon"
                      type="button"
                      onClick={() => removeCondition(flatIndex)}
                      className="h-9 w-9 shrink-0 text-muted-foreground hover:text-destructive"
                      aria-label="Remove condition"
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                );
              })}
            </div>

            <div className="mt-3 flex items-center justify-start border-t pt-3">
              <Button
                variant="ghost"
                size="sm"
                type="button"
                onClick={() => addCondition(group.index)}
                className="h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
              >
                <Plus className="mr-1 h-3 w-3" />
                Add condition
              </Button>
            </div>
          </div>
        </div>
      ))}

      <Button variant="outline" size="sm" onClick={addRuleSet} type="button">
        <Plus className="mr-1 h-4 w-4" />
        Add Rule Set
      </Button>
    </div>
  );
}

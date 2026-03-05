import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { useMemo } from 'react';
import type { FieldConfig } from '../constants';
import { US_STATES } from '../constants';
import CptypeSelector from './cptype-selector';

interface TestFormProps {
  fields: FieldConfig[];
  cptypeField: FieldConfig | null;
  stateField: FieldConfig | null;
  fieldValues: Record<string, string>;
  setFieldValues: React.Dispatch<React.SetStateAction<Record<string, string>>>;
  selectedCptypes: string[];
  setSelectedCptypes: React.Dispatch<React.SetStateAction<string[]>>;
}

export default function TestForm({
  fields,
  cptypeField,
  stateField,
  fieldValues,
  setFieldValues,
  selectedCptypes,
  setSelectedCptypes,
}: TestFormProps) {
  const handleFieldChange = (token: string, value: string) => {
    setFieldValues((prev) => ({ ...prev, [token]: value }));
  };

  // Sort fields alphabetically by label
  const sortedFields = useMemo(() => [...fields].sort((a, b) => a.label.localeCompare(b.label)), [fields]);

  return (
    <Card>
      <CardHeader>
        <CardTitle>Test Parameters</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
          {/* CpType field — always first */}
          {cptypeField && cptypeField.possible_values.length > 0 && (
            <div className="space-y-1.5">
              <Label>{cptypeField.label}</Label>
              <div>
                <CptypeSelector
                  label="Select cptypes"
                  options={cptypeField.possible_values}
                  selected={selectedCptypes}
                  onChange={setSelectedCptypes}
                />
              </div>
            </div>
          )}

          {/* State field as searchable select */}
          {stateField && (
            <div className="space-y-1.5">
              <Label>{stateField.label}</Label>
              <SearchableSelect
                options={US_STATES}
                value={fieldValues['state'] || ''}
                onValueChange={(value) => handleFieldChange('state', value)}
                placeholder="Select a state"
                searchPlaceholder="Search states..."
              />
            </div>
          )}

          {/* Regular fields sorted alphabetically */}
          {sortedFields.map((field) => (
            <div key={field.token} className="space-y-1.5">
              <Label htmlFor={field.token}>{field.label}</Label>
              <Input
                id={field.token}
                list={`${field.token}-options`}
                value={fieldValues[field.token] || ''}
                onChange={(e) => handleFieldChange(field.token, e.target.value)}
                placeholder={field.default_value || `Enter ${field.label.toLowerCase()}`}
              />
              {field.possible_values.length > 0 && (
                <datalist id={`${field.token}-options`}>
                  {field.possible_values.map((v) => (
                    <option key={v} value={v} />
                  ))}
                </datalist>
              )}
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}

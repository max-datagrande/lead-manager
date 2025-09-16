import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { v4 as uuidv4 } from 'uuid';

export const KeyValueList = ({
  initialValues = [],
  onChange,
  keyDatalist = [],
  valueDatalist = [],
  keyPlaceholder = 'Key',
  valuePlaceholder = 'Value',
}) => {
  // Add a unique ID to initial values for React key prop, if they don't have one
  const [pairs, setPairs] = useState(() => initialValues.map((p) => ({ ...p, id: p.id || uuidv4() })));

  useEffect(() => {
    // Inform the parent component of changes, stripping the internal ID
    onChange(pairs.map(({ id, ...rest }) => rest));
  }, [pairs]);

  const addPair = () => {
    setPairs([...pairs, { id: uuidv4(), key: '', value: '' }]);
  };

  const removePair = (id) => {
    setPairs(pairs.filter((p) => p.id !== id));
  };

  const updatePair = (id, field, fieldValue) => {
    setPairs(pairs.map((p) => (p.id === id ? { ...p, [field]: fieldValue } : p)));
  };

  return (
    <div className="w-full space-y-2">
      {pairs.map((pair, index) => (
        <div key={pair.id} className="group flex items-center gap-2">
          <Input
            type="text"
            list={`datalist-keys-${index}`}
            value={pair.key}
            onChange={(e) => updatePair(pair.id, 'key', e.target.value)}
            placeholder={keyPlaceholder}
          />
          {keyDatalist.length > 0 && (
            <datalist id={`datalist-keys-${index}`}>
              {keyDatalist.map((opt) => (
                <option key={opt} value={opt} />
              ))}
            </datalist>
          )}

          <Input
            type="text"
            list={`datalist-values-${index}`}
            value={pair.value}
            onChange={(e) => updatePair(pair.id, 'value', e.target.value)}
            placeholder={valuePlaceholder}
          />
          {valueDatalist.length > 0 && (
            <datalist id={`datalist-values-${index}`}>
              {valueDatalist.map((opt) => (
                <option key={opt} value={opt} />
              ))}
            </datalist>
          )}

          <Button
            type="button"
            variant="ghost"
            size="icon"
            className="text-destructive opacity-0 group-hover:opacity-100"
            onClick={() => removePair(pair.id)}
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
      ))}
      <Button type="button" variant="outline" size="sm" onClick={addPair}>
        <Plus className="mr-2 h-4 w-4" />
        Add Header
      </Button>
    </div>
  );
};

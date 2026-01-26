import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { v4 as uuidv4 } from 'uuid';
/*  // Comparar los valores reales en lugar de referencias de objetos
    const currentValues = pairs.map(({ id, ...rest }) => rest);
    const initialValues = initialPairs.map(({ id, ...rest }) => rest);

    // Solo llamar onChange si los valores realmente han cambiado
    if (JSON.stringify(currentValues) !== JSON.stringify(initialValues)) {
      onChange(currentValues);
    } */
export const KeyValueList = ({
  initialValues = [],
  onChange,
  keyDatalist = [],
  valueDatalist = [],
  keyPlaceholder = 'Key',
  valuePlaceholder = 'Value',
  addButtonText = null,
  textOnTooltip = false,
  label = null,
}) => {
  const initialPairs = initialValues.map((p) => ({ ...p, id: p.id || uuidv4() }));
  // Add a unique ID to initial values for React key prop, if they don't have one
  const [pairs, setPairs] = useState(initialPairs);

  useEffect(() => {
    const timeoutId = setTimeout(() => {
      if (initialPairs === pairs) {
        return;
      }
      onChange(pairs.map(({ id, ...rest }) => rest));
    }, 300);
    return () => clearTimeout(timeoutId);
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
      <div className="flex justify-between gap-2">
        {label && (
          <Label className="flex items-center gap-2" htmlFor="key">
            {label}
          </Label>
        )}
        <AddButton handleClick={addPair} addButtonText={addButtonText} textOnTooltip={textOnTooltip} />
      </div>
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
            variant="secondary"
            className="transition-colors duration-200 hover:bg-destructive hover:text-white text-gray-400"
            size="sm"
            onClick={() => removePair(pair.id)}
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
      ))}
    </div>
  );
};

function AddButton({ handleClick, addButtonText, textOnTooltip = false }) {
  if (!textOnTooltip || !addButtonText) {
    return (
      <Button type="button" variant="black" size="sm" onClick={handleClick}>
        <Plus className="h-4 w-4" />
        {addButtonText && addButtonText}
      </Button>
    );
  }
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button type="button" variant="black" size="sm" onClick={handleClick}>
          <Plus className="h-4 w-4" />
        </Button>
      </TooltipTrigger>
      <TooltipContent className="bg-black text-white" arrowClassName="bg-black fill-black" side='left'>
        <p>{addButtonText}</p>
      </TooltipContent>
    </Tooltip>
  );
}

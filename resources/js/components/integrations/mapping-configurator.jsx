import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { ArrowRightLeft, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { ValueMappingModal } from './value-mapping-modal';

export function MappingConfigurator({ parsers = {}, onParserChange, fields = [], onRemoveToken }) {
  const tokens = Object.keys(parsers);
  const timer = useRef(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedTokenData, setSelectedTokenData] = useState(null);

  if (tokens.length === 0) {
    return null; // Don't render anything if no tokens are present
  }

  const handleInputValue = (e) => {
    const value = e.target.value;
    const token = e.target.dataset.token;
    if (timer.current) {
      clearTimeout(timer.current);
    }
    timer.current = setTimeout(() => {
      onParserChange(token, 'defaultValue', value);
    }, 500);
  };

  const handleOpenModal = (token, field) => {
    setSelectedTokenData({
      token,
      possible_values: field.possible_values,
      value_mapping: parsers[token]?.value_mapping || {},
    });
    setIsModalOpen(true);
  };

  return (
    <>
      {tokens.map((token) => {
        const field = fields.find((f) => f.name === token);
        const hasPossibleValues = field && field.possible_values && field.possible_values.length > 0;
        return (
          <div key={token} className="grid grid-cols-3 items-end gap-4">
            <div className="space-y-2">
              <Label>Token</Label>
              <p className="rounded-md bg-slate-100 p-2 font-mono text-sm">{`{${token}}`}</p>
            </div>
            <div>
              <Label htmlFor={`parser-type-${token}`}>Data Type</Label>
              <Select
                id={`parser-type-${token}`}
                value={parsers[token]?.dataType}
                onValueChange={(value) => onParserChange(token, 'dataType', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Select a type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="string">String</SelectItem>
                  <SelectItem value="integer">Integer</SelectItem>
                  <SelectItem value="boolean">Boolean</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor={`parser-default-${token}`}>Default Value</Label>
              <div className="flex items-center gap-2">
                <Input
                  id={`parser-default-${token}`}
                  data-token={token}
                  defaultValue={parsers[token]?.defaultValue}
                  onChange={handleInputValue}
                  placeholder="(optional)"
                />
                {hasPossibleValues && (
                  <TooltipProvider>
                    <Tooltip>
                      <TooltipTrigger asChild>
                        <Button type="button" variant="black" size="sm" onClick={() => handleOpenModal(token, field)}>
                          <ArrowRightLeft className="h-4 w-4" />
                        </Button>
                      </TooltipTrigger>
                      <TooltipContent>
                        <p>Map Values</p>
                      </TooltipContent>
                    </Tooltip>
                  </TooltipProvider>
                )}
                <div className="flex justify-end">
                  <Button type="button" variant="destructive" size="sm" onClick={() => onRemoveToken?.(token)}>
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>
          </div>
        );
      })}
      <ValueMappingModal isOpen={isModalOpen} onOpenChange={setIsModalOpen} tokenData={selectedTokenData} onSave={onParserChange} />
    </>
  );
}

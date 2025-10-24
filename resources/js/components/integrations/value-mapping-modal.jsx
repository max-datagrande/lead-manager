import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useEffect, useState } from 'react';

export function ValueMappingModal({ isOpen, onOpenChange, tokenData, onSave }) {
  const [mapping, setMapping] = useState({});

  useEffect(() => {
    // Initialize mapping state when tokenData changes
    if (tokenData?.value_mapping) {
      setMapping(tokenData.value_mapping);
    } else {
      setMapping({});
    }
  }, [tokenData]);

  const handleMappingChange = (internalValue, externalValue) => {
    setMapping((prev) => ({ ...prev, [internalValue]: externalValue }));
  };

  const handleSave = () => {
    onSave(tokenData.token, 'value_mapping', mapping);
    onOpenChange(false);
  };

  if (!isOpen || !tokenData) {
    return null;
  }

  const { token, possible_values } = tokenData;

  return (
    <Dialog open={isOpen} onOpenChange={onOpenChange}>
      <DialogContent className="p-0 sm:max-w-[625px] gap-0">
        <DialogHeader className="p-4">
          <DialogTitle>Map Values for {`{${token}}`}</DialogTitle>
          <DialogDescription>Map the internal values from your system to the corresponding values expected by the external API.</DialogDescription>
        </DialogHeader>
        <section className="no-scrollbar max-h-[80vh] overflow-auto border-t border-b p-4">
          <div className="grid grid-cols-[auto_1fr] items-center gap-4">
            {possible_values.map((internalValue) => (
              <div key={internalValue} className="contents">
                <Label htmlFor={`map-${internalValue}`} className="text-right">
                  {internalValue}
                </Label>
                <Input
                  id={`map-${internalValue}`}
                  value={mapping[internalValue] || ''}
                  onChange={(e) => handleMappingChange(internalValue, e.target.value)}
                  className="col-span-1"
                />
              </div>
            ))}
          </div>
        </section>
        <DialogFooter className="p-4">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" onClick={handleSave}>
            Save Changes
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

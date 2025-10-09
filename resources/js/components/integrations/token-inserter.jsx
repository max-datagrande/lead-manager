import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export function TokenInserter({ fields = [], onTokenSelect }) {
  return (
    <div className="flex justify-end w-full">
      <Select onValueChange={(value) => onTokenSelect(value)}>
        <SelectTrigger className="w-full mt-1">
          <SelectValue placeholder="Insert field as token..." />
        </SelectTrigger>
        <SelectContent>
          {fields.map((field) => (
            <SelectItem key={field.id} value={field.name}>
              {field.name}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}

import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export function TokenInserter({ fields = [], onTokenSelect }) {
    return (
        <div className="mb-2">
            <Select onValueChange={(value) => onTokenSelect(value)}>
                <SelectTrigger className="w-[280px]">
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

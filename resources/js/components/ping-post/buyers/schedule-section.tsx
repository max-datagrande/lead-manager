import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/ui/searchable-select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import type { ScheduleWindow, TimezoneOption } from '@/types/ping-post';
import { Plus, Trash2 } from 'lucide-react';

/* Constants */
const DAYS: Array<{ value: number; short: string; label: string }> = [
  { value: 0, short: 'S', label: 'Sunday' },
  { value: 1, short: 'M', label: 'Monday' },
  { value: 2, short: 'T', label: 'Tuesday' },
  { value: 3, short: 'W', label: 'Wednesday' },
  { value: 4, short: 'T', label: 'Thursday' },
  { value: 5, short: 'F', label: 'Friday' },
  { value: 6, short: 'S', label: 'Saturday' },
];

/* Helper functions */
function renderTimezone(tz: TimezoneOption) {
  return (
    <div className="flex w-full items-center justify-between gap-2">
      <span className="truncate">
        {tz.name}
        {tz.offset && <span className="ml-1 text-xs text-muted-foreground">({tz.offset})</span>}
      </span>
      {tz.description && (
        <Badge variant="muted" className="shrink-0">
          {tz.description}
        </Badge>
      )}
    </div>
  );
}

function emptyWindow(sortOrder: number): ScheduleWindow {
  return {
    days_of_week: [],
    start_time: '09:00',
    end_time: '17:00',
    sort_order: sortOrder,
  };
}

// `<input type="time">` expects HH:MM, but the DB returns HH:MM:SS.
function trimSeconds(value: string): string {
  return value.length > 5 ? value.slice(0, 5) : value;
}

/* Props */
interface Props {
  windows: ScheduleWindow[];
  timezone: string;
  timezones: TimezoneOption[];
  errors: Record<string, string>;
  onWindowsChange: (windows: ScheduleWindow[]) => void;
  onTimezoneChange: (tz: string) => void;
}



export function ScheduleSection({ windows, timezone, timezones, errors, onWindowsChange, onTimezoneChange }: Props) {
  const updateWindow = (index: number, patch: Partial<ScheduleWindow>) => {
    onWindowsChange(windows.map((w, i) => (i === index ? { ...w, ...patch } : w)));
  };

  const addWindow = () => {
    onWindowsChange([...windows, emptyWindow(windows.length)]);
  };

  const removeWindow = (index: number) => {
    onWindowsChange(windows.filter((_, i) => i !== index).map((w, i) => ({ ...w, sort_order: i })));
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-1.5">
        <Label htmlFor="schedule_timezone">Timezone</Label>
        <SearchableSelect
          options={timezones}
          value={timezone}
          onValueChange={onTimezoneChange}
          placeholder="Select timezone..."
          searchPlaceholder="Search timezone..."
          emptyMessage="No timezones match."
          renderOption={renderTimezone}
        />
        <p className="text-xs text-muted-foreground">Applies to all schedule windows below.</p>
      </div>

      <div className="space-y-3">
        {windows.length === 0 ? (
          <p className="rounded-md border border-dashed bg-muted/30 px-3 py-4 text-center text-sm text-muted-foreground">
            No schedule configured — buyer can receive leads 24/7.
          </p>
        ) : (
          windows.map((window, index) => {
            const daysError = errors[`schedule_windows.${index}.days_of_week`];
            const startError = errors[`schedule_windows.${index}.start_time`];
            const endError = errors[`schedule_windows.${index}.end_time`];
            const rowError = daysError ?? startError ?? endError;

            return (
              <div key={index} className="space-y-2 rounded-lg border p-3">
                <div className="flex flex-col gap-3 md:flex-row md:items-end">
                  <div className="flex-1 space-y-1.5">
                    <Label className="text-xs text-muted-foreground uppercase">Days</Label>
                    <ToggleGroup
                      type="multiple"
                      variant="outline"
                      value={window.days_of_week.map(String)}
                      onValueChange={(values) => updateWindow(index, { days_of_week: values.map(Number).sort((a, b) => a - b) })}
                    >
                      <TooltipProvider delayDuration={300}>
                        {DAYS.map((d) => (
                          <Tooltip key={d.value}>
                            <TooltipTrigger asChild>
                              <span className="inline-flex">
                                <ToggleGroupItem
                                  value={String(d.value)}
                                  aria-label={d.label}
                                  className="aspect-square h-9 w-9 data-[state=on]:bg-primary data-[state=on]:text-primary-foreground data-[state=on]:hover:bg-primary/90 data-[state=on]:hover:text-primary-foreground"
                                >
                                  {d.short}
                                </ToggleGroupItem>
                              </span>
                            </TooltipTrigger>
                            <TooltipContent>{d.label}</TooltipContent>
                          </Tooltip>
                        ))}
                      </TooltipProvider>
                    </ToggleGroup>
                  </div>

                  <div className="flex gap-2">
                    <div className="space-y-1.5">
                      <Label htmlFor={`schedule_start_${index}`} className="text-xs text-muted-foreground uppercase">
                        Start
                      </Label>
                      <Input
                        id={`schedule_start_${index}`}
                        type="time"
                        value={trimSeconds(window.start_time)}
                        onChange={(e) => updateWindow(index, { start_time: e.target.value })}
                        className="w-fit pr-1 cursor-pointer"
                      />
                    </div>
                    <div className="space-y-1.5">
                      <Label htmlFor={`schedule_end_${index}`} className="text-xs text-muted-foreground uppercase">
                        End
                      </Label>
                      <Input
                        id={`schedule_end_${index}`}
                        type="time"
                        value={trimSeconds(window.end_time)}
                        onChange={(e) => updateWindow(index, { end_time: e.target.value })}
                        className="w-fit pr-1 cursor-pointer"
                      />
                    </div>
                  </div>

                  <Button type="button" variant="ghost" size="icon" onClick={() => removeWindow(index)} aria-label="Remove window">
                    <Trash2 className="h-4 w-4 text-destructive" />
                  </Button>
                </div>

                {rowError && <p className="text-xs text-destructive">{rowError}</p>}
              </div>
            );
          })
        )}

        <Button type="button" variant="outline" size="sm" onClick={addWindow} className="gap-1">
          <Plus className="h-4 w-4" />
          Add window
        </Button>
      </div>
    </div>
  );
}

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { CapRule } from '@/types/ping-post'
import { Plus, Trash2 } from 'lucide-react'

const PERIODS = [
  { value: 'day', label: 'Daily' },
  { value: 'week', label: 'Weekly' },
  { value: 'month', label: 'Monthly' },
  { value: 'year', label: 'Yearly' },
]

interface Props {
  caps: CapRule[]
  onChange: (caps: CapRule[]) => void
}

export function CapRuleEditor({ caps, onChange }: Props) {
  const addCap = () => {
    onChange([...caps, { period: 'day', max_leads: null, max_revenue: null }])
  }

  const removeCap = (index: number) => {
    onChange(caps.filter((_, i) => i !== index))
  }

  const updateCap = (index: number, key: keyof CapRule, value: any) => {
    onChange(caps.map((c, i) => (i === index ? { ...c, [key]: value } : c)))
  }

  return (
    <div className="space-y-3">
      {caps.map((cap, i) => (
        <div key={i} className="flex items-end gap-3 rounded-md border p-3">
          <div className="space-y-1">
            <Label className="text-xs">Period</Label>
            <Select value={cap.period} onValueChange={(v) => updateCap(i, 'period', v as CapRule['period'])}>
              <SelectTrigger className="w-28">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {PERIODS.map((p) => (
                  <SelectItem key={p.value} value={p.value}>
                    {p.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1">
            <Label className="text-xs">Max Leads</Label>
            <Input
              type="number"
              min={0}
              placeholder="∞"
              value={cap.max_leads ?? ''}
              onChange={(e) => updateCap(i, 'max_leads', e.target.value === '' ? null : parseInt(e.target.value))}
              className="w-24"
            />
          </div>
          <div className="space-y-1">
            <Label className="text-xs">Max Revenue ($)</Label>
            <Input
              type="number"
              min={0}
              step="0.01"
              placeholder="∞"
              value={cap.max_revenue ?? ''}
              onChange={(e) => updateCap(i, 'max_revenue', e.target.value === '' ? null : parseFloat(e.target.value))}
              className="w-28"
            />
          </div>
          <Button variant="ghost" size="icon" onClick={() => removeCap(i)} type="button" className="mb-0.5 text-destructive">
            <Trash2 className="h-4 w-4" />
          </Button>
        </div>
      ))}
      <Button variant="outline" size="sm" onClick={addCap} type="button">
        <Plus className="mr-1 h-4 w-4" />
        Add Cap
      </Button>
    </div>
  )
}

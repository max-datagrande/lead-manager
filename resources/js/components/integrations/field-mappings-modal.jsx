import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useIntegrations } from '@/hooks/use-integrations'
import { Settings2 } from 'lucide-react'
import { useState } from 'react'

const DATA_TYPES = [
  { value: 'string', label: 'String' },
  { value: 'integer', label: 'Integer' },
  { value: 'float', label: 'Float' },
  { value: 'boolean', label: 'Boolean' },
]

/**
 * Global modal for editing field mapping config (data_type, default_value) for all
 * tokens currently in use across request body templates.
 *
 * @param {{ fields: Array<{ id: number, name: string, possible_values?: any }> }} props
 */
export function FieldMappingsModal({ fields = [] }) {
  const { data, updateFieldMapping } = useIntegrations()
  const [open, setOpen] = useState(false)

  const mappings = data.field_mappings ?? []
  const fieldById = (fieldId) => fields.find((f) => f.id === fieldId)

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button type="button" variant="outline" size="sm" className="gap-2">
          <Settings2 className="size-4" />
          Field Mappings{mappings.length > 0 ? ` (${mappings.length})` : ''}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>Field Mappings</DialogTitle>
        </DialogHeader>
        {mappings.length === 0 ? (
          <p className="py-8 text-center text-sm text-muted-foreground">
            No field tokens in use. Insert a field with{' '}
            <kbd className="rounded border px-1 font-mono text-xs">@</kbd> in a request body editor.
          </p>
        ) : (
          <div className="space-y-3">
            {mappings.map((mapping) => {
              const field = fieldById(mapping.field_id)
              const displayName = field?.name ?? `Field #${mapping.field_id}`
              return (
                <div key={mapping.field_id} className="rounded-lg border p-4 space-y-3">
                  <p className="text-sm font-semibold">{displayName}</p>
                  <div className="grid grid-cols-2 gap-3">
                    <div className="space-y-1.5">
                      <Label className="text-xs">Data Type</Label>
                      <Select
                        value={mapping.data_type ?? 'string'}
                        onValueChange={(val) => updateFieldMapping(mapping.field_id, { data_type: val })}
                      >
                        <SelectTrigger className="h-8 text-sm">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          {DATA_TYPES.map((dt) => (
                            <SelectItem key={dt.value} value={dt.value}>
                              {dt.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                    <div className="space-y-1.5">
                      <Label className="text-xs">Default Value</Label>
                      <Input
                        className="h-8 text-sm"
                        placeholder="(empty)"
                        value={mapping.default_value ?? ''}
                        onChange={(e) =>
                          updateFieldMapping(mapping.field_id, { default_value: e.target.value || null })
                        }
                      />
                    </div>
                  </div>
                </div>
              )
            })}
          </div>
        )}
      </DialogContent>
    </Dialog>
  )
}

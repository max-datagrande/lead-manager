import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { useDebouncedFunction } from '@/hooks/use-debounce'
import { useIntegrations } from '@/hooks/use-integrations'
import { Settings2 } from 'lucide-react'
import { useEffect, useState } from 'react'

/**
 * Isolated textarea with local display state + debounced draft update.
 * Validity check (border color) also lives here — no parent re-renders while typing.
 */
function RawJsonTextarea({ initialValue, rowCount, onChange }) {
  const [text, setText] = useState(initialValue ?? '{}')
  const [isValid, setIsValid] = useState(true)
  const debouncedOnChange = useDebouncedFunction(onChange, 250)

  useEffect(() => {
    setText(initialValue ?? '{}')
  }, [initialValue])

  const handleChange = (e) => {
    const newText = e.target.value
    setText(newText)
    try {
      const parsed = JSON.parse(newText)
      if (typeof parsed === 'object' && parsed !== null && !Array.isArray(parsed)) {
        setIsValid(true)
        debouncedOnChange(parsed)
      } else {
        setIsValid(false)
      }
    } catch {
      setIsValid(false)
    }
  }

  return (
    <textarea
      className={`w-full rounded-md border bg-background px-3 py-2 font-mono text-xs outline-none transition-colors focus:ring-1 ${
        isValid ? 'border-border focus:ring-ring' : 'border-destructive focus:ring-destructive'
      }`}
      rows={rowCount}
      value={text}
      onChange={handleChange}
      spellCheck={false}
    />
  )
}

/**
 * Isolated input with local display state + debounced draft update.
 * Prevents parent re-renders on every keystroke.
 */
function ValueMappingInput({ value: externalValue, onChange }) {
  const [value, setValue] = useState(externalValue ?? '')
  const debouncedOnChange = useDebouncedFunction(onChange, 250)

  // Sync when external value changes (e.g. raw JSON mode overwrites it)
  useEffect(() => {
    setValue(externalValue ?? '')
  }, [externalValue])

  return (
    <Input
      className="h-7 text-xs"
      placeholder="mapped value"
      value={value}
      onChange={(e) => {
        setValue(e.target.value)
        debouncedOnChange(e.target.value)
      }}
    />
  )
}

const DATA_TYPES = [
  { value: 'string', label: 'String' },
  { value: 'integer', label: 'Integer' },
  { value: 'float', label: 'Float' },
  { value: 'boolean', label: 'Boolean' },
]

/**
 * Global modal for editing field mapping config for all tokens currently in use
 * across request body templates.
 *
 * Draft-based: all edits are local until "Save" — avoids re-rendering the page
 * (and CodeMirror editors) on every keystroke.
 *
 * @param {{ fields: Array<{ id: number, name: string, label?: string, possible_values?: string[] }> }} props
 */
export function FieldMappingsModal({ fields = [] }) {
  const { data, updateFieldMapping } = useIntegrations()
  const [open, setOpen] = useState(false)

  // ── Local draft state (isolated from parent) ──────────────────────────────
  const [draft, setDraft] = useState([])
  const [expandedMappings, setExpandedMappings] = useState({})
  const [rawModes, setRawModes] = useState({})

  const fieldById = (fieldId) => fields.find((f) => f.id === fieldId)

  // ── Dialog lifecycle ──────────────────────────────────────────────────────
  const handleOpenChange = (next) => {
    if (next) {
      // Deep-copy current mappings into draft on open
      setDraft((data.field_mappings ?? []).map((m) => ({ ...m })))
      setExpandedMappings({})
      setRawModes({})
    }
    setOpen(next)
  }

  const handleSave = () => {
    draft.forEach((m) => {
      updateFieldMapping(m.field_id, {
        data_type: m.data_type,
        default_value: m.default_value,
        value_mapping: m.value_mapping,
      })
    })
    setOpen(false)
  }

  // ── Draft helpers ─────────────────────────────────────────────────────────
  const updateDraft = (fieldId, patch) => {
    setDraft((prev) => prev.map((m) => (m.field_id === fieldId ? { ...m, ...patch } : m)))
  }

  const toggleExpand = (fieldId) => {
    setExpandedMappings((prev) => ({ ...prev, [fieldId]: !prev[fieldId] }))
  }

  const toggleRawMode = (fieldId) => {
    setRawModes((prev) => ({ ...prev, [fieldId]: !prev[fieldId] }))
  }

  // Called by RawJsonTextarea only when the JSON is valid (already parsed)
  const handleRawChange = (fieldId, parsed) => {
    updateDraft(fieldId, { value_mapping: Object.keys(parsed).length > 0 ? parsed : null })
  }

  const handleValueMappingChange = (fieldId, rawValue, mappedValue) => {
    const mapping = draft.find((m) => m.field_id === fieldId)
    const current = mapping?.value_mapping ?? {}
    const updated = { ...current }
    if (mappedValue) {
      updated[rawValue] = mappedValue
    } else {
      delete updated[rawValue]
    }
    updateDraft(fieldId, { value_mapping: Object.keys(updated).length > 0 ? updated : null })
  }

  // Badge count uses data (not draft) so the trigger button stays accurate before opening
  const totalMappings = (data.field_mappings ?? []).length

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogTrigger asChild>
        <Button type="button" variant="outline" size="sm" className="gap-2">
          <Settings2 className="size-4" />
          Field Mappings{totalMappings > 0 ? ` (${totalMappings})` : ''}
        </Button>
      </DialogTrigger>

      <DialogContent className="flex max-h-[85vh] max-w-none flex-col overflow-hidden sm:max-w-5xl">
        <DialogHeader className="shrink-0">
          <DialogTitle>Field Mappings</DialogTitle>
        </DialogHeader>

        {draft.length === 0 ? (
          <p className="py-8 text-center text-sm text-muted-foreground">
            No field tokens in use. Insert a field with{' '}
            <kbd className="rounded border px-1 font-mono text-xs">@</kbd> in a request body editor.
          </p>
        ) : (
          <div className="min-h-0 flex-1 overflow-y-auto">
            {/* Table header — Field | Value Map | Data Type | Default Value */}
            <div className="sticky top-0 z-10 grid grid-cols-[1.5fr_0.5fr_1fr_1fr] items-center gap-3 border-b bg-muted px-4 py-2 text-xs font-medium text-muted-foreground">
              <span>Field</span>
              <span>Value Map</span>
              <span>Data Type</span>
              <span>Default Value</span>
            </div>

            <div className="divide-y">
              {draft.map((mapping) => {
                const field = fieldById(mapping.field_id)
                const label = field?.label ?? field?.name ?? `Field #${mapping.field_id}`
                const technicalName = field?.name ?? null
                const possibleValues = Array.isArray(field?.possible_values)
                  ? field.possible_values.filter(Boolean)
                  : []
                const mappedCount = mapping.value_mapping ? Object.keys(mapping.value_mapping).length : 0
                const isExpanded = expandedMappings[mapping.field_id] ?? false
                const isRaw = rawModes[mapping.field_id] ?? false

                return (
                  <div key={mapping.field_id}>
                    <div className="grid grid-cols-[1.5fr_0.5fr_1fr_1fr] items-center gap-3 px-4 py-3">
                      {/* Field name */}
                      <div className="min-w-0">
                        <p className="truncate text-sm font-medium">{label}</p>
                        {technicalName && label !== technicalName && (
                          <p className="font-mono text-xs text-muted-foreground">{technicalName}</p>
                        )}
                      </div>

                      {/* Value map badge — right after field name */}
                      <div>
                        {possibleValues.length > 0 && (
                          <button
                            type="button"
                            onClick={() => toggleExpand(mapping.field_id)}
                            className="flex items-center gap-1"
                          >
                            <Badge
                              variant={mappedCount > 0 ? 'secondary' : 'outline'}
                              className="text-xs transition-colors hover:bg-accent"
                            >
                              {mappedCount > 0 ? `${mappedCount} mapped` : 'No map'}
                            </Badge>
                          </button>
                        )}
                      </div>

                      {/* Data Type */}
                      <Select
                        value={mapping.data_type ?? 'string'}
                        onValueChange={(val) => updateDraft(mapping.field_id, { data_type: val })}
                      >
                        <SelectTrigger className="h-8 text-xs">
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          {DATA_TYPES.map((dt) => (
                            <SelectItem key={dt.value} value={dt.value} className="text-xs">
                              {dt.label}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>

                      {/* Default Value */}
                      <Input
                        className="h-8 text-xs"
                        placeholder="(empty)"
                        value={mapping.default_value ?? ''}
                        onChange={(e) => updateDraft(mapping.field_id, { default_value: e.target.value || null })}
                      />
                    </div>

                    {/* Expandable value mapping section — animated */}
                    {possibleValues.length > 0 && isExpanded && (
                      <div className="animate-in fade-in slide-in-from-top-2 duration-150 border-t bg-muted/30 px-4 py-3">
                        <div className="mb-3 flex items-center justify-between">
                          <Label className="text-xs text-muted-foreground">Value Mapping</Label>
                          <div className="flex items-center gap-2">
                            <span className="text-xs text-muted-foreground">Raw JSON</span>
                            <Switch
                              checked={isRaw}
                              onCheckedChange={() => toggleRawMode(mapping.field_id)}
                              className="scale-75"
                            />
                          </div>
                        </div>

                        {isRaw ? (
                          <RawJsonTextarea
                            initialValue={JSON.stringify(mapping.value_mapping ?? {}, null, 2)}
                            rowCount={Math.min(Object.keys(mapping.value_mapping ?? {}).length + 3, 12)}
                            onChange={(parsed) => handleRawChange(mapping.field_id, parsed)}
                          />
                        ) : (
                          <div className="grid grid-cols-2 gap-x-6 gap-y-1.5">
                            {possibleValues.map((rawValue) => (
                              <div key={rawValue} className="flex items-center gap-2">
                                <span className="w-24 shrink-0 font-mono text-xs text-muted-foreground">{rawValue}</span>
                                <span className="text-xs text-muted-foreground">→</span>
                                <ValueMappingInput
                                  value={mapping.value_mapping?.[rawValue] ?? ''}
                                  onChange={(val) => handleValueMappingChange(mapping.field_id, rawValue, val)}
                                />
                              </div>
                            ))}
                          </div>
                        )}
                      </div>
                    )}
                  </div>
                )
              })}
            </div>
          </div>
        )}

        <DialogFooter className="shrink-0 border-t pt-4">
          <Button type="button" variant="ghost" onClick={() => setOpen(false)}>
            Cancel
          </Button>
          <Button type="button" onClick={handleSave}>
            Save changes
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

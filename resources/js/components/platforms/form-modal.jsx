import { Button } from '@/components/ui/button'
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useCurrentModalId, useModal } from '@/hooks/use-modal'
import { useForm } from '@inertiajs/react'
import { Plus, Trash2 } from 'lucide-react'

export default function FormModal({ entry, companies = [], internalTokens = [], isEdit = false }) {
  const modal = useModal()
  const modalId = useCurrentModalId()

  const initialMappings = entry?.token_mappings && Object.keys(entry.token_mappings).length
    ? Object.entries(entry.token_mappings).map(([external, internal]) => ({ external, internal }))
    : [{ external: '', internal: '' }]

  const { data, setData, post, put, processing, errors, reset, transform } = useForm({
    name: entry?.name ?? '',
    company_id: entry?.company_id ?? '',
    token_mappings: initialMappings,
  })

  transform((formData) => {
    const mappingsObj = {}
    formData.token_mappings.forEach(({ external, internal }) => {
      if (external.trim() && internal) {
        mappingsObj[external.trim()] = internal
      }
    })
    return { ...formData, token_mappings: mappingsObj }
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    const url = isEdit ? route('platforms.update', entry.id) : route('platforms.store')
    const options = {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        modal.resolve(modalId, true)
        reset()
      },
    }

    if (isEdit) {
      put(url, options)
    } else {
      post(url, options)
    }
  }

  const addMapping = () => setData('token_mappings', [...data.token_mappings, { external: '', internal: '' }])

  const removeMapping = (index) => setData('token_mappings', data.token_mappings.filter((_, i) => i !== index))

  const updateMapping = (index, field, value) =>
    setData(
      'token_mappings',
      data.token_mappings.map((m, i) => (i === index ? { ...m, [field]: value } : m)),
    )

  return (
    <>
      <DialogHeader>
        <DialogTitle>{isEdit ? 'Edit Platform' : 'Create Platform'}</DialogTitle>
        <DialogDescription>{isEdit ? 'Update the platform details' : 'Add a new platform'}</DialogDescription>
      </DialogHeader>

      <form onSubmit={handleSubmit} className="space-y-4">
        {/* Name */}
        <div className="space-y-2">
          <Label htmlFor="name">Name</Label>
          <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. ClickFlare" />
          {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
        </div>

        {/* Company */}
        <div className="space-y-2">
          <Label>Company</Label>
          <SearchableSelect
            options={companies.map((c) => ({ value: String(c.id), label: c.name }))}
            value={data.company_id ? String(data.company_id) : ''}
            onValueChange={(val) => setData('company_id', val ? Number(val) : '')}
            placeholder="Select company (optional)"
          />
          {errors.company_id && <p className="text-sm text-destructive">{errors.company_id}</p>}
        </div>

        {/* Token Mappings */}
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <Label>Token Mappings</Label>
            <Button type="button" variant="outline" size="sm" onClick={addMapping}>
              <Plus className="mr-1 h-3 w-3" />
              Add Mapping
            </Button>
          </div>
          {data.token_mappings.length > 0 && (
            <div className="grid grid-cols-[1fr_1fr_auto] gap-x-2 gap-y-1 pb-1">
              <span className="text-xs font-medium text-muted-foreground">External Token</span>
              <span className="text-xs font-medium text-muted-foreground">Internal Token</span>
              <span className="w-9" />
            </div>
          )}
          {data.token_mappings.map((mapping, index) => (
            <div key={index} className="grid grid-cols-[1fr_1fr_auto] gap-2">
              <Input
                value={mapping.external}
                onChange={(e) => updateMapping(index, 'external', e.target.value)}
                placeholder="e.g. Cost, Callid"
                className="font-mono text-xs"
              />
              <Select value={mapping.internal} onValueChange={(val) => updateMapping(index, 'internal', val)}>
                <SelectTrigger className="text-xs">
                  <SelectValue placeholder="Select token" />
                </SelectTrigger>
                <SelectContent>
                  {internalTokens.map((token) => (
                    <SelectItem key={token.value} value={token.value}>
                      {token.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {data.token_mappings.length > 1 && (
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  className="shrink-0 text-gray-400 hover:bg-destructive hover:text-white"
                  onClick={() => removeMapping(index)}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              )}
            </div>
          ))}
          {errors.token_mappings && <p className="text-sm text-destructive">{errors.token_mappings}</p>}
        </div>

        <div className="flex justify-end gap-2 pt-4">
          <Button type="button" variant="outline" onClick={() => { modal.resolve(modalId, false); reset() }} disabled={processing}>
            Cancel
          </Button>
          <Button type="submit" disabled={processing}>
            {processing ? 'Saving...' : isEdit ? 'Update' : 'Create'}
          </Button>
        </div>
      </form>
    </>
  )
}

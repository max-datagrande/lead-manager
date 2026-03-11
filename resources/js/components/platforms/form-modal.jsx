import { Button } from '@/components/ui/button'
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { useCurrentModalId, useModal } from '@/hooks/use-modal'
import { useForm } from '@inertiajs/react'
import { Plus, Trash2 } from 'lucide-react'

export default function FormModal({ entry, companies = [], isEdit = false }) {
  const modal = useModal()
  const modalId = useCurrentModalId()

  const { data, setData, post, put, processing, errors, reset } = useForm({
    name: entry?.name ?? '',
    company_id: entry?.company_id ?? '',
    tokens: entry?.tokens?.length ? entry.tokens : [''],
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    const tokens = data.tokens.filter((t) => t.trim() !== '')
    const payload = { ...data, tokens }

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
      put(url, { ...options, data: payload })
    } else {
      post(url, { ...options, data: payload })
    }
  }

  const addToken = () => setData('tokens', [...data.tokens, ''])

  const removeToken = (index) => setData('tokens', data.tokens.filter((_, i) => i !== index))

  const updateToken = (index, value) =>
    setData(
      'tokens',
      data.tokens.map((t, i) => (i === index ? value : t)),
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

        {/* Tokens */}
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <Label>Tokens</Label>
            <Button type="button" variant="outline" size="sm" onClick={addToken}>
              <Plus className="mr-1 h-3 w-3" />
              Add Token
            </Button>
          </div>
          {data.tokens.map((token, index) => (
            <div key={index} className="flex gap-2">
              <Input
                value={token}
                onChange={(e) => updateToken(index, e.target.value)}
                placeholder="e.g. cost, lead_id, click_id"
              />
              {data.tokens.length > 1 && (
                <Button
                  type="button"
                  variant="secondary"
                  size="sm"
                  className="shrink-0 text-gray-400 hover:bg-destructive hover:text-white"
                  onClick={() => removeToken(index)}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              )}
            </div>
          ))}
          {errors.tokens && <p className="text-sm text-destructive">{errors.tokens}</p>}
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

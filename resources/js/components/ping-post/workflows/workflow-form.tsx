import { WorkflowBuyerList } from '@/components/ping-post/workflow-buyer-list'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { FieldHint } from '@/components/ui/field-hint'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { useWorkflows } from '@/hooks/use-workflows'
import { cn } from '@/lib/utils'
import { Link } from '@inertiajs/react'
import { type LucideIcon, Clock, Cpu, Layers, ListOrdered, Zap } from 'lucide-react'
import { route } from 'ziggy-js'

const STRATEGY_META: Record<string, { icon: LucideIcon; description: string }> = {
  best_bid: { icon: Zap, description: 'Ping all buyers in parallel. Highest bid wins.' },
  waterfall: { icon: ListOrdered, description: 'Try buyers sequentially. Post to first that accepts.' },
  combined: { icon: Layers, description: 'Best Bid primary group with Waterfall fallback.' },
}

const EXEC_MODE_OPTIONS = [
  { value: 'sync', label: 'Synchronous', icon: Clock, description: 'API waits for result' },
  { value: 'async', label: 'Asynchronous', icon: Cpu, description: 'Fire and forget (202)' },
]

interface ToggleRowProps {
  id: string
  checked: boolean
  onCheckedChange: (v: boolean) => void
  title: string
  description: string
  hint?: string
}

function ToggleRow({ id, checked, onCheckedChange, title, description, hint }: ToggleRowProps) {
  return (
    <div
      className={cn(
        'flex items-center justify-between rounded-lg border px-4 py-3 transition-colors',
        checked ? 'border-primary/30 bg-primary/5' : 'bg-muted/20',
      )}
    >
      <div className="space-y-0.5 pr-4">
        <p className="flex items-center gap-1 text-sm font-medium">
          {title}
          {hint && <FieldHint text={hint} side="top" />}
        </p>
        <p className="text-xs text-muted-foreground">{description}</p>
      </div>
      <Switch id={id} checked={checked} onCheckedChange={onCheckedChange} />
    </div>
  )
}

export function WorkflowForm() {
  const { isEdit, data, errors, processing, handleSubmit, setData, availableBuyers, strategies } = useWorkflows()

  const isCombined = data.strategy === 'combined'
  const isWaterfall = data.strategy === 'waterfall'
  const showCascadeRules = data.strategy === 'best_bid' || isCombined
  const showWaterfallRules = isWaterfall || isCombined

  return (
    <form onSubmit={handleSubmit} className="space-y-6">

      {/* ── Workflow Settings ─────────────────────────────────────────────── */}
      <Card>
        <CardHeader className="flex-row items-start justify-between space-y-0 pb-4">
          <div>
            <CardTitle>Workflow Settings</CardTitle>
            <CardDescription className="mt-1">Name, timeout and execution mode.</CardDescription>
          </div>
          <div className="flex items-center gap-2">
            <Switch id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v)} />
            <Label htmlFor="is_active" className="cursor-pointer">
              {data.is_active
                ? <Badge className="bg-emerald-500 hover:bg-emerald-500 text-white">Active</Badge>
                : <Badge variant="secondary">Inactive</Badge>
              }
            </Label>
            <FieldHint text="Un workflow inactivo no puede recibir leads via API. Los dispatches en curso no se ven afectados." />
          </div>
        </CardHeader>
        <CardContent className="space-y-5">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div className="space-y-2 md:col-span-2">
              <Label htmlFor="name">Name</Label>
              <Input
                id="name"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                placeholder="e.g. Main Lead Distribution"
              />
              {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
            </div>

            <div className="space-y-2">
              <Label htmlFor="global_timeout_ms">
                Global Timeout
                <FieldHint text="Tiempo máximo total para que el workflow complete. Debe ser mayor que la suma de los timeouts de ping + post de los buyers." />
              </Label>
              <div className="relative">
                <Input
                  id="global_timeout_ms"
                  type="number"
                  min={500}
                  value={data.global_timeout_ms}
                  onChange={(e) => setData('global_timeout_ms', parseInt(e.target.value))}
                  className="pr-9"
                />
                <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-muted-foreground">
                  ms
                </span>
              </div>
            </div>
          </div>

          <div className="space-y-2">
            <Label>
              Execution Mode
              <FieldHint items={[
                { label: 'Synchronous', description: 'La API espera a que el dispatch complete y devuelve el resultado inmediatamente. Útil cuando el caller necesita saber si el lead fue vendido.' },
                { label: 'Asynchronous', description: 'La API responde 202 de inmediato y el dispatch corre en background via queue. Usar cuando la latencia del workflow sería inaceptable para el caller.' },
              ]} />
            </Label>
            <div className="grid grid-cols-2 gap-3">
              {EXEC_MODE_OPTIONS.map(({ value, label, icon: Icon, description }) => (
                <button
                  key={value}
                  type="button"
                  onClick={() => setData('execution_mode', value)}
                  className={cn(
                    'flex items-center gap-3 rounded-lg border p-3 text-left transition-all hover:bg-muted/30',
                    data.execution_mode === value
                      ? 'border-primary bg-primary/5 ring-1 ring-primary'
                      : 'border-border',
                  )}
                >
                  <Icon className={cn('h-4 w-4 shrink-0', data.execution_mode === value ? 'text-primary' : 'text-muted-foreground')} />
                  <div>
                    <p className={cn('text-sm font-medium leading-none', data.execution_mode === value ? 'text-primary' : '')}>{label}</p>
                    <p className="mt-1 text-xs text-muted-foreground">{description}</p>
                  </div>
                </button>
              ))}
            </div>
          </div>
        </CardContent>
      </Card>

      {/* ── Distribution Strategy ─────────────────────────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle>Distribution Strategy</CardTitle>
          <CardDescription>Define how leads are distributed to buyers.</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
            {strategies.map((s) => {
              const meta = STRATEGY_META[s.value]
              const Icon = meta?.icon
              const isSelected = data.strategy === s.value
              return (
                <button
                  key={s.value}
                  type="button"
                  onClick={() => setData('strategy', s.value)}
                  className={cn(
                    'flex flex-col gap-3 rounded-xl border p-4 text-left transition-all hover:bg-muted/30',
                    isSelected ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border',
                  )}
                >
                  <div className="flex items-center justify-between">
                    {Icon && (
                      <Icon className={cn('h-5 w-5', isSelected ? 'text-primary' : 'text-muted-foreground')} />
                    )}
                    {isSelected && <div className="h-2 w-2 rounded-full bg-primary" />}
                  </div>
                  <div>
                    <p className={cn('text-sm font-semibold', isSelected ? 'text-primary' : '')}>{s.label}</p>
                    {meta && (
                      <p className="mt-0.5 text-xs leading-snug text-muted-foreground">{meta.description}</p>
                    )}
                  </div>
                </button>
              )
            })}
          </div>
        </CardContent>
      </Card>

      {/* ── Cascade & Advance Rules ───────────────────────────────────────── */}
      {(showCascadeRules || showWaterfallRules) && (
        <Card>
          <CardHeader>
            <CardTitle>Cascade & Advance Rules</CardTitle>
            <CardDescription>Control how the workflow reacts to buyer responses.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            {showCascadeRules && (
              <>
                <ToggleRow
                  id="cascade_on_post_rejection"
                  checked={data.cascade_on_post_rejection}
                  onCheckedChange={(v) => setData('cascade_on_post_rejection', v)}
                  title="Cascade on Post Rejection"
                  description="Try the next buyer if the winner rejects the post"
                  hint="Si el buyer ganador del ping rechaza el post, el sistema intenta el siguiente en el ranking de bids. Si está desactivado, un rechazo en el post termina el dispatch sin venta."
                />
                <div className="flex items-center justify-between rounded-lg border bg-muted/20 px-4 py-3">
                  <div className="space-y-0.5">
                    <Label htmlFor="cascade_max_retries" className="text-sm font-medium">
                      Max Cascade Retries
                      <FieldHint text="Número máximo de buyers a intentar en cascada después del primer rechazo. Con valor 2: ganador + 2 más = 3 buyers en total. Evita iterar toda la lista si la mayoría rechaza." />
                    </Label>
                    <p className="text-xs text-muted-foreground">Max buyers to try after the first rejection</p>
                  </div>
                  <Input
                    id="cascade_max_retries"
                    type="number"
                    min={1}
                    max={10}
                    className="w-20 text-center"
                    value={data.cascade_max_retries}
                    onChange={(e) => setData('cascade_max_retries', parseInt(e.target.value))}
                  />
                </div>
              </>
            )}

            {showWaterfallRules && (
              <>
                <ToggleRow
                  id="advance_on_rejection"
                  checked={data.advance_on_rejection}
                  onCheckedChange={(v) => setData('advance_on_rejection', v)}
                  title="Advance on Rejection"
                  description="Continue to the next buyer if the current one rejects"
                  hint="Si un buyer devuelve una respuesta de rechazo explícita (accepted_path = false), el workflow continúa con el siguiente en la lista. Si está desactivado, el primer rechazo detiene el dispatch."
                />
                <ToggleRow
                  id="advance_on_timeout"
                  checked={data.advance_on_timeout}
                  onCheckedChange={(v) => setData('advance_on_timeout', v)}
                  title="Advance on Timeout"
                  description="Continue to the next buyer if no response within timeout"
                  hint="Si un buyer no responde dentro del timeout configurado, el workflow continúa con el siguiente. Útil cuando algunos buyers son lentos pero otros pueden aceptar el lead."
                />
                <ToggleRow
                  id="advance_on_error"
                  checked={data.advance_on_error}
                  onCheckedChange={(v) => setData('advance_on_error', v)}
                  title="Advance on Error"
                  description="Continue to the next buyer if current returns an HTTP error"
                  hint="Si un buyer devuelve un error HTTP (5xx, red caída, etc.), el workflow continúa en lugar de detenerse. Recomendado activarlo para mayor resiliencia."
                />
              </>
            )}
          </CardContent>
        </Card>
      )}

      {/* ── Buyers ───────────────────────────────────────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle>Buyers</CardTitle>
          <CardDescription>
            {isCombined
              ? 'Primary group runs Best Bid; Secondary group is a Waterfall fallback.'
              : 'Drag to reorder. Mark a buyer as Fallback to use it as last resort.'}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <WorkflowBuyerList
            buyers={data.buyers}
            availableBuyers={availableBuyers}
            strategy={data.strategy}
            onChange={(buyers) => setData('buyers', buyers)}
          />
          {errors.buyers && <p className="mt-2 text-sm text-destructive">{errors.buyers}</p>}
        </CardContent>
      </Card>

      {/* ── Actions ──────────────────────────────────────────────────────── */}
      <div className="flex items-center justify-end gap-3">
        <Button variant="outline" asChild>
          <Link href={route('ping-post.workflows.index')}>Cancel</Link>
        </Button>
        <Button type="submit" disabled={processing} className="min-w-28">
          {processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Workflow'}
        </Button>
      </div>
    </form>
  )
}

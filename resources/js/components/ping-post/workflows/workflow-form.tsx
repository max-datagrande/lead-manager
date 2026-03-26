import { WorkflowBuyerList } from '@/components/ping-post/workflow-buyer-list'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { FieldHint } from '@/components/ui/field-hint'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { useWorkflows } from '@/hooks/use-workflows'
import { Link } from '@inertiajs/react'
import { route } from 'ziggy-js'

export function WorkflowForm() {
  const { isEdit, data, errors, processing, handleSubmit, setData, availableBuyers, strategies } = useWorkflows()

  const isCombined = data.strategy === 'combined'
  const isWaterfall = data.strategy === 'waterfall'

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {/* Basic Info */}
      <Card>
        <CardHeader>
          <CardTitle>Workflow Settings</CardTitle>
        </CardHeader>
        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-3">
          <div className="space-y-2 md:col-span-2">
            <Label htmlFor="name">Name</Label>
            <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. Main Lead Distribution" />
            {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
          </div>

          <div className="space-y-2">
            <Label>
              Strategy
              <FieldHint items={[
                { label: 'Best Bid',  description: 'Hace ping a todos los buyers en paralelo, ordena por precio y postea al mayor postor. Si rechaza, puede cascadear al siguiente.' },
                { label: 'Waterfall', description: 'Itera los buyers en orden de posición. Postea al primero que acepte. Ideal para prioridad fija.' },
                { label: 'Combined',  description: 'Grupo primario corre Best Bid. Si no vende, el grupo secundario corre como Waterfall de respaldo.' },
              ]} />
            </Label>
            <Select value={data.strategy} onValueChange={(v) => setData('strategy', v)}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {strategies.map((s) => (
                  <SelectItem key={s.value} value={s.value}>
                    {s.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label>
              Execution Mode
              <FieldHint items={[
                { label: 'Synchronous',  description: 'La API espera a que el dispatch complete y devuelve el resultado inmediatamente. Útil cuando el caller necesita saber si el lead fue vendido.' },
                { label: 'Asynchronous', description: 'La API responde 202 de inmediato y el dispatch corre en background via queue. Usar cuando la latencia del workflow sería inaceptable para el caller.' },
              ]} />
            </Label>
            <Select value={data.execution_mode} onValueChange={(v) => setData('execution_mode', v)}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="sync">Synchronous</SelectItem>
                <SelectItem value="async">Asynchronous</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label htmlFor="global_timeout_ms">
              Global Timeout (ms)
              <FieldHint text="Tiempo máximo total para que el workflow complete. Si el proceso supera este límite, el dispatch se marca como timeout. Debe ser mayor que la suma de los timeouts individuales de ping + post de los buyers." />
            </Label>
            <Input
              id="global_timeout_ms"
              type="number"
              min={500}
              value={data.global_timeout_ms}
              onChange={(e) => setData('global_timeout_ms', parseInt(e.target.value))}
            />
          </div>

          <div className="col-span-full flex items-center gap-2">
            <Switch id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v)} />
            <Label htmlFor="is_active">
              Active
              <FieldHint text="Un workflow inactivo no puede recibir leads via API. Los dispatches en curso no se ven afectados." />
            </Label>
          </div>
        </CardContent>
      </Card>

      {/* Strategy-specific settings */}
      <Card>
        <CardHeader>
          <CardTitle>Cascade & Advance Rules</CardTitle>
          <CardDescription>Control how the workflow reacts to buyer responses.</CardDescription>
        </CardHeader>
        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
          {(data.strategy === 'best_bid' || isCombined) && (
            <>
              <div className="flex items-center justify-between rounded border px-4 py-3">
                <div>
                  <p className="text-sm font-medium">
                    Cascade on Post Rejection
                    <FieldHint text="Si el buyer ganador del ping rechaza el post (respuesta negativa), el sistema intenta el siguiente en el ranking de bids. Si está desactivado, un rechazo en el post termina el dispatch sin venta." side="top" />
                  </p>
                  <p className="text-xs text-muted-foreground">Intenta el siguiente buyer si el ganador rechaza el post</p>
                </div>
                <Switch
                  checked={data.cascade_on_post_rejection}
                  onCheckedChange={(v) => setData('cascade_on_post_rejection', v)}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="cascade_max_retries">
                  Max Cascade Retries
                  <FieldHint text="Número máximo de buyers a intentar en cascada después del primer rechazo. Ejemplo: con valor 2, se intenta el ganador + 2 más = 3 buyers en total. Evita iterar toda la lista si la mayoría rechaza." />
                </Label>
                <Input
                  id="cascade_max_retries"
                  type="number"
                  min={1}
                  max={10}
                  value={data.cascade_max_retries}
                  onChange={(e) => setData('cascade_max_retries', parseInt(e.target.value))}
                />
              </div>
            </>
          )}

          {(isWaterfall || isCombined) && (
            <>
              <div className="flex items-center justify-between rounded border px-4 py-3">
                <div>
                  <p className="text-sm font-medium">
                    Advance on Rejection
                    <FieldHint text="Si un buyer devuelve una respuesta de rechazo explícita (accepted_path = false), el workflow continúa con el siguiente en la lista. Si está desactivado, el primer rechazo detiene el dispatch." side="top" />
                  </p>
                  <p className="text-xs text-muted-foreground">Continúa al siguiente buyer si el actual rechaza</p>
                </div>
                <Switch
                  checked={data.advance_on_rejection}
                  onCheckedChange={(v) => setData('advance_on_rejection', v)}
                />
              </div>
              <div className="flex items-center justify-between rounded border px-4 py-3">
                <div>
                  <p className="text-sm font-medium">
                    Advance on Timeout
                    <FieldHint text="Si un buyer no responde dentro del timeout configurado, el workflow continúa con el siguiente. Útil cuando algunos buyers son lentos pero otros pueden aceptar el lead." side="top" />
                  </p>
                  <p className="text-xs text-muted-foreground">Continúa al siguiente buyer si el actual no responde a tiempo</p>
                </div>
                <Switch
                  checked={data.advance_on_timeout}
                  onCheckedChange={(v) => setData('advance_on_timeout', v)}
                />
              </div>
              <div className="flex items-center justify-between rounded border px-4 py-3">
                <div>
                  <p className="text-sm font-medium">
                    Advance on Error
                    <FieldHint text="Si un buyer devuelve un error HTTP (5xx, red caída, etc.), el workflow continúa con el siguiente en lugar de detenerse. Recomendado activarlo para mayor resiliencia." side="top" />
                  </p>
                  <p className="text-xs text-muted-foreground">Continúa al siguiente buyer si el actual devuelve un error</p>
                </div>
                <Switch
                  checked={data.advance_on_error}
                  onCheckedChange={(v) => setData('advance_on_error', v)}
                />
              </div>
            </>
          )}
        </CardContent>
      </Card>

      {/* Buyers */}
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

      {/* Actions */}
      <div className="flex items-center justify-end gap-3">
        <Button variant="outline" asChild>
          <Link href={route('ping-post.workflows.index')}>Cancel</Link>
        </Button>
        <Button type="submit" disabled={processing}>
          {processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Workflow'}
        </Button>
      </div>
    </form>
  )
}

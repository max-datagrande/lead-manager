import { CapRuleEditor } from '@/components/ping-post/cap-rule-editor'
import { EligibilityRuleEditor } from '@/components/ping-post/eligibility-rule-editor'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { FieldHint } from '@/components/ui/field-hint'
import { useBuyers } from '@/hooks/use-buyers'
import type { Integration } from '@/types/ping-post'
import { Link } from '@inertiajs/react'
import { route } from 'ziggy-js'

interface Props {
  integrations?: Integration[]
  pricingTypes?: Array<{ value: string; label: string }>
  companies?: Array<{ id: number; name: string }>
}

export function BuyerForm({ integrations = [], pricingTypes = [], companies = [] }: Props) {
  const { isEdit, data, errors, processing, handleSubmit, setData } = useBuyers()

  const selectedIntegration = integrations.find((i) => i.id === data.integration_id) ?? null
  const isPingPost = selectedIntegration?.type === 'ping-post'

  return (
    <form onSubmit={handleSubmit} className="space-y-6">

      {/* ── Buyer Info ─────────────────────────────────────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle>Buyer Info</CardTitle>
          <CardDescription>
            Identifica al buyer y vinculalo a una integración existente. La integración define
            los endpoints a los que se enviarán los leads.
          </CardDescription>
        </CardHeader>
        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">

          <div className="space-y-2">
            <Label htmlFor="name">
              Name
              <FieldHint text="Nombre interno para identificar a este buyer en el sistema. Puede ser el nombre del cliente o empresa que compra los leads." />
            </Label>
            <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="e.g. Acme Leads Buyer" />
            {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
          </div>

          <div className="space-y-2">
            <Label htmlFor="integration_id">
              Integration
              <FieldHint text="La integración define los endpoints (URLs) configurados para este buyer. Ping-post = el buyer recibe primero un ping con datos parciales y oferta un precio antes de recibir el lead completo. Post-only = el lead se envía directamente sin fase de ping previa. Cada integración solo puede tener un buyer asignado." />
            </Label>
            <Select
              value={data.integration_id ? String(data.integration_id) : ''}
              onValueChange={(v) => setData('integration_id', parseInt(v))}
              disabled={isEdit}
            >
              <SelectTrigger id="integration_id">
                <SelectValue placeholder="Select an integration" />
              </SelectTrigger>
              <SelectContent>
                {integrations.map((i) => (
                  <SelectItem key={i.id} value={String(i.id)}>
                    {i.name}
                    <span className="ml-2 text-xs text-muted-foreground">({i.type})</span>
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.integration_id && <p className="text-sm text-destructive">{errors.integration_id}</p>}
            {isEdit && <p className="text-xs text-muted-foreground">Integration cannot be changed after creation.</p>}
          </div>

          {companies.length > 0 && (
            <div className="space-y-2">
              <Label htmlFor="company_id">
                Company
                <FieldHint text="Asociación opcional con una empresa del sistema. Útil para agrupar buyers por cliente o cuenta." />
              </Label>
              <Select
                value={data.company_id ? String(data.company_id) : ''}
                onValueChange={(v) => setData('company_id', v ? parseInt(v) : null)}
              >
                <SelectTrigger id="company_id">
                  <SelectValue placeholder="Select company" />
                </SelectTrigger>
                <SelectContent>
                  {companies.map((c) => (
                    <SelectItem key={c.id} value={String(c.id)}>
                      {c.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          <div className="flex items-center gap-2 pt-2">
            <Switch id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v)} />
            <Label htmlFor="is_active">
              Active
              <FieldHint text="Un buyer inactivo es ignorado por todos los workflows y no recibe leads, aunque esté asignado. Útil para pausar temporalmente sin eliminarlo." />
            </Label>
          </div>

        </CardContent>
      </Card>

      {/* ── Timeouts ────────────────────────────────────────────────────────── */}
      {selectedIntegration && (
        <Card>
          <CardHeader>
            <CardTitle>Connection Timeouts</CardTitle>
            <CardDescription>
              Tiempo máximo de espera para cada fase. La configuración de response parsing (paths de
              aceptación, bid price, etc.) se gestiona en el formulario de la integración.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">

            {isPingPost && (
              <div className="space-y-2">
                <Label htmlFor="ping_timeout_ms">
                  Ping Timeout (ms)
                  <FieldHint text="Tiempo máximo en milisegundos que se espera la respuesta del ping. Si el buyer no responde a tiempo, es omitido en esta ronda. Se recomienda un valor bajo (1000–3000 ms) para no bloquear el workflow." />
                </Label>
                <Input
                  id="ping_timeout_ms"
                  type="number"
                  min={500}
                  placeholder="e.g. 3000  (recomendado: 1000–3000)"
                  value={data.ping_timeout_ms}
                  onChange={(e) => setData('ping_timeout_ms', e.target.value === '' ? '' : parseInt(e.target.value))}
                />
              </div>
            )}

            <div className="space-y-2">
              <Label htmlFor="post_timeout_ms">
                Post Timeout (ms)
                <FieldHint text="Tiempo máximo en milisegundos para esperar la respuesta del post. Puede ser más alto que el ping timeout ya que el lead completo se está transfiriendo. Valor recomendado: 5000–10000 ms." />
              </Label>
              <Input
                id="post_timeout_ms"
                type="number"
                min={500}
                placeholder="e.g. 5000  (recomendado: 5000–10000)"
                value={data.post_timeout_ms}
                onChange={(e) => setData('post_timeout_ms', e.target.value === '' ? '' : parseInt(e.target.value))}
              />
            </div>

          </CardContent>
        </Card>
      )}

      {/* ── Pricing ────────────────────────────────────────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle>Pricing</CardTitle>
          <CardDescription>
            Define cómo se determina el precio que se cobra o acepta por cada lead enviado a este buyer.
          </CardDescription>
        </CardHeader>
        <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-3">

          <div className="space-y-2">
            <Label>
              Pricing Type
              <FieldHint items={[
                { label: 'Fixed',       description: 'Se cobra un precio fijo por lead, independiente de lo que el buyer oferte en el ping.' },
                { label: 'Min Bid',     description: 'El buyer debe ofertar al menos el valor configurado. Bids menores son rechazados automáticamente.' },
                { label: 'Conditional', description: 'El precio varía según condiciones del lead (estado, edad, vertical, etc.). Se configuran reglas con condiciones y precio asociado; se aplica la primera regla que coincida.' },
                { label: 'Postback',    description: 'El precio final se confirma cuando el buyer envía un postback de conversión. Hasta entonces el lead queda pendiente.' },
              ]} />
            </Label>
            <Select value={data.pricing_type} onValueChange={(v) => setData('pricing_type', v)}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {pricingTypes.map((pt) => (
                  <SelectItem key={pt.value} value={pt.value}>
                    {pt.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {data.pricing_type === 'fixed' && (
            <div className="space-y-2">
              <Label htmlFor="fixed_price">
                Fixed Price ($)
                <FieldHint text="Precio en dólares que se registra por cada lead aceptado por este buyer, independientemente de lo que el buyer haya ofertado en el ping." />
              </Label>
              <Input
                id="fixed_price"
                type="number"
                min={0}
                step="0.01"
                value={data.fixed_price}
                onChange={(e) => setData('fixed_price', e.target.value)}
                placeholder="0.00"
              />
              {errors.fixed_price && <p className="text-sm text-destructive">{errors.fixed_price}</p>}
            </div>
          )}

          {data.pricing_type === 'min_bid' && (
            <div className="space-y-2">
              <Label htmlFor="min_bid">
                Min Bid ($)
                <FieldHint text="El buyer solo recibirá el lead si su bid en el ping es mayor o igual a este valor. Bids por debajo del mínimo son automáticamente rechazados." />
              </Label>
              <Input
                id="min_bid"
                type="number"
                min={0}
                step="0.01"
                value={data.min_bid}
                onChange={(e) => setData('min_bid', e.target.value)}
                placeholder="0.00"
              />
              {errors.min_bid && <p className="text-sm text-destructive">{errors.min_bid}</p>}
            </div>
          )}

          {data.pricing_type === 'postback' && (
            <div className="space-y-2">
              <Label htmlFor="postback_pending_days">
                Postback Window (days)
                <FieldHint text="Días que el sistema espera que el buyer envíe el postback de confirmación con el precio final. Si no llega dentro de este plazo, el lead se marca automáticamente como no vendido. Máximo 90 días." />
              </Label>
              <Input
                id="postback_pending_days"
                type="number"
                min={1}
                max={90}
                placeholder="e.g. 15  (máx. 90 días)"
                value={data.postback_pending_days}
                onChange={(e) => setData('postback_pending_days', e.target.value === '' ? '' : parseInt(e.target.value))}
              />
            </div>
          )}

        </CardContent>
      </Card>

      {/* ── Eligibility Rules ──────────────────────────────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle>Eligibility Rules</CardTitle>
          <CardDescription>
            Filtros que determinan si un lead puede ser enviado a este buyer. El lead debe cumplir
            <strong> todas</strong> las reglas para ser elegible. Si no cumple alguna, el buyer es omitido
            sin contar como rechazo. Ejemplo: solo leads de CA y TX, con edad ≥ 25.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <EligibilityRuleEditor rules={data.eligibility_rules} onChange={(rules) => setData('eligibility_rules', rules)} />
        </CardContent>
      </Card>

      {/* ── Cap Rules ──────────────────────────────────────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle>Volume Caps</CardTitle>
          <CardDescription>
            Límites de volumen para este buyer. Cuando se alcanza un cap, el buyer es omitido
            automáticamente hasta que el período se reinicie. Puedes combinar caps por día, semana,
            mes o año, ya sea por cantidad de leads o por revenue acumulado.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <CapRuleEditor caps={data.caps} onChange={(caps) => setData('caps', caps)} />
        </CardContent>
      </Card>

      {/* ── Actions ────────────────────────────────────────────────────────── */}
      <div className="flex items-center justify-end gap-3">
        <Button variant="outline" asChild>
          <Link href={route('ping-post.buyers.index')}>Cancel</Link>
        </Button>
        <Button type="submit" disabled={processing}>
          {processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Buyer'}
        </Button>
      </div>

    </form>
  )
}

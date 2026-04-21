import { CapRuleEditor } from '@/components/ping-post/cap-rule-editor';
import { ConditionalPricingEditor } from '@/components/ping-post/conditional-pricing-editor';
import { EligibilityRuleEditor } from '@/components/ping-post/eligibility-rule-editor';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FieldHint } from '@/components/ui/field-hint';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useBuyers } from '@/hooks/use-buyers';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import type { Integration } from '@/types/ping-post';
import { Link, usePage } from '@inertiajs/react';
import { type LucideIcon, AlertTriangle, Copy, DollarSign, ExternalLink, GitBranch, RotateCcw, TrendingUp } from 'lucide-react';
import { route } from 'ziggy-js';

const PRICING_META: Record<string, { icon: LucideIcon; description: string }> = {
  fixed: { icon: DollarSign, description: 'Fixed price per accepted lead' },
  response_bid: { icon: TrendingUp, description: 'Price extracted from buyer ping response bid' },
  conditional: { icon: GitBranch, description: 'Price varies by lead conditions' },
  postback: { icon: RotateCcw, description: 'Price confirmed via postback callback' },
};

interface ExternalPostback {
  id: number
  uuid: string
  name: string
  param_mappings: Record<string, string>
  generated_url: string
}

interface Props {
  integrations?: Integration[];
  priceSources?: Array<{ value: string; label: string }>;
  companies?: Array<{ id: number; name: string }>;
  fields?: { id: number; name: string }[];
  externalPostbacks?: ExternalPostback[];
}

export function BuyerForm({ integrations = [], priceSources = [], companies = [], fields = [], externalPostbacks = [] }: Props) {
  const { isEdit, data, errors, processing, handleSubmit, setData } = useBuyers()
  const { auth } = usePage<SharedData>().props
  const isAdmin = auth.user?.role === 'admin';

  const selectedIntegration = integrations.find((i) => i.id === data.integration_id) ?? null;
  const isPingPost = selectedIntegration?.type === 'ping-post';

  return (
    <form onSubmit={handleSubmit} className="space-y-6">
      {/* ── Buyer Info ─────────────────────────────────────────────────────── */}
      <Card>
        <CardHeader className="flex-row items-start justify-between space-y-0 pb-4">
          <div>
            <CardTitle>Buyer Info</CardTitle>
            <CardDescription className="mt-1">Identifica al buyer y vinculalo a una integración existente.</CardDescription>
          </div>
          <div className="flex items-center gap-2">
            <Switch id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v)} />
            <Label htmlFor="is_active" className="cursor-pointer">
              {data.is_active ? (
                <Badge className="bg-emerald-500 text-white hover:bg-emerald-500">Active</Badge>
              ) : (
                <Badge variant="secondary">Inactive</Badge>
              )}
            </Label>
            <FieldHint text="Un buyer inactivo es ignorado por todos los workflows y no recibe leads, aunque esté asignado. Útil para pausar temporalmente sin eliminarlo." />
          </div>
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
            {isEdit ? (
              <div className="flex items-center gap-2">
                <div className="flex h-9 flex-1 items-center gap-2 rounded-md border bg-muted/50 px-3 text-sm text-foreground">
                  <span className="flex-1 truncate">{selectedIntegration?.name ?? '—'}</span>
                  {selectedIntegration && (
                    <Badge variant="outline" className="shrink-0 text-xs">
                      {selectedIntegration.type}
                    </Badge>
                  )}
                </div>
                {isAdmin && selectedIntegration && (
                  <Button variant="outline" size="icon" className="shrink-0" asChild>
                    <Link href={route('integrations.edit', selectedIntegration.id)}>
                      <ExternalLink className="h-4 w-4" />
                    </Link>
                  </Button>
                )}
              </div>
            ) : (
              <Select value={data.integration_id ? String(data.integration_id) : ''} onValueChange={(v) => setData('integration_id', parseInt(v))}>
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
            )}
            {errors.integration_id && <p className="text-sm text-destructive">{errors.integration_id}</p>}
            {isEdit && <p className="text-xs text-muted-foreground">Integration cannot be changed after creation.</p>}
          </div>

          {companies.length > 0 && (
            <div className="space-y-2">
              <Label htmlFor="company_id">
                Company
                <FieldHint text="Asociación opcional con una empresa del sistema. Útil para agrupar buyers por cliente o cuenta." />
              </Label>
              <Select value={data.company_id ? String(data.company_id) : ''} onValueChange={(v) => setData('company_id', v ? parseInt(v) : null)}>
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
        </CardContent>
      </Card>

      {/* ── Timeouts ────────────────────────────────────────────────────────── */}
      {selectedIntegration && (
        <Card>
          <CardHeader>
            <CardTitle>Connection Timeouts</CardTitle>
            <CardDescription>
              Tiempo máximo de espera para cada fase. La configuración de response parsing se gestiona en el formulario de la integración.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
            {isPingPost && (
              <div className="space-y-2">
                <Label htmlFor="ping_timeout_ms">
                  Ping Timeout (ms)
                  <FieldHint text="Tiempo máximo en milisegundos que se espera la respuesta del ping. Si el buyer no responde a tiempo, es omitido en esta ronda. Se recomienda un valor bajo (1000–3000 ms) para no bloquear el workflow." />
                </Label>
                <div className="relative">
                  <Input
                    id="ping_timeout_ms"
                    type="number"
                    min={500}
                    placeholder="e.g. 3000 (recomendado: 1000–3000)"
                    value={data.ping_timeout_ms}
                    onChange={(e) => setData('ping_timeout_ms', e.target.value === '' ? '' : parseInt(e.target.value))}
                    className="pr-9"
                  />
                  <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-muted-foreground">ms</span>
                </div>
              </div>
            )}

            <div className="space-y-2">
              <Label htmlFor="post_timeout_ms">
                Post Timeout (ms)
                <FieldHint text="Tiempo máximo en milisegundos para esperar la respuesta del post. Puede ser más alto que el ping timeout ya que el lead completo se está transfiriendo. Valor recomendado: 5000–10000 ms." />
              </Label>
              <div className="relative">
                <Input
                  id="post_timeout_ms"
                  type="number"
                  min={500}
                  placeholder="e.g. 5000 (recomendado: 5000–10000)"
                  value={data.post_timeout_ms}
                  onChange={(e) => setData('post_timeout_ms', e.target.value === '' ? '' : parseInt(e.target.value))}
                  className="pr-9"
                />
                <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-muted-foreground">ms</span>
              </div>
            </div>
          </CardContent>
        </Card>
      )}

      {/* ── Pricing ────────────────────────────────────────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle>Pricing</CardTitle>
          <CardDescription>Define cómo se determina el precio que se cobra o acepta por cada lead enviado a este buyer.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-5">
          <div className="space-y-2">
            <Label>
              Pricing Type
              <FieldHint
                items={[
                  { label: 'Fixed', description: 'Se cobra un precio fijo por lead, independiente de lo que el buyer oferte en el ping.' },
                  {
                    label: 'Min Bid',
                    description: 'El buyer debe ofertar al menos el valor configurado. Bids menores son rechazados automáticamente.',
                  },
                  {
                    label: 'Conditional',
                    description:
                      'El precio varía según condiciones del lead (estado, edad, vertical, etc.). Se aplica la primera regla que coincida.',
                  },
                  {
                    label: 'Postback',
                    description:
                      'El precio final se confirma cuando el buyer envía un postback de conversión. Hasta entonces el lead queda pendiente.',
                  },
                ]}
              />
            </Label>
            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
              {priceSources.map((pt) => {
                const meta = PRICING_META[pt.value];
                const Icon = meta?.icon;
                const isSelected = data.price_source === pt.value;
                return (
                  <button
                    key={pt.value}
                    type="button"
                    onClick={() => setData('price_source', pt.value)}
                    className={cn(
                      'flex flex-col gap-2 rounded-xl border p-3 text-left transition-all hover:bg-muted/30',
                      isSelected ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-border',
                    )}
                  >
                    <div className="flex items-center justify-between">
                      {Icon && <Icon className={cn('h-5 w-5', isSelected ? 'text-primary' : 'text-muted-foreground')} />}
                      {isSelected && <div className="h-2 w-2 rounded-full bg-primary" />}
                    </div>
                    <div>
                      <p className={cn('text-sm font-semibold', isSelected ? 'text-primary' : '')}>{pt.label}</p>
                      {meta && <p className="mt-0.5 text-xs leading-snug text-muted-foreground">{meta.description}</p>}
                    </div>
                  </button>
                );
              })}
            </div>
          </div>

          {data.price_source === 'fixed' && (
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
                className="max-w-40"
              />
              {errors.fixed_price && <p className="text-sm text-destructive">{errors.fixed_price}</p>}
            </div>
          )}

          {data.price_source === 'response_bid' && (
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
                className="max-w-40"
              />
              {errors.min_bid && <p className="text-sm text-destructive">{errors.min_bid}</p>}
            </div>
          )}

          {data.price_source === 'postback' && (
            <>
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
                  className="max-w-40"
                />
              </div>

              {/* ── Pricing Webhook ──────────────────────────────────────────── */}
              <PricingWebhookSection
                externalPostbacks={externalPostbacks}
                pricingPostback={data.pricing_postback}
                onChange={(value) => setData('pricing_postback', value)}
                errors={errors}
              />
            </>
          )}

          {data.price_source === 'conditional' && (
            <div className="space-y-2">
              <Label>
                Pricing Rules
                <FieldHint text="Se evalúan en orden. Se aplica el precio de la primera regla cuyas condiciones coincidan con el lead. Si ninguna coincide y sell_on_zero_price está activo, el precio resuelto será $0." />
              </Label>
              <ConditionalPricingEditor
                rules={data.conditional_pricing_rules}
                onChange={(rules) => setData('conditional_pricing_rules', rules)}
                fields={fields}
              />
            </div>
          )}

          {/* ── Sell on zero price ──────────────────────────────────────────── */}
          <Alert className="bg-muted text-foreground">
            <AlertTriangle />
            <AlertTitle className="flex items-center justify-between text-base">
              <span>Sell on zero price</span>
              <Switch id="sell_on_zero_price" checked={data.sell_on_zero_price} onCheckedChange={(v) => setData('sell_on_zero_price', v)} />
            </AlertTitle>
            <AlertDescription>
              Allow selling leads to this buyer even when the resolved price is $0 or unavailable. If disabled, the buyer will be skipped when the
              price cannot be resolved.
            </AlertDescription>
          </Alert>
        </CardContent>
      </Card>

      {/* ── Eligibility Rules ──────────────────────────────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle>Eligibility Rules</CardTitle>
          <CardDescription>
            Filtros que determinan si un lead puede ser enviado a este buyer. El lead debe cumplir
            <strong> todas</strong> las reglas para ser elegible. Si no cumple alguna, el buyer es omitido sin contar como rechazo. Ejemplo: solo
            leads de CA y TX, con edad ≥ 25.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <EligibilityRuleEditor rules={data.eligibility_rules} onChange={(rules) => setData('eligibility_rules', rules)} fields={fields} />
        </CardContent>
      </Card>

      {/* ── Cap Rules ──────────────────────────────────────────────────────── */}
      <Card>
        <CardHeader>
          <CardTitle>Volume Caps</CardTitle>
          <CardDescription>
            Límites de volumen para este buyer. Cuando se alcanza un cap, el buyer es omitido automáticamente hasta que el período se reinicie. Puedes
            combinar caps por día, semana, mes o año, ya sea por cantidad de leads o por revenue acumulado.
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
        <Button type="submit" disabled={processing} className="min-w-28">
          {processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Buyer'}
        </Button>
      </div>
    </form>
  );
}

// ─── Pricing Webhook Section ──────────────────────────────────────────────────

interface PricingWebhookProps {
  externalPostbacks: ExternalPostback[]
  pricingPostback: { postback_id: number; identifier_token: string; price_token: string } | null
  onChange: (value: { postback_id: number; identifier_token: string; price_token: string } | null) => void
  errors: Record<string, string>
}

function PricingWebhookSection({ externalPostbacks, pricingPostback, onChange, errors }: PricingWebhookProps) {
  const selectedPostback = externalPostbacks.find((p) => p.id === pricingPostback?.postback_id) ?? null
  const internalTokens = selectedPostback ? [...new Set(Object.values(selectedPostback.param_mappings))] : []

  const handlePostbackChange = (postbackId: string) => {
    if (postbackId === 'none') {
      onChange(null)
      return
    }
    onChange({ postback_id: Number(postbackId), identifier_token: '', price_token: '' })
  }

  return (
    <div className="space-y-4 rounded-lg border p-4">
      <div>
        <h4 className="text-sm font-medium">Pricing Webhook</h4>
        <p className="text-muted-foreground text-xs">
          Vincula un postback externo para recibir la confirmación de precio del buyer. Cuando el partner dispara la URL, el sistema resuelve el
          precio pendiente automáticamente.
        </p>
      </div>

      <div className="space-y-2">
        <Label>Postback</Label>
        <Select value={pricingPostback?.postback_id ? String(pricingPostback.postback_id) : 'none'} onValueChange={handlePostbackChange}>
          <SelectTrigger className="max-w-sm">
            <SelectValue placeholder="Select a postback..." />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="none">No postback</SelectItem>
            {externalPostbacks.map((p) => (
              <SelectItem key={p.id} value={String(p.id)}>
                {p.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        {errors['pricing_postback.postback_id'] && <p className="text-sm text-destructive">{errors['pricing_postback.postback_id']}</p>}
      </div>

      {selectedPostback && pricingPostback && (
        <>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label>
                Identifier Token
                <FieldHint text="El token que contiene el fingerprint del lead. Se usa para buscar el PostResult pendiente." />
              </Label>
              <Select
                value={pricingPostback.identifier_token || undefined}
                onValueChange={(v) => onChange({ ...pricingPostback, identifier_token: v })}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Select token..." />
                </SelectTrigger>
                <SelectContent>
                  {internalTokens.map((token) => (
                    <SelectItem key={token} value={token}>
                      {token}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors['pricing_postback.identifier_token'] && (
                <p className="text-sm text-destructive">{errors['pricing_postback.identifier_token']}</p>
              )}
            </div>

            <div className="space-y-2">
              <Label>
                Price Token
                <FieldHint text="El token que contiene el precio confirmado por el buyer." />
              </Label>
              <Select
                value={pricingPostback.price_token || undefined}
                onValueChange={(v) => onChange({ ...pricingPostback, price_token: v })}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Select token..." />
                </SelectTrigger>
                <SelectContent>
                  {internalTokens.map((token) => (
                    <SelectItem key={token} value={token}>
                      {token}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors['pricing_postback.price_token'] && (
                <p className="text-sm text-destructive">{errors['pricing_postback.price_token']}</p>
              )}
            </div>
          </div>

          {selectedPostback.generated_url && (
            <div className="space-y-2">
              <Label className="text-muted-foreground text-xs">Generated URL</Label>
              <div className="bg-muted flex items-center gap-2 rounded-md p-3">
                <code className="flex-1 truncate text-xs">{selectedPostback.generated_url}</code>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="h-7 w-7 shrink-0"
                  onClick={() => navigator.clipboard.writeText(selectedPostback.generated_url)}
                >
                  <Copy className="h-3.5 w-3.5" />
                </Button>
              </div>
              <p className="text-muted-foreground text-xs">Comparte esta URL con el buyer para recibir confirmaciones de precio.</p>
            </div>
          )}
        </>
      )}
    </div>
  )
}

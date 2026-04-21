import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { BuyerOption, ProviderOption, RuleStatusOption, RuleStatusValue, ValidationTypeOption } from '@/types/models/lead-quality';
import { Link } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { BuyerMultiSelect } from './buyer-multiselect';
import { RuleSettingsFields, type RuleSettings } from './rule-settings';

export interface RuleFormData {
  name: string;
  validation_type: string;
  provider_id: number | '';
  status: RuleStatusValue;
  is_enabled: boolean;
  description: string;
  settings: RuleSettings;
  priority: number;
  buyer_ids: number[];
}

interface Props {
  data: RuleFormData;
  setData: (key: string, value: unknown) => void;
  errors: Record<string, string>;
  processing: boolean;
  validationTypes: ValidationTypeOption[];
  statuses: RuleStatusOption[];
  providers: ProviderOption[];
  buyers: BuyerOption[];
  onSubmit: (e: React.FormEvent) => void;
  isEdit?: boolean;
}

export function RuleForm({ data, setData, errors, processing, validationTypes, statuses, providers, buyers, onSubmit, isEdit = false }: Props) {
  const validationMeta = validationTypes.find((t) => t.value === data.validation_type);
  const provider = providers.find((p) => p.id === data.provider_id);
  const providerNotUsable = provider && !provider.is_usable;

  return (
    <form onSubmit={onSubmit} className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Basic info</CardTitle>
          <CardDescription>Describe the rule and how it applies.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="name">Name</Label>
            <Input
              id="name"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              placeholder="OTP phone validation for premium buyers"
            />
            {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
          </div>

          <div className="grid gap-4 md:grid-cols-3">
            <div className="space-y-2">
              <Label>Validation type</Label>
              <Select value={data.validation_type} onValueChange={(v) => setData('validation_type', v)}>
                <SelectTrigger>
                  <SelectValue placeholder="Choose a type" />
                </SelectTrigger>
                <SelectContent>
                  {validationTypes.map((t) => (
                    <SelectItem key={t.value} value={t.value}>
                      {t.label}
                      {t.is_async ? ' · async' : ' · sync'}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.validation_type && <p className="text-xs text-destructive">{errors.validation_type}</p>}
              {validationMeta && (
                <p className="text-xs text-muted-foreground">
                  {validationMeta.is_async
                    ? 'Async — the landing must call challenge/send and challenge/verify before dispatch.'
                    : 'Sync — resolved inline during dispatch eligibility.'}
                </p>
              )}
            </div>

            <div className="space-y-2">
              <Label>Provider</Label>
              <Select value={data.provider_id ? String(data.provider_id) : ''} onValueChange={(v) => setData('provider_id', v ? Number(v) : '')}>
                <SelectTrigger>
                  <SelectValue placeholder="Pick a provider" />
                </SelectTrigger>
                <SelectContent>
                  {providers.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)} disabled={!p.is_usable}>
                      {p.name}
                      {!p.is_usable ? ' (not usable)' : ''}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.provider_id && <p className="text-xs text-destructive">{errors.provider_id}</p>}
            </div>

            <div className="space-y-2">
              <Label>Status</Label>
              <Select value={data.status} onValueChange={(v) => setData('status', v as RuleStatusValue)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {statuses.map((s) => (
                    <SelectItem key={s.value} value={s.value}>
                      {s.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.status && <p className="text-xs text-destructive">{errors.status}</p>}
            </div>
          </div>

          {providerNotUsable && (
            <div className="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-200">
              <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
              <div>
                <div className="font-medium">Provider is not usable right now.</div>
                <p className="text-xs">
                  Its status is inactive, disabled or missing credentials. The rule will be saved but won't fire until the provider is enabled.
                </p>
              </div>
            </div>
          )}

          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="priority">Priority</Label>
              <Input
                id="priority"
                type="number"
                min={0}
                max={65535}
                value={data.priority}
                onChange={(e) => setData('priority', Number(e.target.value))}
              />
              <p className="text-xs text-muted-foreground">Lower runs first if multiple rules apply. Default: 100.</p>
              {errors.priority && <p className="text-xs text-destructive">{errors.priority}</p>}
            </div>

            <div className="flex items-center justify-between rounded-md border p-3">
              <div>
                <Label className="text-sm">Enabled</Label>
                <p className="text-xs text-muted-foreground">Disabled rules don't run even if their status is active.</p>
              </div>
              <Switch checked={data.is_enabled} onCheckedChange={(v) => setData('is_enabled', v)} />
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="description">Description</Label>
            <Textarea
              id="description"
              value={data.description}
              onChange={(e) => setData('description', e.target.value)}
              rows={2}
              placeholder="What does this rule enforce and why?"
            />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Buyers</CardTitle>
          <CardDescription>Pick the buyers this rule applies to. A rule can be reused across multiple buyers.</CardDescription>
        </CardHeader>
        <CardContent>
          <BuyerMultiSelect buyers={buyers} value={data.buyer_ids} onChange={(ids) => setData('buyer_ids', ids)} />
          {errors.buyer_ids && <p className="mt-2 text-xs text-destructive">{errors.buyer_ids}</p>}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Settings</CardTitle>
          <CardDescription>Parameters specific to the chosen validation type.</CardDescription>
        </CardHeader>
        <CardContent>
          <RuleSettingsFields
            validationType={data.validation_type}
            value={data.settings}
            onChange={(next) => setData('settings', next)}
            errors={errors}
          />
        </CardContent>
      </Card>

      <div className="flex items-center gap-3">
        <Button type="submit" disabled={processing}>
          {processing ? 'Saving…' : isEdit ? 'Update rule' : 'Create rule'}
        </Button>
        <Button type="button" variant="outline" asChild>
          <Link href={route('lead-quality.validation-rules.index')}>Cancel</Link>
        </Button>
      </div>
    </form>
  );
}

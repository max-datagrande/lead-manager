import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { EnvironmentOption, ProviderStatusOption, ProviderStatusValue, ProviderTypeOption } from '@/types/models/lead-quality';
import { Link } from '@inertiajs/react';
import { CredentialsFields } from './credentials-fields';
import { TestConnectionButton } from './test-connection-button';

export interface ProviderFormData {
  name: string;
  type: string;
  status: ProviderStatusValue;
  is_enabled: boolean;
  environment: 'production' | 'sandbox' | 'test';
  credentials: Record<string, string>;
  notes: string;
}

interface Props {
  data: ProviderFormData;
  setData: (key: keyof ProviderFormData | string, value: unknown) => void;
  errors: Record<string, string>;
  processing: boolean;
  providerTypes: ProviderTypeOption[];
  statuses: ProviderStatusOption[];
  environments: EnvironmentOption[];
  onSubmit: (e: React.FormEvent) => void;
  isEdit?: boolean;
  providerId?: number;
}

export function ProviderForm({
  data,
  setData,
  errors,
  processing,
  providerTypes,
  statuses,
  environments,
  onSubmit,
  isEdit = false,
  providerId,
}: Props) {
  const currentType = providerTypes.find((t) => t.value === data.type);
  const notImplemented = currentType && !currentType.is_implemented;

  return (
    <form onSubmit={onSubmit} className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Basic info</CardTitle>
          <CardDescription>Identify the provider and control whether it is in use.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="name">Name</Label>
            <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Twilio Verify (Production)" />
            {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
          </div>

          <div className="grid gap-4 md:grid-cols-3">
            <div className="space-y-2">
              <Label>Type</Label>
              <Select value={data.type} onValueChange={(v) => setData('type', v)} disabled={isEdit}>
                <SelectTrigger>
                  <SelectValue placeholder="Select type" />
                </SelectTrigger>
                <SelectContent>
                  {providerTypes.map((t) => (
                    <SelectItem key={t.value} value={t.value} disabled={!t.is_implemented}>
                      {t.label}
                      {!t.is_implemented ? ' (coming soon)' : ''}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.type && <p className="text-xs text-destructive">{errors.type}</p>}
              {isEdit && <p className="text-xs text-muted-foreground">Type cannot be changed after creation.</p>}
            </div>

            <div className="space-y-2">
              <Label>Status</Label>
              <Select value={data.status} onValueChange={(v) => setData('status', v as ProviderStatusValue)}>
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

            <div className="space-y-2">
              <Label>Environment</Label>
              <Select value={data.environment} onValueChange={(v) => setData('environment', v)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {environments.map((e) => (
                    <SelectItem key={e.value} value={e.value}>
                      {e.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.environment && <p className="text-xs text-destructive">{errors.environment}</p>}
            </div>
          </div>

          <div className="flex items-center justify-between rounded-md border p-3">
            <div>
              <Label className="text-sm">Enabled</Label>
              <p className="text-xs text-muted-foreground">Disabled providers are ignored by rules even if status is active.</p>
            </div>
            <Switch checked={data.is_enabled} onCheckedChange={(v) => setData('is_enabled', v)} disabled={notImplemented} />
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Credentials</CardTitle>
          <CardDescription>Stored encrypted. Hidden fields keep the previous value if left blank on edit.</CardDescription>
        </CardHeader>
        <CardContent>
          <CredentialsFields type={data.type} credentials={data.credentials} onChange={(c) => setData('credentials', c)} errors={errors} />
        </CardContent>
      </Card>

      {isEdit && providerId && (
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Connection test</CardTitle>
            <CardDescription>Runs a read-only call against the provider to validate credentials.</CardDescription>
          </CardHeader>
          <CardContent>
            <TestConnectionButton providerId={providerId} disabled={notImplemented} />
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle className="text-base">Notes</CardTitle>
          <CardDescription>Optional free-text notes visible only in the admin.</CardDescription>
        </CardHeader>
        <CardContent>
          <Textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows={3} />
        </CardContent>
      </Card>

      <div className="flex items-center gap-3">
        <Button type="submit" disabled={processing}>
          {processing ? 'Saving…' : isEdit ? 'Update provider' : 'Create provider'}
        </Button>
        <Button type="button" variant="outline" asChild>
          <Link href={route('lead-quality.providers.index')}>Cancel</Link>
        </Button>
      </div>
    </form>
  );
}

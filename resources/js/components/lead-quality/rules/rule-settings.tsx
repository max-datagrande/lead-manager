import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';

export type RuleSettings = {
  channel?: 'sms' | 'call' | 'email' | 'whatsapp';
  otp_length?: number;
  ttl?: number;
  max_attempts?: number;
  validity_window?: number;
  required_score?: number;
  sync_check?: boolean;
};

interface Props {
  validationType: string;
  value: RuleSettings;
  onChange: (next: RuleSettings) => void;
  errors?: Record<string, string>;
}

const NumberField = ({
  id,
  label,
  hint,
  value,
  min,
  max,
  onChange,
  error,
}: {
  id: string;
  label: string;
  hint?: string;
  value: number | undefined;
  min?: number;
  max?: number;
  onChange: (n: number | undefined) => void;
  error?: string;
}) => (
  <div className="space-y-1.5">
    <Label htmlFor={id}>{label}</Label>
    <Input
      id={id}
      type="number"
      min={min}
      max={max}
      value={value ?? ''}
      onChange={(e) => {
        const raw = e.target.value;
        onChange(raw === '' ? undefined : Number(raw));
      }}
    />
    {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
    {error && <p className="text-xs text-destructive">{error}</p>}
  </div>
);

export function RuleSettingsFields({ validationType, value, onChange, errors = {} }: Props) {
  const set = <K extends keyof RuleSettings>(key: K, v: RuleSettings[K]) => {
    onChange({ ...value, [key]: v });
  };

  const isOtp = validationType === 'otp_phone' || validationType === 'otp_email';
  const isLookup = validationType === 'phone_lookup' || validationType === 'email_reputation' || validationType === 'ipqs_score';
  const isScored = validationType === 'ipqs_score';

  if (!validationType) {
    return <p className="text-sm text-muted-foreground">Pick a validation type to configure its settings.</p>;
  }

  return (
    <div className="space-y-5">
      {isOtp && (
        <>
          <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-1.5">
              <Label>Channel</Label>
              <Select value={value.channel ?? 'sms'} onValueChange={(v) => set('channel', v as RuleSettings['channel'])}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {validationType === 'otp_phone' ? (
                    <>
                      <SelectItem value="sms">SMS</SelectItem>
                      <SelectItem value="call">Call</SelectItem>
                      <SelectItem value="whatsapp">WhatsApp</SelectItem>
                    </>
                  ) : (
                    <SelectItem value="email">Email</SelectItem>
                  )}
                </SelectContent>
              </Select>
              <p className="text-xs text-muted-foreground">Delivery channel supported by the provider.</p>
            </div>

            <NumberField
              id="otp_length"
              label="Code length"
              hint="Digits per challenge code (Twilio default: 6)."
              min={4}
              max={10}
              value={value.otp_length}
              onChange={(n) => set('otp_length', n)}
              error={errors['settings.otp_length']}
            />
          </div>

          <div className="grid gap-4 md:grid-cols-3">
            <NumberField
              id="ttl"
              label="Code TTL (seconds)"
              hint="How long the code stays valid after send."
              min={60}
              max={3600}
              value={value.ttl}
              onChange={(n) => set('ttl', n)}
              error={errors['settings.ttl']}
            />
            <NumberField
              id="max_attempts"
              label="Max verify attempts"
              hint="After this many wrong codes the rule fails."
              min={1}
              max={10}
              value={value.max_attempts}
              onChange={(n) => set('max_attempts', n)}
              error={errors['settings.max_attempts']}
            />
            <NumberField
              id="validity_window"
              label="Validity window (minutes)"
              hint="How long a verified challenge is reused by the dispatch gate."
              min={1}
              max={1440}
              value={value.validity_window}
              onChange={(n) => set('validity_window', n)}
              error={errors['settings.validity_window']}
            />
          </div>
        </>
      )}

      {isLookup && (
        <div className="grid gap-4 md:grid-cols-2">
          <NumberField
            id="validity_window_lookup"
            label="Validity window (minutes)"
            hint="Cache lookup results for this long before re-querying."
            min={1}
            max={1440}
            value={value.validity_window}
            onChange={(n) => set('validity_window', n)}
            error={errors['settings.validity_window']}
          />
          {isScored && (
            <NumberField
              id="required_score"
              label="Minimum score"
              hint="IPQS score threshold the lead must meet to pass."
              min={0}
              max={100}
              value={value.required_score}
              onChange={(n) => set('required_score', n)}
              error={errors['settings.required_score']}
            />
          )}
          <div className="flex items-center justify-between rounded-md border p-3 md:col-span-2">
            <div>
              <Label className="text-sm">Run synchronously inside dispatch</Label>
              <p className="text-xs text-muted-foreground">
                When enabled, the checker calls the provider inline during dispatch instead of consulting an existing log.
              </p>
            </div>
            <Switch checked={value.sync_check ?? true} onCheckedChange={(v) => set('sync_check', v)} />
          </div>
        </div>
      )}
    </div>
  );
}

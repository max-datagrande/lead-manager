import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AlertTriangle } from 'lucide-react';

interface FieldSpec {
  key: string;
  label: string;
  placeholder?: string;
  helper?: string;
  secret?: boolean;
}

const FIELDS_BY_TYPE: Record<string, FieldSpec[]> = {
  twilio_verify: [
    { key: 'account_sid', label: 'Account SID', placeholder: 'ACxxxxxxxxxxxxxxxx', helper: 'From Twilio Console → Account Info.' },
    {
      key: 'auth_token',
      label: 'Auth Token',
      placeholder: '••••••••••••••••',
      secret: true,
      helper: 'Kept encrypted. Leave blank on edit to keep current.',
    },
    {
      key: 'verify_service_sid',
      label: 'Verify Service SID',
      placeholder: 'VAxxxxxxxxxxxxxxxx',
      helper: 'Create a Verify Service in Twilio and paste its SID.',
    },
  ],
  ipqs: [
    { key: 'api_key', label: 'API Key', placeholder: '••••••••', secret: true, helper: 'IPQS — placeholder, integration not implemented in V1.' },
  ],
  email_validator: [{ key: 'api_key', label: 'API Key', placeholder: '••••••••', secret: true, helper: 'Placeholder — no implementation yet.' }],
};

interface Props {
  type: string;
  credentials: Record<string, string>;
  onChange: (creds: Record<string, string>) => void;
  errors?: Record<string, string>;
}

export function CredentialsFields({ type, credentials, onChange, errors = {} }: Props) {
  const fields = FIELDS_BY_TYPE[type] ?? [];

  if (!fields.length) {
    return (
      <div className="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-200">
        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
        <div>Select a provider type to configure credentials.</div>
      </div>
    );
  }

  const update = (key: string, value: string) => {
    onChange({ ...credentials, [key]: value });
  };

  return (
    <div className="grid gap-4 md:grid-cols-2">
      {fields.map((f) => {
        const fieldError = errors[`credentials.${f.key}`];
        return (
          <div key={f.key} className="space-y-2">
            <Label htmlFor={`cred-${f.key}`}>{f.label}</Label>
            <Input
              id={`cred-${f.key}`}
              type={f.secret ? 'password' : 'text'}
              value={credentials[f.key] ?? ''}
              onChange={(e) => update(f.key, e.target.value)}
              placeholder={f.placeholder}
              autoComplete="off"
            />
            {f.helper && <p className="text-xs text-muted-foreground">{f.helper}</p>}
            {fieldError && <p className="text-xs text-destructive">{fieldError}</p>}
          </div>
        );
      })}
    </div>
  );
}

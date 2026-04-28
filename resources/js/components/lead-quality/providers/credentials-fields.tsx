import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AlertTriangle, CheckCircle2, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';

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
  melissa: [
    {
      key: 'license_key',
      label: 'License Key',
      placeholder: '••••••••••••••••••••',
      secret: true,
      helper: 'Melissa Global Phone v4 license key. Stored encrypted; leave blank on edit to keep current.',
    },
  ],
};

interface Props {
  type: string;
  credentials: Record<string, string>;
  onChange: (creds: Record<string, string>) => void;
  errors?: Record<string, string>;
  /**
   * Per-key "a value already exists in DB" map, driven from the server.
   * Secret fields can't be prefilled safely, so we use this to show an
   * "Already set" badge + "Leave blank to keep" placeholder — the admin
   * can see that a value exists without us ever exposing it.
   */
  credentialStatus?: Record<string, boolean>;
  /**
   * Per-key length of stored secret values. Used to render realistic
   * bullet-filled placeholders so secret fields look populated at a glance,
   * without ever shipping the actual value to the browser.
   */
  credentialLengths?: Record<string, number>;
}

export function CredentialsFields({ type, credentials, onChange, errors = {}, credentialStatus, credentialLengths }: Props) {
  const fields = FIELDS_BY_TYPE[type] ?? [];

  // Tracks which secret fields the admin explicitly unlocked for editing.
  // Locked secret fields render as read-only dots + a trash button; clicking
  // it clears the value (which the backend merge preserves as "keep existing"
  // when blank — actually rewriting requires typing a new value).
  const [unlocked, setUnlocked] = useState<Record<string, boolean>>({});
  const inputRefs = useRef<Record<string, HTMLInputElement | null>>({});

  const unlock = (key: string) => {
    setUnlocked((prev) => ({ ...prev, [key]: true }));
    onChange({ ...credentials, [key]: '' });
    requestAnimationFrame(() => inputRefs.current[key]?.focus());
  };

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
        const alreadySet = Boolean(credentialStatus?.[f.key]);
        const isLockedSecret = f.secret && alreadySet && !unlocked[f.key];
        const dotLength = credentialLengths?.[f.key] ?? 16;
        const placeholder = f.secret && alreadySet ? 'Leave blank to keep current' : f.placeholder;

        return (
          <div key={f.key} className="space-y-2">
            <div className="flex items-center justify-between gap-2">
              <Label htmlFor={`cred-${f.key}`}>{f.label}</Label>
              {alreadySet && (
                <Badge variant="outline" className="gap-1 text-[10px] font-medium text-emerald-700 dark:text-emerald-400">
                  <CheckCircle2 className="h-3 w-3" />
                  Already set
                </Badge>
              )}
            </div>
            <div className="flex items-center gap-2">
              <Input
                ref={(el) => {
                  inputRefs.current[f.key] = el;
                }}
                id={`cred-${f.key}`}
                type={f.secret ? 'password' : 'text'}
                value={isLockedSecret ? '•'.repeat(dotLength) : (credentials[f.key] ?? '')}
                onChange={(e) => update(f.key, e.target.value)}
                placeholder={placeholder}
                autoComplete="off"
                readOnly={isLockedSecret}
                className={isLockedSecret ? 'cursor-not-allowed' : ''}
              />
              {isLockedSecret && (
                <Button
                  type="button"
                  variant="outline"
                  size="icon"
                  onClick={() => unlock(f.key)}
                  aria-label={`Clear ${f.label} to enter a new value`}
                  title="Clear and enter a new value"
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              )}
            </div>
            {f.helper && <p className="text-xs text-muted-foreground">{f.helper}</p>}
            {fieldError && <p className="text-xs text-destructive">{fieldError}</p>}
          </div>
        );
      })}
    </div>
  );
}

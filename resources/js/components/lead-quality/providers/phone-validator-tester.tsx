import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
import axios from 'axios';
import { AlertTriangle, CheckCircle2, Loader2, PhoneCall, RotateCcw, XCircle } from 'lucide-react';
import { useMemo, useState } from 'react';
import { COUNTRY_CODES, DEFAULT_COUNTRY, findCountry, sanitizeLocal } from './country-codes';

type Phase = 'idle' | 'success' | 'failure';

type Outcome = 'accepted' | 'rejected' | 'technical_error';

interface ValidateResponse {
  ok: boolean;
  valid: boolean;
  classification: string;
  line_type: 'cellular' | 'landline' | 'voip' | null;
  country: string | null;
  carrier: string | null;
  normalized_phone: string | null;
  result_codes: string[];
  error: string | null;
}

interface Props {
  providerId: number;
  disabled?: boolean;
}

const ACCEPTED_CLASSIFICATIONS = new Set([
  'valid_high_confidence',
  'valid_low_confidence',
  'low_confidence',
  'compliance_risk',
  'pending_or_timeout',
]);

const CLASSIFICATION_LABELS: Record<string, string> = {
  valid_high_confidence: 'Valid (high confidence)',
  valid_low_confidence: 'Valid (low confidence)',
  low_confidence: 'Low confidence',
  compliance_risk: 'Compliance risk (DNC)',
  pending_or_timeout: 'Pending / timeout',
  invalid_phone: 'Invalid phone',
  disconnected_phone: 'Disconnected',
  high_risk_phone: 'High risk (disposable)',
  validation_error: 'Validation error',
};

/**
 * Admin-only smoke tester for the sync phone-validation provider (Melissa).
 *
 * Calls `validatePhone()` directly — bypasses `PhoneValidationService` and
 * its cache so the admin always sees fresh upstream output. The HTTP
 * exchange is recorded as `operation=test_validate_phone` to keep it
 * separated from production traffic in `external_service_requests`.
 */
export function PhoneValidatorTester({ providerId, disabled = false }: Props) {
  const { addMessage } = useToast();
  const [phase, setPhase] = useState<Phase>('idle');
  const [loading, setLoading] = useState(false);
  const [countryCode, setCountryCode] = useState<string>(DEFAULT_COUNTRY.code);
  const [localPart, setLocalPart] = useState('');
  const [result, setResult] = useState<ValidateResponse | null>(null);
  const [errorText, setErrorText] = useState<string | null>(null);

  const country = useMemo(() => findCountry(countryCode) ?? DEFAULT_COUNTRY, [countryCode]);

  // We send the raw phone string the admin typed — Melissa accepts both raw
  // digits and E.164. The UI just shows the user's intent unambiguously.
  const phoneToSend = useMemo(() => {
    if (country.code === 'OTHER') return localPart.trim();
    const digits = sanitizeLocal(localPart);
    return digits ? `${country.dial}${digits}` : '';
  }, [country, localPart]);

  const canSend = phoneToSend.length >= 6 && !loading && !disabled;

  const reset = () => {
    setPhase('idle');
    setResult(null);
    setErrorText(null);
  };

  const handleValidate = async () => {
    if (!canSend) return;
    setLoading(true);
    setErrorText(null);
    try {
      const { data } = await axios.post<ValidateResponse>(route('lead-quality.providers.test-validate-phone', providerId), {
        phone: phoneToSend,
        // Melissa expects the suspected country as ISO2; for OTHER we let it
        // infer from the dial code embedded in the phone string.
        country: country.code === 'OTHER' ? undefined : country.code,
      });
      setResult(data);
      setPhase(outcomeOf(data) === 'technical_error' ? 'failure' : 'success');
      addMessage(data.valid ? 'Phone accepted by Melissa.' : 'Phone rejected by Melissa.', data.valid ? 'success' : 'warning');
    } catch (e) {
      // Backend returns 422 with the same shape on technical errors. axios
      // throws on 4xx/5xx, so we try to surface the structured payload first.
      const data = axios.isAxiosError(e) ? (e.response?.data as ValidateResponse | undefined) : undefined;
      if (data && typeof data === 'object' && 'classification' in data) {
        setResult(data);
        setPhase('failure');
      } else {
        setErrorText(extractError(e));
        setPhase('failure');
      }
    } finally {
      setLoading(false);
    }
  };

  if (phase === 'idle') {
    return (
      <div className="space-y-3">
        <p className="text-xs text-muted-foreground">
          Sends a real validation request to Melissa Global Phone. Bypasses the cache and is recorded in <code>external_service_requests</code> as{' '}
          <code>operation=test_validate_phone</code> for audit. No <code>LeadDispatch</code> or <code>ValidationLog</code> is written.
        </p>
        <div className="space-y-1.5">
          <Label htmlFor="phone-validator-tester-input">Phone number</Label>
          <div className="flex gap-2">
            <Select value={countryCode} onValueChange={setCountryCode} disabled={loading || disabled}>
              <SelectTrigger className="w-[170px] shrink-0" aria-label="Country dial code">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {COUNTRY_CODES.map((c) => (
                  <SelectItem key={c.code} value={c.code}>
                    <span className="font-mono text-xs text-muted-foreground">{c.dial || '—'}</span>
                    <span className="ml-2">{c.name}</span>
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Input
              id="phone-validator-tester-input"
              inputMode={country.code === 'OTHER' ? 'text' : 'numeric'}
              value={localPart}
              onChange={(e) => setLocalPart(country.code === 'OTHER' ? e.target.value : sanitizeLocal(e.target.value))}
              placeholder={country.code === 'OTHER' ? '+441234567890' : '5551234567'}
              disabled={loading || disabled}
              autoComplete="off"
            />
          </div>
          <p className="text-xs text-muted-foreground">
            {country.code === 'OTHER'
              ? 'Paste the full E.164 number with the leading +.'
              : `Will validate ${phoneToSend || `${country.dial}…`} against Melissa.`}
          </p>
        </div>
        <Button type="button" onClick={handleValidate} disabled={!canSend} className="w-fit gap-2">
          {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <PhoneCall className="h-4 w-4" />}
          {loading ? 'Validating…' : 'Validate phone'}
        </Button>
      </div>
    );
  }

  // success | failure → render the result card.
  return (
    <div className="space-y-3">
      {result && <ResultCard result={result} />}
      {!result && errorText && (
        <div className="flex items-start gap-2 rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-200">
          <XCircle className="mt-0.5 h-4 w-4 shrink-0" />
          <span>{errorText}</span>
        </div>
      )}
      <Button type="button" variant="outline" onClick={reset} className="gap-2">
        <RotateCcw className="h-4 w-4" />
        Run another test
      </Button>
    </div>
  );
}

function ResultCard({ result }: { result: ValidateResponse }) {
  const outcome = outcomeOf(result);

  // Border + icon are derived from the outcome bucket, not the boolean
  // `valid` directly, so technical errors stand out from "real" rejections.
  const styles = (
    {
      accepted: 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-200',
      rejected: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-200',
      technical_error: 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-200',
    } as const
  )[outcome];

  const Icon = outcome === 'accepted' ? CheckCircle2 : outcome === 'rejected' ? AlertTriangle : XCircle;
  const headline = outcome === 'accepted' ? 'Accepted by Melissa' : outcome === 'rejected' ? 'Rejected by Melissa' : 'Technical error';

  return (
    <div className={`rounded-md border p-3 text-sm ${styles}`}>
      <div className="flex items-start gap-2">
        <Icon className="mt-0.5 h-4 w-4 shrink-0" />
        <div className="flex-1 space-y-2">
          <div className="font-medium">{headline}</div>
          <div className="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
            <DetailRow label="Classification" value={CLASSIFICATION_LABELS[result.classification] ?? result.classification} />
            <DetailRow label="Valid" value={result.valid ? 'true' : 'false'} />
            {result.normalized_phone && <DetailRow label="Normalized" value={result.normalized_phone} mono />}
            {result.country && <DetailRow label="Country" value={result.country} />}
            {result.line_type && <DetailRow label="Line type" value={result.line_type} />}
            {result.carrier && <DetailRow label="Carrier" value={result.carrier} />}
          </div>
          {result.result_codes.length > 0 && (
            <div className="flex flex-wrap items-center gap-1">
              <span className="text-xs">Codes:</span>
              {result.result_codes.map((code) => (
                <Badge key={code} variant="outline" className="font-mono text-[10px]">
                  {code}
                </Badge>
              ))}
            </div>
          )}
          {result.error && <div className="text-xs italic">{result.error}</div>}
        </div>
      </div>
    </div>
  );
}

function DetailRow({ label, value, mono = false }: { label: string; value: string; mono?: boolean }) {
  return (
    <div className="flex items-baseline gap-2">
      <span className="text-[10px] tracking-wide uppercase opacity-70">{label}</span>
      <span className={mono ? 'font-mono' : ''}>{value}</span>
    </div>
  );
}

function outcomeOf(result: ValidateResponse): Outcome {
  if (result.classification === 'validation_error') return 'technical_error';
  return ACCEPTED_CLASSIFICATIONS.has(result.classification) ? 'accepted' : 'rejected';
}

function extractError(e: unknown): string {
  if (axios.isAxiosError(e)) {
    const payload = e.response?.data as { error?: string; message?: string } | undefined;
    return payload?.error ?? payload?.message ?? e.message;
  }
  if (e instanceof Error) return e.message;
  return 'Unexpected error.';
}

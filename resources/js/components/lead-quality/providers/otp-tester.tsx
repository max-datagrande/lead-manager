import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
import axios from 'axios';
import { CheckCircle2, Loader2, MessageSquare, RotateCcw, ShieldCheck, XCircle } from 'lucide-react';
import { useMemo, useState } from 'react';
import { COUNTRY_CODES, DEFAULT_COUNTRY, findCountry, sanitizeLocal } from './country-codes';

type Channel = 'sms' | 'call' | 'email' | 'whatsapp';

type Phase = 'idle' | 'awaiting_code' | 'success' | 'failure';

interface SendResponse {
  ok: boolean;
  verification_sid?: string;
  masked_destination?: string;
  status?: string | null;
  error?: string;
}

interface VerifyResponse {
  ok: boolean;
  status?: string | null;
  error?: string;
}

interface Props {
  providerId: number;
  disabled?: boolean;
}

/**
 * Admin-only smoke tester for the provider's OTP challenge/verify roundtrip.
 *
 * The flow is deliberately narrow: enter a phone, send a code via the live
 * provider, then punch in the received code and verify it. Nothing is
 * persisted in `lead_quality_*` — only the two technical rows in
 * `external_service_requests` (operation=test_send|test_verify) for audit.
 */
export function ProviderOtpTester({ providerId, disabled = false }: Props) {
  const { addMessage } = useToast();
  const [phase, setPhase] = useState<Phase>('idle');
  const [loading, setLoading] = useState(false);
  // Phone = countryCode (ISO, e.g. 'US') + localPart (digits only, no prefix).
  // For `OTHER` we treat localPart as the full E.164 string pasted verbatim.
  const [countryCode, setCountryCode] = useState<string>(DEFAULT_COUNTRY.code);
  const [localPart, setLocalPart] = useState('');
  const [channel, setChannel] = useState<Channel>('sms');
  const [code, setCode] = useState('');
  const [maskedDestination, setMaskedDestination] = useState<string | null>(null);
  const [feedback, setFeedback] = useState<{ kind: 'ok' | 'err'; text: string } | null>(null);

  const isEmail = channel === 'email';
  const country = useMemo(() => findCountry(countryCode) ?? DEFAULT_COUNTRY, [countryCode]);

  // Compose the final destination that we actually send to the backend.
  //   - For email: just the email address as-is.
  //   - For OTHER country: trust the admin's raw input (they know the format).
  //   - For any other country: prefix dial code + digits-only local part.
  const destination = useMemo(() => {
    if (isEmail) return localPart.trim();
    if (country.code === 'OTHER') return localPart.trim();
    const digits = sanitizeLocal(localPart);
    return digits ? `${country.dial}${digits}` : '';
  }, [country, isEmail, localPart]);

  const canSend = destination.length >= 6 && !loading && !disabled;

  const reset = () => {
    setPhase('idle');
    setCode('');
    setMaskedDestination(null);
    setFeedback(null);
  };

  const handleSend = async () => {
    if (!canSend) return;
    setLoading(true);
    setFeedback(null);
    try {
      const { data } = await axios.post<SendResponse>(route('lead-quality.providers.test-send', providerId), {
        to: destination,
        channel,
      });
      if (data.ok) {
        setMaskedDestination(data.masked_destination ?? null);
        setPhase('awaiting_code');
        addMessage('Test OTP dispatched. Check the destination.', 'success');
      } else {
        setPhase('failure');
        setFeedback({ kind: 'err', text: data.error ?? 'Send failed.' });
      }
    } catch (e) {
      const text = extractError(e);
      setPhase('failure');
      setFeedback({ kind: 'err', text });
    } finally {
      setLoading(false);
    }
  };

  const handleVerify = async () => {
    if (!code.trim() || loading || disabled) return;
    setLoading(true);
    setFeedback(null);
    try {
      const { data } = await axios.post<VerifyResponse>(route('lead-quality.providers.test-verify', providerId), {
        to: destination,
        code: code.trim(),
      });
      if (data.ok) {
        setPhase('success');
        setFeedback({ kind: 'ok', text: `Verified. Twilio status: ${data.status ?? 'approved'}.` });
        addMessage('OTP verified successfully.', 'success');
      } else {
        setPhase('failure');
        setFeedback({ kind: 'err', text: data.error ?? `Twilio status: ${data.status ?? 'unknown'}.` });
      }
    } catch (e) {
      const text = extractError(e);
      setPhase('failure');
      setFeedback({ kind: 'err', text });
    } finally {
      setLoading(false);
    }
  };

  if (phase === 'idle') {
    return (
      <div className="space-y-3">
        <p className="text-xs text-muted-foreground">
          Sends a real OTP via the provider to check end-to-end wiring. No LeadDispatch, ValidationLog, or timeline event is written — only a
          technical request/response entry tagged <code>test_send</code> / <code>test_verify</code>.
        </p>
        <div className="grid gap-3 md:grid-cols-[2fr_1fr]">
          <div className="space-y-1.5">
            <Label htmlFor="otp-tester-to">Destination</Label>
            {isEmail ? (
              <Input
                id="otp-tester-to"
                type="email"
                value={localPart}
                onChange={(e) => setLocalPart(e.target.value)}
                placeholder="someone@example.com"
                disabled={loading || disabled}
                autoComplete="off"
              />
            ) : (
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
                  id="otp-tester-to"
                  inputMode={country.code === 'OTHER' ? 'text' : 'numeric'}
                  value={localPart}
                  onChange={(e) => setLocalPart(country.code === 'OTHER' ? e.target.value : sanitizeLocal(e.target.value))}
                  placeholder={country.code === 'OTHER' ? '+441234567890' : '5551234567'}
                  disabled={loading || disabled}
                  autoComplete="off"
                />
              </div>
            )}
            <p className="text-xs text-muted-foreground">
              {isEmail
                ? "Email provider needed. We'll pass the address as-is."
                : country.code === 'OTHER'
                  ? 'Paste the full E.164 number with the leading +.'
                  : `Will send to ${destination || `${country.dial}…`}`}
            </p>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="otp-tester-channel">Channel</Label>
            <Select value={channel} onValueChange={(v) => setChannel(v as Channel)} disabled={loading || disabled}>
              <SelectTrigger id="otp-tester-channel">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="sms">SMS</SelectItem>
                <SelectItem value="call">Voice call</SelectItem>
                <SelectItem value="whatsapp">WhatsApp</SelectItem>
                <SelectItem value="email">Email</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
        <Button type="button" onClick={handleSend} disabled={!canSend} className="w-fit gap-2">
          {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <MessageSquare className="h-4 w-4" />}
          {loading ? 'Sending…' : 'Send test OTP'}
        </Button>
      </div>
    );
  }

  if (phase === 'awaiting_code') {
    return (
      <div className="space-y-3">
        <div className="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-200">
          <div className="flex items-start gap-2">
            <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />
            <div>
              Code dispatched to <span className="font-mono">{maskedDestination ?? destination}</span>. Enter the code below to finish the roundtrip.
            </div>
          </div>
        </div>
        <div className="space-y-1.5">
          <Label htmlFor="otp-tester-code">Verification code</Label>
          <Input
            id="otp-tester-code"
            value={code}
            onChange={(e) => setCode(e.target.value.replace(/\s/g, ''))}
            placeholder="123456"
            inputMode="numeric"
            disabled={loading || disabled}
            autoComplete="one-time-code"
            maxLength={12}
          />
        </div>
        <div className="flex items-center gap-2">
          <Button type="button" onClick={handleVerify} disabled={!code.trim() || loading || disabled} className="gap-2">
            {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <ShieldCheck className="h-4 w-4" />}
            {loading ? 'Verifying…' : 'Verify code'}
          </Button>
          <Button type="button" variant="outline" onClick={reset} disabled={loading} className="gap-2">
            <RotateCcw className="h-4 w-4" />
            Reset
          </Button>
        </div>
      </div>
    );
  }

  // success | failure
  return (
    <div className="space-y-3">
      <div
        className={
          phase === 'success'
            ? 'flex items-start gap-2 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-200'
            : 'flex items-start gap-2 rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-200'
        }
      >
        {phase === 'success' ? <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" /> : <XCircle className="mt-0.5 h-4 w-4 shrink-0" />}
        <span>{feedback?.text ?? (phase === 'success' ? 'Verified.' : 'Something went wrong.')}</span>
      </div>
      <Button type="button" variant="outline" onClick={reset} className="gap-2">
        <RotateCcw className="h-4 w-4" />
        Run another test
      </Button>
    </div>
  );
}

function extractError(e: unknown): string {
  if (axios.isAxiosError(e)) {
    const payload = e.response?.data as { error?: string; message?: string } | undefined;
    return payload?.error ?? payload?.message ?? e.message;
  }
  if (e instanceof Error) return e.message;
  return 'Unexpected error.';
}

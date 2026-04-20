import { Button } from '@/components/ui/button';
import { useToast } from '@/hooks/use-toast';
import { CheckCircle2, PlugZap, XCircle } from 'lucide-react';
import { useState } from 'react';

interface Props {
  providerId: number;
  disabled?: boolean;
}

export function TestConnectionButton({ providerId, disabled = false }: Props) {
  const { addMessage } = useToast();
  const [loading, setLoading] = useState(false);
  const [lastResult, setLastResult] = useState<{ ok: boolean; text: string } | null>(null);

  const handleClick = async () => {
    if (loading || disabled) return;
    setLoading(true);
    setLastResult(null);
    try {
      const res = await fetch(route('lead-quality.providers.test', providerId), {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
        },
      });
      const json = (await res.json()) as { ok: boolean; message?: string; error?: string };
      const ok = Boolean(json.ok);
      const text = ok ? (json.message ?? 'Connection successful.') : (json.error ?? 'Connection failed.');
      setLastResult({ ok, text });
      addMessage(text, ok ? 'success' : 'error');
    } catch (e) {
      const text = e instanceof Error ? e.message : 'Unexpected error.';
      setLastResult({ ok: false, text });
      addMessage(text, 'error');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex flex-col gap-2">
      <Button type="button" variant="outline" onClick={handleClick} disabled={loading || disabled} className="w-fit gap-2">
        <PlugZap className="h-4 w-4" />
        {loading ? 'Testing…' : 'Test connection'}
      </Button>
      {lastResult && (
        <div
          className={`flex items-start gap-2 rounded-md border p-2 text-sm ${
            lastResult.ok
              ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-200'
              : 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-200'
          }`}
        >
          {lastResult.ok ? <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" /> : <XCircle className="mt-0.5 h-4 w-4 shrink-0" />}
          <span>{lastResult.text}</span>
        </div>
      )}
    </div>
  );
}

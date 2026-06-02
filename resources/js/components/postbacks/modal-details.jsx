import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useCurrentModalId, useModal } from '@/hooks/use-modal';
import { useToast } from '@/hooks/use-toast';
import { Check, Copy } from 'lucide-react';
import { useMemo, useState } from 'react';

export default function ModalDetails({ postback, sources = [] }) {
  const modal = useModal();
  const modalId = useCurrentModalId();
  const [isCopied, setIsCopied] = useState(false);
  const { addMessage } = useToast();

  const isInternal = postback.type === 'internal';
  const hasSources = isInternal && sources.length > 0;
  const [selectedSource, setSelectedSource] = useState(() => (sources.some((s) => s.value === 'manual') ? 'manual' : (sources[0]?.value ?? '')));

  const displayUrl = useMemo(() => {
    if (!postback.generated_url) return '';
    if (!hasSources || !selectedSource) return postback.generated_url;
    return postback.generated_url.replace('{source}', selectedSource);
  }, [postback.generated_url, hasSources, selectedSource]);

  const copyUrl = () => {
    navigator.clipboard.writeText(displayUrl);
    setIsCopied(true);
    addMessage('URL copied to clipboard', 'success');
    setTimeout(() => setIsCopied(false), 2000);
  };

  return (
    <>
      <DialogHeader>
        <DialogTitle>{postback.name}</DialogTitle>
        <DialogDescription>
          Platform: <span className="font-medium">{postback.platform?.name ?? '—'}</span>
        </DialogDescription>
      </DialogHeader>

      <div className="space-y-4">
        <div className="space-y-2">
          <Label className="block">Base URL</Label>
          <Input readOnly value={postback.base_url} className="bg-muted text-xs" />
        </div>

        {postback.param_mappings && Object.keys(postback.param_mappings).length > 0 && (
          <div className="space-y-2">
            <Label className="block">Parameter Mappings</Label>
            <div className="divide-y rounded-md border text-sm">
              {Object.entries(postback.param_mappings).map(([param, token]) => (
                <div key={param} className="flex items-center justify-between px-3 py-2">
                  <span className="font-mono text-xs text-muted-foreground">{param}</span>
                  <span className="text-xs">→</span>
                  <span className="font-mono text-xs font-medium">{token || '—'}</span>
                </div>
              ))}
            </div>
          </div>
        )}

        {hasSources && (
          <div className="space-y-2">
            <Label className="block">Source</Label>
            <Select value={selectedSource} onValueChange={setSelectedSource}>
              <SelectTrigger>
                <SelectValue placeholder="Select a source" />
              </SelectTrigger>
              <SelectContent>
                {sources.map((s) => (
                  <SelectItem key={s.value} value={s.value}>
                    {s.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <p className="text-xs text-muted-foreground">
              The source identifies where the fire is triggered from. It replaces the <span className="font-mono">{'{source}'}</span> segment in the
              URL below.
            </p>
          </div>
        )}

        {postback.generated_url && (
          <Card className="mt-10 bg-muted">
            <CardHeader className="py-4 text-muted-foreground">
              <CardTitle className="block text-center text-xl">Generated URL</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="mb-2 flex gap-2">
                <Input readOnly value={displayUrl} className="font-mono" onClick={(e) => e.target.select()} />
                <Button type="button" size="icon" onClick={copyUrl} disabled={isCopied}>
                  {isCopied ? <Check className="h-4 w-4 text-success" /> : <Copy className="h-4 w-4" />}
                </Button>
              </div>
              <p className="text-center text-sm text-muted-foreground">
                {isInternal
                  ? 'Replace {fingerprint} with the lead fingerprint and append field names as query params to update their values for that lead.'
                  : 'Share this URL with your platform to receive postback notifications.'}
              </p>
            </CardContent>
          </Card>
        )}

        <div className="flex justify-end pt-2">
          <Button variant="outline" onClick={() => modal.resolve(modalId, false)}>
            Close
          </Button>
        </div>
      </div>
    </>
  );
}

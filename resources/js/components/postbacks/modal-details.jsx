import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCurrentModalId, useModal } from '@/hooks/use-modal';
import { useToast } from '@/hooks/use-toast';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';

export default function ModalDetails({ postback }) {
  const modal = useModal();
  const modalId = useCurrentModalId();
  const [isCopied, setIsCopied] = useState(false);
  const { addMessage } = useToast();

  const copyUrl = () => {
    navigator.clipboard.writeText(postback.generated_url);
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

        {postback.generated_url && (
          <Card className="mt-10 bg-muted">
            <CardHeader className="py-4 text-muted-foreground">
              <CardTitle className="block text-center text-xl">Generated URL</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="mb-2 flex gap-2">
                <Input readOnly value={postback.generated_url} className="font-mono" onClick={(e) => e.target.select()} />
                <Button type="button" size="icon" onClick={copyUrl} disabled={isCopied}>
                  {isCopied ? <Check className="h-4 w-4 text-success" /> : <Copy className="h-4 w-4" />}
                </Button>
              </div>
              <p className="text-center text-sm text-muted-foreground">
                {postback.type === 'internal'
                  ? 'Append field names as query params to update their values for the lead identified by the fingerprint.'
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

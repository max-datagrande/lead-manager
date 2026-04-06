import { Button } from '@/components/ui/button';
import { DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { useCurrentModalId, useModal } from '@/hooks/use-modal';
import type { InternalPostbackSummary } from '@/types/ping-post';
import axios from 'axios';
import { CheckCircle2, Loader2, XCircle, Zap } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { route } from 'ziggy-js';

type ModalState = 'loading' | 'confirm' | 'firing' | 'success' | 'error';

interface ResolvedPostback {
  id: number;
  name: string;
  resolved_url: string;
}

interface Props {
  dispatchId: number;
  postbacks: InternalPostbackSummary[];
}

export function FirePostbacksModal({ dispatchId, postbacks }: Props) {
  const modalId = useCurrentModalId();
  const modal = useModal();
  const [state, setState] = useState<ModalState>('loading');
  const [resolved, setResolved] = useState<ResolvedPostback[]>([]);
  const [message, setMessage] = useState('');
  const contentRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    axios
      .post(route('postbacks.associations.preview-for-dispatch'), { dispatch_id: dispatchId })
      .then(({ data }) => {
        setResolved(data.data);
        setState('confirm');
      })
      .catch(() => {
        setMessage('Failed to resolve postback URLs.');
        setState('error');
      });
  }, [dispatchId]);

  const lockHeight = useCallback(() => {
    if (contentRef.current) {
      const h = contentRef.current.offsetHeight;
      contentRef.current.style.minHeight = `${h}px`;
      contentRef.current.style.maxHeight = `${h}px`;
    }
  }, []);

  const handleFire = async () => {
    lockHeight();
    setState('firing');
    try {
      const { data } = await axios.post(route('postbacks.associations.fire-for-dispatch'), {
        dispatch_id: dispatchId,
      });
      setMessage(data.message);
      setState('success');
    } catch (err: any) {
      setMessage(err.response?.data?.message ?? 'An unexpected error occurred.');
      setState('error');
    }
  };

  return (
    <>
      <DialogHeader>
        <DialogTitle>Fire Internal Postbacks</DialogTitle>
        <DialogDescription>
          {state === 'loading' && 'Resolving postback URLs...'}
          {state === 'confirm' && 'The following postbacks will be fired for this dispatch.'}
          {state === 'firing' && 'Firing postbacks...'}
          {state === 'success' && 'Postbacks fired successfully.'}
          {state === 'error' && 'Something went wrong.'}
        </DialogDescription>
      </DialogHeader>

      <div ref={contentRef} className="flex items-center justify-center overflow-hidden">
        {state === 'loading' && (
          <div className="w-full space-y-3">
            {postbacks.map((p) => (
              <div key={p.id} className="space-y-2 rounded-lg border p-4">
                <div className="flex items-center gap-2">
                  <Skeleton className="h-4 w-4 rounded" />
                  <Skeleton className="h-4 w-40" />
                </div>
                <Skeleton className="h-8 w-full rounded-md" />
              </div>
            ))}
          </div>
        )}

        {state === 'confirm' && (
          <div className="w-full space-y-3">
            {resolved.map((p) => (
              <div key={p.id} className="space-y-2 rounded-lg border p-4">
                <div className="flex items-center">
                  <Zap className="h-4 w-4 shrink-0 text-primary" />
                  <p className="text-sm font-semibold">{p.name}</p>
                </div>
                <div className="overflow-x-auto rounded-md bg-muted px-3 py-2">
                  <p className="font-mono text-xs whitespace-nowrap text-muted-foreground">{p.resolved_url}</p>
                </div>
              </div>
            ))}
          </div>
        )}

        {state === 'firing' && <Loader2 className="h-10 w-10 animate-spin text-muted-foreground" />}

        {state === 'success' && (
          <div className="flex flex-col items-center gap-2">
            <CheckCircle2 className="h-10 w-10 text-emerald-500" />
            <p className="text-sm text-muted-foreground">{message}</p>
          </div>
        )}

        {state === 'error' && (
          <div className="flex flex-col items-center gap-2">
            <XCircle className="h-10 w-10 text-destructive" />
            <p className="text-sm text-muted-foreground">{message}</p>
          </div>
        )}
      </div>

      <DialogFooter>
        {state === 'confirm' && (
          <>
            <Button variant="outline" onClick={() => modal.resolve(modalId, false)}>
              Cancel
            </Button>
            <Button onClick={handleFire}>
              <Zap className="mr-1.5 h-4 w-4" />
              Fire {resolved.length} postback{resolved.length !== 1 ? 's' : ''}
            </Button>
          </>
        )}
        {(state === 'success' || state === 'error') && (
          <Button variant="outline" onClick={() => modal.resolve(modalId, state === 'success')}>
            Close
          </Button>
        )}
      </DialogFooter>
    </>
  );
}
